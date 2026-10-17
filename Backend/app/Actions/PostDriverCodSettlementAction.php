<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCodSettlement;
use App\Models\DriverCodSettlementItem;
use App\Models\TreasuryCurrencyAccount;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostDriverCodSettlementAction
{
    public function __construct(
        private readonly BuildJournalFromCodSettlementAction $buildJournalAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(DriverCodSettlement $settlement, int $userId = 0): DriverCodSettlement
    {
        return DB::transaction(function () use ($settlement, $userId): DriverCodSettlement {
            // 1. Lock and validate Settlement
            /** @var DriverCodSettlement $lockedSettlement */
            $lockedSettlement = DriverCodSettlement::query()
                ->lockForUpdate()
                ->with(['items'])
                ->findOrFail($settlement->id);

            // Idempotency: if already posted
            if ($lockedSettlement->status === DriverCodSettlementStatus::Posted) {
                return $lockedSettlement->load(['driver', 'currency', 'treasuryAccount', 'items.delivery.order', 'journalEntry.lines']);
            }

            if ($lockedSettlement->status === DriverCodSettlementStatus::Reversed) {
                throw new DomainException('لا يمكن ترحيل تسوية معكوسة/ملغاة.');
            }

            if ($lockedSettlement->items->isEmpty()) {
                throw new DomainException('لا يمكن ترحيل تسوية لا تحتوي على أي بنود شحنات.');
            }

            if ((float) $lockedSettlement->total_settled <= 0) {
                throw new DomainException('إجمالي مبلغ التسوية يجب أن يكون أكبر من الصفر.');
            }

            // 2. Lock Driver
            /** @var Driver $driver */
            $driver = Driver::query()->lockForUpdate()->findOrFail($lockedSettlement->driver_id);
            if (! $driver->is_active) {
                throw new DomainException(sprintf('المندوب (%s) معطل حالياً ولا يمكن ترحيل تسوية له.', $driver->name));
            }

            // 3. Lock Treasury Account
            $treasuryClass = $lockedSettlement->treasury_account_type;
            if (! class_exists($treasuryClass)) {
                throw new DomainException('نوع حساب الخزينة غير صالح.');
            }
            $treasury = $treasuryClass::query()->lockForUpdate()->findOrFail($lockedSettlement->treasury_account_id);
            if (! $treasury->is_active) {
                throw new DomainException(sprintf('حساب الخزينة المحدد (%s) معطل حالياً.', $treasury->name_ar ?? $treasury->name));
            }

            // Resolve specific currency account
            $currencyAccount = null;
            if ($treasury instanceof TreasuryCurrencyAccount) {
                $currencyAccount = $treasury;
            } elseif (method_exists($treasury, 'currencyAccounts')) {
                $currencyAccount = $treasury->currencyAccounts()
                    ->where('currency_id', $lockedSettlement->currency_id)
                    ->lockForUpdate()
                    ->first();
            }

            $targetCurrencyId = $currencyAccount ? $currencyAccount->currency_id : $treasury->currency_id;
            if ((int) $targetCurrencyId !== (int) $lockedSettlement->currency_id) {
                throw new DomainException('عملة حساب الخزينة لا تطابق عملة التسوية.');
            }

            $glAccountId = $currencyAccount ? $currencyAccount->gl_account_id : $treasury->gl_account_id;
            if (empty($glAccountId)) {
                throw new DomainException(sprintf('حساب الخزينة (%s) غير مرتبط بحساب في الأستاذ العام.', $treasury->name_ar ?? $treasury->name));
            }

            if ($currencyAccount) {
                $lockedSettlement->treasury_currency_account_id = $currencyAccount->id;
            }

            // 4. Lock Deliveries in deterministic order to prevent deadlocks
            $deliveryIds = $lockedSettlement->items->pluck('delivery_id')->unique()->sort()->values()->all();
            $deliveries = Delivery::query()
                ->whereIn('id', $deliveryIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sumCalculated = 0.0;

            // 5. Revalidate each line allocation under strict row locks
            foreach ($lockedSettlement->items as $item) {
                if (! isset($deliveries[$item->delivery_id])) {
                    throw new DomainException(sprintf('الشحنة رقم (%d) غير موجودة.', $item->delivery_id));
                }

                $delivery = $deliveries[$item->delivery_id];

                if ($delivery->status !== DeliveryStatus::Delivered) {
                    throw new DomainException(sprintf('الشحنة (%s) ليست بحالة تسليم ناجح (DELIVERED).', $delivery->delivery_number));
                }

                if ((int) $delivery->cod_currency_id !== (int) $lockedSettlement->currency_id) {
                    throw new DomainException(sprintf('عملة الشحنة (%s) لا تطابق عملة التسوية.', $delivery->delivery_number));
                }

                $collected = (float) ($delivery->cod_collected_amount ?? 0);
                if ($collected <= 0) {
                    throw new DomainException(sprintf('الشحنة (%s) ليس عليها مبلغ تحصيل COD موجب.', $delivery->delivery_number));
                }

                // Recalculate settled sum from active POSTED settlements under lock
                $previouslySettled = (float) DriverCodSettlementItem::query()
                    ->join('driver_cod_settlements', 'driver_cod_settlements.id', '=', 'driver_cod_settlement_items.settlement_id')
                    ->where('driver_cod_settlement_items.delivery_id', $delivery->id)
                    ->where('driver_cod_settlements.status', DriverCodSettlementStatus::Posted->value)
                    ->lockForUpdate()
                    ->sum('driver_cod_settlement_items.settlement_amount');

                $outstanding = max(0.0, round($collected - $previouslySettled, 4));
                $settleAmount = (float) $item->settlement_amount;

                if ($settleAmount <= 0) {
                    throw new DomainException(sprintf('مبلغ التسوية للشحنة (%s) يجب أن يكون أكبر من الصفر.', $delivery->delivery_number));
                }

                // Over-settlement protection under concurrency
                if ($settleAmount > $outstanding) {
                    throw new DomainException(sprintf(
                        'لا يمكن ترحيل التسوية: مبلغ التسوية المطلوب (%s) للشحنة (%s) يتجاوز الرصيد المستحق الفعلي المتبقي عليها (%s). تم رفض الترحيل لمنع التسوية الزائدة.',
                        number_format($settleAmount, 2),
                        $delivery->delivery_number,
                        number_format($outstanding, 2)
                    ));
                }

                // Update item's snapshot of previously settled
                $item->update([
                    'previously_settled_amount' => $previouslySettled,
                ]);

                $sumCalculated += $settleAmount;
            }

            if (round($sumCalculated, 4) !== round((float) $lockedSettlement->total_settled, 4)) {
                $lockedSettlement->total_settled = round($sumCalculated, 4);
            }

            // 6. Build and Post Journal Entry
            $effectiveUserId = $userId > 0 ? $userId : (auth()->id() ?: null);
            $journalEntry = $this->buildJournalAction->execute($lockedSettlement, $effectiveUserId ?? 0);

            // 7. Mark Settlement as POSTED
            $now = now();
            $lockedSettlement->update([
                'status' => DriverCodSettlementStatus::Posted,
                'journal_entry_id' => $journalEntry->id,
                'posted_at' => $now,
                'posted_by' => $effectiveUserId,
                'updated_by' => $effectiveUserId,
            ]);

            // 8. Audit Log
            $this->auditLogger->record('admin.cod_settlement.posted', $lockedSettlement, metadata: [
                'settlement_number' => $lockedSettlement->settlement_number,
                'driver_name' => $driver->name,
                'total_settled' => $lockedSettlement->total_settled,
                'journal_entry_number' => $journalEntry->entry_number,
                'treasury_type' => class_basename($treasury),
                'treasury_id' => $treasury->id,
            ]);

            return $lockedSettlement->fresh(['driver', 'currency', 'treasuryAccount', 'items.delivery.order', 'journalEntry.lines']);
        });
    }
}
