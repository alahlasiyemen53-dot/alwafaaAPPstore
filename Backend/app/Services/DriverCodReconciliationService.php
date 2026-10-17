<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Models\Currency;
use App\Models\Delivery;
use App\Models\DriverCodSettlement;
use App\Models\DriverCodSettlementItem;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

final class DriverCodReconciliationService
{
    /**
     * @return array{
     *     status: 'MATCHED'|'DISCREPANCY_FOUND',
     *     summary: array<string, mixed>,
     *     discrepancies: array<int, string>,
     *     currencies_summary: array<int, array<string, mixed>>
     * }
     */
    public function reconcile(): array
    {
        $discrepancies = [];

        // 1. Currencies breakdown
        $currencies = Currency::query()->where('is_active', true)->get();
        $currenciesSummary = [];

        foreach ($currencies as $currency) {
            $collected = (float) Delivery::query()
                ->where('cod_currency_id', $currency->id)
                ->where('status', DeliveryStatus::Delivered)
                ->where('cod_collected_amount', '>', 0)
                ->sum('cod_collected_amount');

            $settled = (float) DriverCodSettlementItem::query()
                ->join('driver_cod_settlements', 'driver_cod_settlements.id', '=', 'driver_cod_settlement_items.settlement_id')
                ->where('driver_cod_settlements.currency_id', $currency->id)
                ->where('driver_cod_settlements.status', DriverCodSettlementStatus::Posted->value)
                ->sum('driver_cod_settlement_items.settlement_amount');

            $outstanding = max(0.0, round($collected - $settled, 4));

            $postedSettlementsSum = (float) DriverCodSettlement::query()
                ->where('currency_id', $currency->id)
                ->where('status', DriverCodSettlementStatus::Posted)
                ->sum('total_settled');

            if (round($settled, 4) !== round($postedSettlementsSum, 4)) {
                $discrepancies[] = sprintf('عدم تطابق في العملة (%s): مجموع بنود التسويات (%s) لا يطابق إجمالي التسويات المرحلة (%s).', $currency->code, number_format($settled, 2), number_format($postedSettlementsSum, 2));
            }

            $currenciesSummary[] = [
                'currency_id' => $currency->id,
                'currency_name' => $currency->name_ar ?? $currency->code,
                'currency_code' => $currency->code,
                'total_collected' => round($collected, 4),
                'total_settled' => round($settled, 4),
                'total_outstanding' => $outstanding,
                'settlements_count' => DriverCodSettlement::query()->where('currency_id', $currency->id)->count(),
            ];
        }

        // 2. Check for over-settled deliveries
        $subSettled = DriverCodSettlementItem::query()
            ->select('driver_cod_settlement_items.delivery_id', DB::raw('SUM(driver_cod_settlement_items.settlement_amount) as settled_sum'))
            ->join('driver_cod_settlements', 'driver_cod_settlements.id', '=', 'driver_cod_settlement_items.settlement_id')
            ->where('driver_cod_settlements.status', DriverCodSettlementStatus::Posted->value)
            ->groupBy('driver_cod_settlement_items.delivery_id');

        $overSettled = Delivery::query()
            ->joinSub($subSettled, 's', 's.delivery_id', '=', 'deliveries.id')
            ->whereRaw('s.settled_sum > deliveries.cod_collected_amount')
            ->get();

        foreach ($overSettled as $os) {
            $settledSum = (float) ($os->getAttribute('settled_sum') ?? 0);
            $discrepancies[] = sprintf('إفراط في التسوية للشحنة (%s): تم تسوية (%s) والمحصل الفعلي (%s).', $os->delivery_number, number_format($settledSum, 2), number_format((float) $os->cod_collected_amount, 2));
        }

        // 3. Check for POSTED settlements without Journal Entry or unbalance
        $postedSettlements = DriverCodSettlement::query()
            ->where('status', DriverCodSettlementStatus::Posted)
            ->with(['journalEntry.lines'])
            ->get();

        foreach ($postedSettlements as $settlement) {
            if (! $settlement->journal_entry_id || ! $settlement->journalEntry) {
                $discrepancies[] = sprintf('التسوية المرحلة (%s) غير مرتبطة بقيد يومية عامة.', $settlement->settlement_number);

                continue;
            }

            /** @var JournalEntry $je */
            $je = $settlement->journalEntry;
            if ($je->total_debit != $je->total_credit) {
                $discrepancies[] = sprintf('قيد اليومية (%s) المرتبط بالتسوية (%s) غير متوازن (مدين: %s, دائن: %s).', $je->entry_number, $settlement->settlement_number, $je->total_debit, $je->total_credit);
            }
        }

        return [
            'status' => empty($discrepancies) ? 'MATCHED' : 'DISCREPANCY_FOUND',
            'summary' => [
                'total_posted_settlements' => DriverCodSettlement::query()->where('status', DriverCodSettlementStatus::Posted)->count(),
                'total_draft_settlements' => DriverCodSettlement::query()->where('status', DriverCodSettlementStatus::Draft)->count(),
                'total_reversed_settlements' => DriverCodSettlement::query()->where('status', DriverCodSettlementStatus::Reversed)->count(),
            ],
            'discrepancies' => $discrepancies,
            'currencies_summary' => $currenciesSummary,
        ];
    }
}
