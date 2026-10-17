<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer acquisition/verification report from Customer identity facts.
 * "Historical spend by currency" reuses confirmed, non-cancelled Order
 * snapshots (see SalesReportQuery) rather than any current balance.
 */
final class CustomerReportQuery
{
    private const ALLOWED_SORTS = ['created_at', 'name', 'id'];

    /**
     * @return array{
     *     summary: array{new_customers: int, verified_customers: int},
     *     customers: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $newCustomers = (int) Customer::query()
            ->where('created_at', '>=', $filters->range->startAt)
            ->where('created_at', '<', $filters->range->endAt)
            ->count();

        $verifiedCustomers = (int) Customer::query()
            ->whereNotNull('phone_verified_at')
            ->where('phone_verified_at', '>=', $filters->range->startAt)
            ->where('phone_verified_at', '<', $filters->range->endAt)
            ->count();

        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'created_at';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        $customers = Customer::query()
            ->where('created_at', '>=', $filters->range->startAt)
            ->where('created_at', '<', $filters->range->endAt)
            ->withCount(['orders as orders_count' => function ($q): void {
                $q->whereNotNull('confirmed_at');
            }])
            ->withMax(['orders as last_order_at' => function ($q): void {
                $q->whereNotNull('confirmed_at');
            }], 'confirmed_at')
            ->orderBy($sort, $direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        return [
            'summary' => [
                'new_customers' => $newCustomers,
                'verified_customers' => $verifiedCustomers,
            ],
            'customers' => $customers,
        ];
    }
}
