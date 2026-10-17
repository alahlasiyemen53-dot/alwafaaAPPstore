<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Enums\CouponUsageStatus;
use App\Enums\OrderStatus;
use App\Models\CouponUsage;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Historical marketing discount report from confirmed Order snapshots and
 * CouponUsage facts. Never recalculates using current Promotion/Coupon/
 * Bundle configuration, and never claims incremental/attributed revenue.
 */
final class MarketingReportQuery
{
    private const ALLOWED_SORTS = ['confirmed_at', 'order_number', 'total', 'id'];

    /**
     * @return array{
     *     discounts: array<int, array{currency_id: int, currency_code: string, promotion_discount_total: string, coupon_discount_total: string, bundle_discount_total: string, gross_eligible_amount: string, net_amount: string}>,
     *     coupon_usage: array{used: int, released: int},
     *     rows: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $base = Order::query()
            ->whereNotNull('confirmed_at')
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('confirmed_at', '>=', $filters->range->startAt)
            ->where('confirmed_at', '<', $filters->range->endAt)
            ->where(function ($q): void {
                $q->where('promotion_discount_total', '>', 0)
                    ->orWhere('coupon_discount_total', '>', 0)
                    ->orWhere('bundle_discount_total', '>', 0);
            });

        if ($filters->currencyId !== null) {
            $base->where('currency_id', $filters->currencyId);
        }

        $discountRows = (clone $base)
            ->toBase()->join('currencies', 'currencies.id', '=', 'orders.currency_id')
            ->groupBy('orders.currency_id', 'currencies.code')
            ->selectRaw('orders.currency_id, currencies.code as currency_code,
                SUM(orders.promotion_discount_total) as promotion_discount_total,
                SUM(orders.coupon_discount_total) as coupon_discount_total,
                SUM(orders.bundle_discount_total) as bundle_discount_total,
                SUM(orders.subtotal) as gross_eligible_amount,
                SUM(orders.total) as net_amount')
            ->get();

        $discounts = $discountRows->map(fn ($row): array => [
            'currency_id' => (int) $row->currency_id,
            'currency_code' => (string) $row->currency_code,
            'promotion_discount_total' => (string) $row->promotion_discount_total,
            'coupon_discount_total' => (string) $row->coupon_discount_total,
            'bundle_discount_total' => (string) $row->bundle_discount_total,
            'gross_eligible_amount' => (string) $row->gross_eligible_amount,
            'net_amount' => (string) $row->net_amount,
        ])->all();

        $couponUsage = [
            'used' => (int) CouponUsage::query()
                ->where('status', CouponUsageStatus::Active)
                ->where('used_at', '>=', $filters->range->startAt)
                ->where('used_at', '<', $filters->range->endAt)
                ->count(),
            'released' => (int) CouponUsage::query()
                ->where('status', CouponUsageStatus::Released)
                ->whereNotNull('released_at')
                ->where('released_at', '>=', $filters->range->startAt)
                ->where('released_at', '<', $filters->range->endAt)
                ->count(),
        ];

        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'confirmed_at';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        $rows = (clone $base)
            ->with(['customer:id,name', 'currency:id,code'])
            ->orderBy($sort, $direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        return [
            'discounts' => $discounts,
            'coupon_usage' => $couponUsage,
            'rows' => $rows,
        ];
    }
}
