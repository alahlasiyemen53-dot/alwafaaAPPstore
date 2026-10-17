<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Enums\SalesReturnStatus;
use App\Models\SalesReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Posted Sales Return report using historical monetary snapshots.
 * Physical return facts remain independent of refund/payment facts.
 */
final class SalesReturnReportQuery
{
    private const ALLOWED_SORTS = ['posted_at', 'return_number', 'total', 'id'];

    /**
     * @return array{
     *     summary: array<int, array{currency_id: int, currency_code: string, returns_count: int, total_returned: string}>,
     *     rows: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $base = $this->baseQuery($filters);

        $summaryRows = (clone $base)
            ->toBase()->join('currencies', 'currencies.id', '=', 'sales_returns.currency_id')
            ->groupBy('sales_returns.currency_id', 'currencies.code')
            ->selectRaw('sales_returns.currency_id, currencies.code as currency_code, COUNT(sales_returns.id) as returns_count, SUM(sales_returns.total) as total_returned')
            ->get();

        $summary = $summaryRows->map(fn ($row): array => [
            'currency_id' => (int) $row->currency_id,
            'currency_code' => (string) $row->currency_code,
            'returns_count' => (int) $row->returns_count,
            'total_returned' => (string) $row->total_returned,
        ])->all();

        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'posted_at';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        $rows = (clone $base)
            ->with(['order:id,order_number', 'warehouse:id,name_ar', 'currency:id,code'])
            ->orderBy($sort, $direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    public function executeForExport(ReportFilterData $filters): \Generator
    {
        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'posted_at';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        foreach ($this->baseQuery($filters)->with(['order:id,order_number', 'warehouse:id,name_ar', 'currency:id,code'])->orderBy($sort, $direction)->lazyById(500, 'sales_returns.id') as $return) {
            yield [
                $return->return_number,
                $return->order->order_number ?? '',
                $return->warehouse->name_ar ?? '',
                $return->currency->code,
                (string) $return->total,
                $return->posted_at?->toDateTimeString() ?? '',
            ];
        }
    }

    /** @return Builder<SalesReturn> */
    private function baseQuery(ReportFilterData $filters): Builder
    {
        $base = SalesReturn::query()
            ->where('status', SalesReturnStatus::Posted)
            ->whereNotNull('posted_at')
            ->where('posted_at', '>=', $filters->range->startAt)
            ->where('posted_at', '<', $filters->range->endAt);

        if ($filters->currencyId !== null) {
            $base->where('currency_id', $filters->currencyId);
        }
        if ($filters->warehouseId !== null) {
            $base->where('warehouse_id', $filters->warehouseId);
        }

        return $base;
    }
}
