<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Models\Driver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Illuminate\Support\Facades\DB;

/**
 * Driver COD outstanding report, evaluated as of the filter's endAt:
 * Outstanding = max(0, collected through endAt - settlement allocations
 * effective at endAt). A reversal recorded after endAt does not change
 * the earlier balance. No mutable driver-balance column is introduced.
 */
final class DriverCodReportQuery
{
    /**
     * @return array{
     *     totals_by_currency: array<int, array{currency_id: int, currency_code: string, collected: string, settled: string, outstanding: string}>,
     *     drivers: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $endAt = $filters->range->endAt;

        $collected = DB::table('deliveries')
            ->select('driver_id', 'cod_currency_id as currency_id', DB::raw('SUM(cod_collected_amount) as collected'))
            ->where('status', DeliveryStatus::Delivered->value)
            ->where('cod_collected_amount', '>', 0)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<', $endAt)
            ->groupBy('driver_id', 'cod_currency_id');

        $allocated = DB::table('driver_cod_settlement_items')
            ->join('driver_cod_settlements', 'driver_cod_settlements.id', '=', 'driver_cod_settlement_items.settlement_id')
            ->select('driver_cod_settlements.driver_id', 'driver_cod_settlement_items.currency_id', DB::raw('SUM(driver_cod_settlement_items.settlement_amount) as settled'))
            ->where('driver_cod_settlements.status', DriverCodSettlementStatus::Posted->value)
            ->whereNotNull('driver_cod_settlements.posted_at')
            ->where('driver_cod_settlements.posted_at', '<', $endAt)
            ->where(function ($q) use ($endAt): void {
                $q->whereNull('driver_cod_settlements.reversed_at')
                    ->orWhere('driver_cod_settlements.reversed_at', '>=', $endAt);
            })
            ->groupBy('driver_cod_settlements.driver_id', 'driver_cod_settlement_items.currency_id');

        $balances = DB::query()
            ->fromSub($collected, 'c')
            ->leftJoinSub($allocated, 'a', function ($join): void {
                $join->on('a.driver_id', '=', 'c.driver_id')->on('a.currency_id', '=', 'c.currency_id');
            })
            ->select('c.driver_id', 'c.currency_id', 'c.collected', DB::raw('COALESCE(a.settled, 0) as settled'))
            ->when($filters->driverId !== null, fn ($q) => $q->where('c.driver_id', $filters->driverId))
            ->when($filters->currencyId !== null, fn ($q) => $q->where('c.currency_id', $filters->currencyId))
            ->get();

        $totalsByCurrencyRaw = [];
        $driverBalances = [];
        foreach ($balances as $row) {
            $outstanding = max(0.0, (float) $row->collected - (float) $row->settled);

            $currencyId = (int) $row->currency_id;
            $totalsByCurrencyRaw[$currencyId]['collected'] = ($totalsByCurrencyRaw[$currencyId]['collected'] ?? 0) + (float) $row->collected;
            $totalsByCurrencyRaw[$currencyId]['settled'] = ($totalsByCurrencyRaw[$currencyId]['settled'] ?? 0) + (float) $row->settled;
            $totalsByCurrencyRaw[$currencyId]['outstanding'] = ($totalsByCurrencyRaw[$currencyId]['outstanding'] ?? 0) + $outstanding;

            $driverBalances[] = [
                'driver_id' => (int) $row->driver_id,
                'currency_id' => $currencyId,
                'collected' => number_format((float) $row->collected, 4, '.', ''),
                'settled' => number_format((float) $row->settled, 4, '.', ''),
                'outstanding' => number_format($outstanding, 4, '.', ''),
            ];
        }

        $currencyCodes = DB::table('currencies')->pluck('code', 'id');

        $totalsByCurrency = [];
        foreach ($totalsByCurrencyRaw as $currencyId => $totals) {
            $totalsByCurrency[] = [
                'currency_id' => $currencyId,
                'currency_code' => (string) ($currencyCodes[$currencyId] ?? ''),
                'collected' => number_format($totals['collected'], 4, '.', ''),
                'settled' => number_format($totals['settled'], 4, '.', ''),
                'outstanding' => number_format($totals['outstanding'], 4, '.', ''),
            ];
        }

        $driverNames = Driver::query()->pluck('name', 'id');
        foreach ($driverBalances as &$row) {
            $row['driver_name'] = (string) ($driverNames[$row['driver_id']] ?? '');
            $row['currency_code'] = (string) ($currencyCodes[$row['currency_id']] ?? '');
        }
        unset($row);

        $sort = $filters->sort;
        $direction = $filters->direction === 'asc' ? 1 : -1;
        usort($driverBalances, function (array $a, array $b) use ($sort, $direction): int {
            $valueA = match ($sort) {
                'driver_name' => $a['driver_name'],
                'outstanding' => (float) $a['outstanding'],
                default => $a['driver_id'],
            };
            $valueB = match ($sort) {
                'driver_name' => $b['driver_name'],
                'outstanding' => (float) $b['outstanding'],
                default => $b['driver_id'],
            };

            return $direction * ($valueA <=> $valueB);
        });

        $perPage = $filters->perPage;
        $currentPage = (int) request()->integer('page', 1);
        $slice = array_slice($driverBalances, ($currentPage - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginatorImpl(
            $slice,
            count($driverBalances),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        return [
            'totals_by_currency' => $totalsByCurrency,
            'drivers' => $paginator,
        ];
    }
}
