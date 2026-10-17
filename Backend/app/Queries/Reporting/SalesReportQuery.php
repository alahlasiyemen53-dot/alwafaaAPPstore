<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Historical Sales report from confirmed, non-cancelled Order snapshots.
 * Never recalculates using current catalog/campaign data.
 */
final class SalesReportQuery
{
    private const ALLOWED_SORTS = ['confirmed_at', 'order_number', 'total', 'id'];

    /**
     * @return array{
     *     summary: array<int, array{currency_id: int, currency_code: string, orders_count: int, gross_subtotal: string, discount_total: string, net_total: string, average_order_value: string}>,
     *     trend: array<int, array{date: string, currency_id: int, currency_code: string, net_total: string, orders_count: int}>,
     *     rows: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $base = $this->baseQuery($filters);

        $summaryRows = (clone $base)
            ->toBase()
            ->join('currencies', 'currencies.id', '=', 'orders.currency_id')
            ->groupBy('orders.currency_id', 'currencies.code')
            ->selectRaw('orders.currency_id, currencies.code as currency_code, COUNT(orders.id) as orders_count, SUM(orders.subtotal) as gross_subtotal, SUM(orders.discount_amount) as discount_total, SUM(orders.total) as net_total')
            ->get();

        $summary = $summaryRows->map(function ($row): array {
            $count = (int) $row->orders_count;
            $net = (string) $row->net_total;

            return [
                'currency_id' => (int) $row->currency_id,
                'currency_code' => (string) $row->currency_code,
                'orders_count' => $count,
                'gross_subtotal' => (string) $row->gross_subtotal,
                'discount_total' => (string) $row->discount_total,
                'net_total' => $net,
                'average_order_value' => $count > 0 ? bcdiv($net, (string) $count, 4) : '0.0000',
            ];
        })->all();

        $trendRows = (clone $base)
            ->toBase()
            ->join('currencies', 'currencies.id', '=', 'orders.currency_id')
            ->groupBy('orders.currency_id', 'currencies.code', DB::raw('DATE(orders.confirmed_at)'))
            ->orderBy(DB::raw('DATE(orders.confirmed_at)'))
            ->selectRaw('DATE(orders.confirmed_at) as report_date, orders.currency_id, currencies.code as currency_code, SUM(orders.total) as net_total, COUNT(orders.id) as orders_count')
            ->get();

        $trend = $trendRows->map(fn ($row): array => [
            'date' => (string) $row->report_date,
            'currency_id' => (int) $row->currency_id,
            'currency_code' => (string) $row->currency_code,
            'net_total' => (string) $row->net_total,
            'orders_count' => (int) $row->orders_count,
        ])->all();

        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'confirmed_at';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        $rows = (clone $base)
            ->with(['customer:id,name', 'currency:id,code,symbol'])
            ->orderBy($sort, $direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        return [
            'summary' => $summary,
            'trend' => $trend,
            'rows' => $rows,
        ];
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    public function executeForExport(ReportFilterData $filters): \Generator
    {
        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'confirmed_at';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        foreach ($this->baseQuery($filters)->with(['customer:id,name', 'currency:id,code'])->orderBy($sort, $direction)->lazyById(500, 'orders.id') as $order) {
            yield [
                $order->order_number,
                $order->customer->name,
                $order->currency->code,
                (string) $order->subtotal,
                (string) $order->discount_amount,
                (string) $order->total,
                $order->confirmed_at?->toDateTimeString() ?? '',
            ];
        }
    }

    /** @return Builder<Order> */
    private function baseQuery(ReportFilterData $filters): Builder
    {
        $base = Order::query()
            ->whereNotNull('confirmed_at')
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('confirmed_at', '>=', $filters->range->startAt)
            ->where('confirmed_at', '<', $filters->range->endAt);

        if ($filters->currencyId !== null) {
            $base->where('currency_id', $filters->currencyId);
        }
        if ($filters->customerId !== null) {
            $base->where('customer_id', $filters->customerId);
        }
        if ($filters->warehouseId !== null) {
            $base->where('warehouse_id', $filters->warehouseId);
        }

        return $base;
    }
}
