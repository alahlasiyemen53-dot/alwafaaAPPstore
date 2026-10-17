<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\PurchaseInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Posted Purchase Invoice report. Supplier ledger/GL reconciliation
 * remains an independent authoritative source; this report never invents
 * a mutable supplier balance or a separate payment history.
 */
final class PurchasingReportQuery
{
    private const ALLOWED_SORTS = ['invoice_date', 'invoice_number', 'total', 'id'];

    /**
     * @return array{
     *     totals_by_currency: array<int, array{currency_id: int, currency_code: string, invoices_count: int, total_amount: string}>,
     *     suppliers: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $base = $this->baseQuery($filters);

        $totalsRows = (clone $base)
            ->toBase()->join('currencies', 'currencies.id', '=', 'purchase_invoices.currency_id')
            ->groupBy('purchase_invoices.currency_id', 'currencies.code')
            ->selectRaw('purchase_invoices.currency_id, currencies.code as currency_code, COUNT(purchase_invoices.id) as invoices_count, SUM(purchase_invoices.total) as total_amount')
            ->get();

        $totalsByCurrency = $totalsRows->map(fn ($row): array => [
            'currency_id' => (int) $row->currency_id,
            'currency_code' => (string) $row->currency_code,
            'invoices_count' => (int) $row->invoices_count,
            'total_amount' => (string) $row->total_amount,
        ])->all();

        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'invoice_date';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        $suppliers = (clone $base)
            ->with(['supplier:id,name', 'warehouse:id,name_ar', 'currency:id,code'])
            ->orderBy($sort, $direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        return [
            'totals_by_currency' => $totalsByCurrency,
            'suppliers' => $suppliers,
        ];
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    public function executeForExport(ReportFilterData $filters): \Generator
    {
        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'invoice_date';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        foreach ($this->baseQuery($filters)->with(['supplier:id,name', 'warehouse:id,name_ar', 'currency:id,code'])->orderBy($sort, $direction)->lazyById(500, 'purchase_invoices.id') as $invoice) {
            yield [
                $invoice->invoice_number,
                $invoice->supplier->name ?? '',
                $invoice->warehouse->name_ar ?? '',
                $invoice->currency->code,
                (string) $invoice->total,
                $invoice->invoice_date->toDateString(),
            ];
        }
    }

    /** @return Builder<PurchaseInvoice> */
    private function baseQuery(ReportFilterData $filters): Builder
    {
        $base = PurchaseInvoice::query()
            ->where('status', PurchaseInvoiceStatus::Posted)
            ->where('invoice_date', '>=', $filters->range->startAt->toDateString())
            ->where('invoice_date', '<', $filters->range->endAt->toDateString());

        if ($filters->currencyId !== null) {
            $base->where('currency_id', $filters->currencyId);
        }
        if ($filters->supplierId !== null) {
            $base->where('supplier_id', $filters->supplierId);
        }
        if ($filters->warehouseId !== null) {
            $base->where('warehouse_id', $filters->warehouseId);
        }

        return $base;
    }
}
