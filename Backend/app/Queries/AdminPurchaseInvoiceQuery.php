<?php

namespace App\Queries;

use App\Models\Currency;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class AdminPurchaseInvoiceQuery
{
    public function paginate(
        int $perPage = 20,
        ?string $search = null,
        ?int $supplierId = null,
        ?int $warehouseId = null,
        ?int $currencyId = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): LengthAwarePaginator {
        $query = PurchaseInvoice::query()
            ->with(['supplier', 'warehouse', 'currency', 'poster', 'creator'])
            ->withCount('items')
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc');

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(function ($q) use ($term): void {
                $q->where('invoice_number', 'like', "%{$term}%")
                    ->orWhere('supplier_invoice_number', 'like', "%{$term}%")
                    ->orWhereHas('supplier', function ($sq) use ($term): void {
                        $sq->where('name', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%");
                    });
            });
        }

        if ($supplierId !== null && $supplierId > 0) {
            $query->where('supplier_id', $supplierId);
        }

        if ($warehouseId !== null && $warehouseId > 0) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($currencyId !== null && $currencyId > 0) {
            $query->where('currency_id', $currencyId);
        }

        if ($status !== null && trim($status) !== '') {
            $query->where('status', $status);
        }

        if ($dateFrom !== null && trim($dateFrom) !== '') {
            $parsedDateFrom = $this->parseDate($dateFrom);
            if ($parsedDateFrom !== null) {
                $query->where('invoice_date', '>=', $parsedDateFrom);
            }
        }

        if ($dateTo !== null && trim($dateTo) !== '') {
            $parsedDateTo = $this->parseDate($dateTo);
            if ($parsedDateTo !== null) {
                $query->where('invoice_date', '<=', $parsedDateTo);
            }
        }

        return $query->paginate($perPage)->withQueryString();
    }

    private function parseDate(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $trimmed = trim($date);

        try {
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $trimmed)) {
                return Carbon::createFromFormat('d-m-Y', $trimmed)->format('Y-m-d');
            }
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed)) {
                return Carbon::createFromFormat('d/m/Y', $trimmed)->format('Y-m-d');
            }

            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, Supplier>
     */
    public function activeSuppliers(): Collection
    {
        return Supplier::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Warehouse>
     */
    public function activeWarehouses(): Collection
    {
        return Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();
    }

    /**
     * @return Collection<int, Currency>
     */
    public function activeCurrencies(): Collection
    {
        return Currency::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('code')->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    public function activeUnits(): Collection
    {
        return Unit::query()->where('is_active', true)->orderBy('name_ar')->get();
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function purchasableVariants(): Collection
    {
        return ProductVariant::query()
            ->with([
                'product',
                'attributeValues.attribute',
            ])
            ->where('status', 'active')
            ->orderBy('sku')
            ->get();
    }
}
