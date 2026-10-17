<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\DriverCodSettlementData;
use App\DTOs\DriverCodSettlementItemData;
use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCodSettlement;
use App\Models\DriverCodSettlementItem;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use App\Services\AuditLogger;
use App\Support\TreasuryAccountResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateDraftDriverCodSettlementAction
{
    public function __construct(
        private readonly GenerateDriverCodSettlementNumberAction $numberGenerator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(DriverCodSettlementData $data, int $userId = 0): DriverCodSettlement
    {
        return DB::transaction(function () use ($data, $userId): DriverCodSettlement {
            // 1. Validate Driver
            /** @var Driver $driver */
            $driver = Driver::query()->findOrFail($data->driverId);
            if (! $driver->is_active) {
                throw new DomainException(sprintf('السائق المحدد (%s) معطل ولا يمكن إجراء تسوية له.', $driver->name));
            }

            // 2. Validate Currency
            /** @var Currency $currency */
            $currency = Currency::query()->findOrFail($data->currencyId);

            // 3. Validate Treasury Destination
            $treasury = $this->resolveAndValidateTreasuryAccount($data->treasuryAccountType, $data->treasuryAccountId, $currency->id);

            // 4. Validate Items
            if (empty($data->items)) {
                throw new DomainException('يجب تحديد شحنة واحدة على الأقل للتسوية.');
            }

            $deliveryIds = array_map(fn (DriverCodSettlementItemData $i) => $i->deliveryId, $data->items);
            if (count($deliveryIds) !== count(array_unique($deliveryIds))) {
                throw new DomainException('يحتوي طلب التسوية على شحنات مكررة.');
            }

            // Lock deliveries in deterministic order
            /** @var array<int, Delivery> $deliveries */
            $deliveries = Delivery::query()
                ->whereIn('id', $deliveryIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all();

            $totalExpected = 0.0;
            $totalSettled = 0.0;
            $itemsToCreate = [];

            foreach ($data->items as $itemData) {
                if (! isset($deliveries[$itemData->deliveryId])) {
                    throw new DomainException(sprintf('الشحنة رقم (%d) غير موجودة.', $itemData->deliveryId));
                }

                $delivery = $deliveries[$itemData->deliveryId];

                // Delivery must belong to this driver
                if ($delivery->driver_id !== $driver->id) {
                    throw new DomainException(sprintf('الشحنة رقم (%s) غير مسندة للسائق المحدد.', $delivery->delivery_number));
                }

                // Delivery must be in a settled-eligible status
                if ($delivery->status !== DeliveryStatus::Delivered) {
                    throw new DomainException(sprintf('الشحنة رقم (%s) ليست بحالة تسليم ناجح (حالتها: %s).', $delivery->delivery_number, $delivery->status->value));
                }

                // Currency mismatch between delivery COD and settlement
                if ((int) $delivery->cod_currency_id !== $currency->id) {
                    throw new DomainException(sprintf(
                        'عملة الشحنة (%s) لا تطابق عملة التسوية المطلوبة (%s). التسوية عبر عملات متعددة غير مدعومة.',
                        $delivery->codCurrency->code ?? (string) $delivery->cod_currency_id,
                        $currency->code
                    ));
                }

                $collectedAmount = (float) ($delivery->cod_collected_amount ?? 0);
                if ($collectedAmount <= 0) {
                    throw new DomainException(sprintf('الشحنة رقم (%s) لا تحتوي على مبالغ محصلة للتسوية.', $delivery->delivery_number));
                }

                // Calculate already posted/settled amount for this delivery
                $previouslySettled = (float) DriverCodSettlementItem::query()
                    ->where('delivery_id', $delivery->id)
                    ->whereHas('settlement', fn ($q) => $q->whereIn('status', [
                        DriverCodSettlementStatus::Posted->value,
                        DriverCodSettlementStatus::Draft->value,
                    ]))
                    ->sum('settlement_amount');

                $outstanding = $collectedAmount - $previouslySettled;
                if ($outstanding <= 0.0001) {
                    throw new DomainException(sprintf('الشحنة رقم (%s) تمت تسويتها بالكامل مسبقاً.', $delivery->delivery_number));
                }

                $settleAmount = (float) $itemData->settlementAmount;

                if ($settleAmount <= 0) {
                    throw new DomainException(sprintf('مبلغ التسوية للشحنة (%s) يجب أن يكون أكبر من الصفر.', $delivery->delivery_number));
                }

                // Strictly disallow over-settlement beyond outstanding COD
                if (round($settleAmount, 4) > round($outstanding, 4)) {
                    throw new DomainException(sprintf(
                        'المبلغ المطلوب تسويته (%.2f) يتجاوز الرصيد المستحق المتبقي عليها للشحنة (%s: %.2f).',
                        $settleAmount,
                        $delivery->delivery_number,
                        $outstanding
                    ));
                }

                $totalExpected += $outstanding;
                $totalSettled += $settleAmount;

                $itemsToCreate[] = [
                    'delivery_id' => $delivery->id,
                    'order_id' => $delivery->order_id,
                    'collected_amount' => $collectedAmount,
                    'previously_settled_amount' => $previouslySettled,
                    'settlement_amount' => $settleAmount,
                    'currency_id' => $currency->id,
                ];
            }

            // 5. Generate Settlement Number
            $settlementNumber = $this->numberGenerator->execute();

            $treasuryCurrencyAccountId = null;
            if ($treasury instanceof TreasuryCurrencyAccount) {
                $treasuryCurrencyAccountId = $treasury->id;
            } elseif (method_exists($treasury, 'currencyAccounts')) {
                $treasuryCurrencyAccountId = $treasury->currencyAccounts()
                    ->where('currency_id', $currency->id)
                    ->where('is_active', true)
                    ->value('id');
            }

            // 6. Create DriverCodSettlement
            /** @var DriverCodSettlement $settlement */
            $settlement = DriverCodSettlement::create([
                'settlement_number' => $settlementNumber,
                'driver_id' => $driver->id,
                'currency_id' => $currency->id,
                'status' => DriverCodSettlementStatus::Draft,
                'treasury_account_type' => get_class($treasury),
                'treasury_account_id' => $treasury->id,
                'treasury_currency_account_id' => $treasuryCurrencyAccountId,
                'total_expected' => round($totalExpected, 4),
                'total_settled' => round($totalSettled, 4),
                'notes' => $data->notes,
                'created_by' => $userId > 0 ? $userId : null,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            // 7. Create Settlement Items
            foreach ($itemsToCreate as $item) {
                $settlement->items()->create($item);
            }

            // 8. Audit Log
            $this->auditLogger->record('admin.cod_settlement.draft_created', $settlement, metadata: [
                'settlement_number' => $settlement->settlement_number,
                'driver_name' => $driver->name,
                'currency_code' => $currency->code,
                'total_settled' => $settlement->total_settled,
                'items_count' => count($itemsToCreate),
            ]);

            return $settlement;
        });
    }

    private function resolveAndValidateTreasuryAccount(string $type, int $id, int $expectedCurrencyId): Cashbox|BankAccount|ElectronicWalletAccount|TreasuryCurrencyAccount
    {
        $resolvedClass = match ($type) {
            Cashbox::class, 'cashbox', 'Cashbox' => Cashbox::class,
            BankAccount::class, 'bank_account', 'BankAccount' => BankAccount::class,
            ElectronicWalletAccount::class, 'electronic_wallet', 'ElectronicWalletAccount' => ElectronicWalletAccount::class,
            TreasuryCurrencyAccount::class, 'treasury_currency_account', 'TreasuryCurrencyAccount' => TreasuryCurrencyAccount::class,
            default => throw new DomainException(sprintf('نوع حساب الخزينة المحدد غير صالح (%s).', $type)),
        };

        $model = $resolvedClass::query()->findOrFail($id);
        $account = TreasuryAccountResolver::resolve($model, sprintf('حساب الخزينة غير صالح (%s).', $type));

        if (! $account->is_active) {
            throw new DomainException(sprintf('حساب الخزينة المحدد (%s) معطل حالياً ولا يمكن الإيداع فيه.', $account->displayName));
        }

        if ($account instanceof TreasuryCurrencyAccount) {
            if ((int) $account->currency_id !== $expectedCurrencyId) {
                throw new DomainException('عملة حساب الخزينة المحدد لا تطابق عملة التسوية. التسوية عبر عملات مختلفة غير مدعومة.');
            }

            if (empty($account->gl_account_id)) {
                throw new DomainException(sprintf('حساب الخزينة المحدد (%s) غير مرتبط بحساب في دليل الحسابات العام (GL Account).', $account->displayName));
            }
        } else {
            $hasMatchingCurrency = ((int) $account->currency_id === $expectedCurrencyId)
                || ($account->currencyAccounts()->where('currency_id', $expectedCurrencyId)->where('is_active', true)->exists());

            if (! $hasMatchingCurrency) {
                throw new DomainException('عملة حساب الخزينة المحدد لا تطابق عملة التسوية. التسوية عبر عملات مختلفة غير مدعومة.');
            }
        }

        return $account;
    }
}
