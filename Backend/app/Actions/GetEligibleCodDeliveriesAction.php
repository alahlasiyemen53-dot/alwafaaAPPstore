<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Models\Delivery;
use App\Models\DriverCodSettlementItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class GetEligibleCodDeliveriesAction
{
    /**
     * @return Collection<int, array{
     *     delivery_id: int,
     *     delivery_number: string,
     *     order_id: int,
     *     order_number: string,
     *     customer_name: string,
     *     delivered_at: string,
     *     collected_amount: float,
     *     previously_settled_amount: float,
     *     outstanding_amount: float,
     *     currency_id: int,
     *     currency_code: string
     * }>
     */
    public function execute(int $driverId, int $currencyId): Collection
    {
        // 1. Subquery for sum of settled amounts per delivery from POSTED settlements
        $subSettled = DriverCodSettlementItem::query()
            ->select('driver_cod_settlement_items.delivery_id', DB::raw('SUM(driver_cod_settlement_items.settlement_amount) as settled_sum'))
            ->join('driver_cod_settlements', 'driver_cod_settlements.id', '=', 'driver_cod_settlement_items.settlement_id')
            ->where('driver_cod_settlements.status', DriverCodSettlementStatus::Posted->value)
            ->groupBy('driver_cod_settlement_items.delivery_id');

        // 2. Fetch deliveries
        $deliveries = Delivery::query()
            ->with(['order.customer', 'codCurrency'])
            ->leftJoinSub($subSettled, 'settled_agg', 'settled_agg.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.driver_id', $driverId)
            ->where('deliveries.cod_currency_id', $currencyId)
            ->where('deliveries.status', DeliveryStatus::Delivered)
            ->where('deliveries.cod_collected_amount', '>', 0)
            ->whereRaw('deliveries.cod_collected_amount > COALESCE(settled_agg.settled_sum, 0)')
            ->orderBy('deliveries.delivered_at')
            ->select('deliveries.*', DB::raw('COALESCE(settled_agg.settled_sum, 0) as settled_sum'))
            ->get();

        return $deliveries->map(function (Delivery $delivery): array {
            $collected = (float) ($delivery->cod_collected_amount ?? 0);
            $previouslySettled = (float) ($delivery->settled_sum ?? 0);
            $outstanding = max(0.0, round($collected - $previouslySettled, 4));

            return [
                'delivery_id' => $delivery->id,
                'delivery_number' => (string) $delivery->delivery_number,
                'order_id' => (int) $delivery->order_id,
                'order_number' => (string) ($delivery->order->order_number ?? ''),
                'customer_name' => (string) ($delivery->recipient_name ?? $delivery->order->customer->name ?? 'عميل'),
                'delivered_at' => $delivery->delivered_at ? $delivery->delivered_at->format('Y-m-d H:i') : '',
                'collected_amount' => round($collected, 4),
                'previously_settled_amount' => round($previouslySettled, 4),
                'outstanding_amount' => $outstanding,
                'currency_id' => (int) $delivery->cod_currency_id,
                // codCurrency is guaranteed present: the query above filters deliveries.cod_currency_id = $currencyId.
                'currency_code' => (string) ($delivery->codCurrency->code ?? ''),
            ];
        });
    }
}
