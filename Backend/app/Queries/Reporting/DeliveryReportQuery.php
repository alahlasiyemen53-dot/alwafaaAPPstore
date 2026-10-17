<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Delivery outcome report using each delivery's own terminal-outcome
 * timestamp (delivered_at/failed_at/returned_at), matching Sprint 12 states.
 */
final class DeliveryReportQuery
{
    private const ALLOWED_SORTS = ['id', 'delivered_at', 'failed_at', 'returned_at'];

    /**
     * @return array{
     *     status_counts: array{delivered: int, failed: int, returned_to_warehouse: int, assigned: int},
     *     success_rate: string,
     *     drivers: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $start = $filters->range->startAt;
        $end = $filters->range->endAt;

        $outcomeQuery = fn (string $column, DeliveryStatus $status) => Delivery::query()
            ->where('status', $status)
            ->whereNotNull($column)
            ->where($column, '>=', $start)
            ->where($column, '<', $end)
            ->when($filters->driverId !== null, fn ($q) => $q->where('driver_id', $filters->driverId));

        $delivered = (int) $outcomeQuery('delivered_at', DeliveryStatus::Delivered)->count();
        $failed = (int) $outcomeQuery('failed_at', DeliveryStatus::Failed)->count();
        $returned = (int) $outcomeQuery('returned_at', DeliveryStatus::ReturnedToWarehouse)->count();

        $assigned = (int) Delivery::query()
            ->where('status', DeliveryStatus::Assigned)
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '>=', $start)
            ->where('assigned_at', '<', $end)
            ->when($filters->driverId !== null, fn ($q) => $q->where('driver_id', $filters->driverId))
            ->count();

        $terminal = $delivered + $failed + $returned;
        $successRate = $terminal > 0 ? bcdiv((string) ($delivered * 100), (string) $terminal, 2) : '0.00';

        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'id';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        $drivers = Delivery::query()
            ->where(function ($q) use ($start, $end): void {
                $q->where(function ($qq) use ($start, $end): void {
                    $qq->where('status', DeliveryStatus::Delivered)->where('delivered_at', '>=', $start)->where('delivered_at', '<', $end);
                })->orWhere(function ($qq) use ($start, $end): void {
                    $qq->where('status', DeliveryStatus::Failed)->where('failed_at', '>=', $start)->where('failed_at', '<', $end);
                })->orWhere(function ($qq) use ($start, $end): void {
                    $qq->where('status', DeliveryStatus::ReturnedToWarehouse)->where('returned_at', '>=', $start)->where('returned_at', '<', $end);
                });
            })
            ->when($filters->driverId !== null, fn ($q) => $q->where('driver_id', $filters->driverId))
            ->with(['driver:id,name', 'order:id,order_number'])
            ->orderBy($sort, $direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        return [
            'status_counts' => [
                'delivered' => $delivered,
                'failed' => $failed,
                'returned_to_warehouse' => $returned,
                'assigned' => $assigned,
            ],
            'success_rate' => $successRate,
            'drivers' => $drivers,
        ];
    }
}
