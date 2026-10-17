<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Models\Delivery;
use App\Models\DriverCodSettlementItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CalculateDriverCodOutstandingAction
{
    /**
     * @return array{
     *     total_collected: float,
     *     total_settled: float,
     *     total_outstanding: float,
     *     oldest_outstanding_date: string|null,
     *     eligible_count: int
     * }
     */
    public function execute(int $driverId, int $currencyId): array
    {
        // 1. Total collected from DELIVERED COD deliveries
        $totalCollected = (float) Delivery::query()
            ->where('driver_id', $driverId)
            ->where('cod_currency_id', $currencyId)
            ->where('status', DeliveryStatus::Delivered)
            ->where('cod_collected_amount', '>', 0)
            ->sum('cod_collected_amount');

        // 2. Total settled from active POSTED settlements
        $totalSettled = (float) DriverCodSettlementItem::query()
            ->join('driver_cod_settlements', 'driver_cod_settlements.id', '=', 'driver_cod_settlement_items.settlement_id')
            ->where('driver_cod_settlements.driver_id', $driverId)
            ->where('driver_cod_settlements.currency_id', $currencyId)
            ->where('driver_cod_settlements.status', DriverCodSettlementStatus::Posted->value)
            ->sum('driver_cod_settlement_items.settlement_amount');

        $totalOutstanding = max(0.0, round($totalCollected - $totalSettled, 4));

        // 3. Find oldest delivery with outstanding amount > 0
        $subSettled = DriverCodSettlementItem::query()
            ->select('driver_cod_settlement_items.delivery_id', DB::raw('SUM(driver_cod_settlement_items.settlement_amount) as settled_sum'))
            ->join('driver_cod_settlements', 'driver_cod_settlements.id', '=', 'driver_cod_settlement_items.settlement_id')
            ->where('driver_cod_settlements.driver_id', $driverId)
            ->where('driver_cod_settlements.currency_id', $currencyId)
            ->where('driver_cod_settlements.status', DriverCodSettlementStatus::Posted->value)
            ->groupBy('driver_cod_settlement_items.delivery_id');

        $eligibleDeliveries = Delivery::query()
            ->leftJoinSub($subSettled, 'settled_agg', 'settled_agg.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.driver_id', $driverId)
            ->where('deliveries.cod_currency_id', $currencyId)
            ->where('deliveries.status', DeliveryStatus::Delivered)
            ->where('deliveries.cod_collected_amount', '>', 0)
            ->whereRaw('deliveries.cod_collected_amount > COALESCE(settled_agg.settled_sum, 0)')
            ->orderBy('deliveries.delivered_at')
            ->select('deliveries.id', 'deliveries.delivered_at', 'deliveries.cod_collected_amount', DB::raw('COALESCE(settled_agg.settled_sum, 0) as settled_sum'))
            ->get();

        $oldestDate = $eligibleDeliveries->first()?->delivered_at;

        return [
            'total_collected' => round($totalCollected, 4),
            'total_settled' => round($totalSettled, 4),
            'total_outstanding' => $totalOutstanding,
            'oldest_outstanding_date' => $oldestDate ? Carbon::parse($oldestDate)->toDateTimeString() : null,
            'eligible_count' => $eligibleDeliveries->count(),
        ];
    }
}
