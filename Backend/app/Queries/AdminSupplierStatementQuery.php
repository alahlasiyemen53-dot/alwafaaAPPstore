<?php

namespace App\Queries;

use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Support\DateFilterNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminSupplierStatementQuery
{
    public function paginate(
        Supplier $supplier,
        ?int $currencyId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = SupplierLedgerEntry::query()
            ->where('supplier_id', $supplier->id)
            ->with(['currency', 'createdBy'])
            ->orderBy('posted_at', 'desc')
            ->orderBy('id', 'desc');

        if ($currencyId !== null && $currencyId > 0) {
            $query->where('currency_id', $currencyId);
        }

        $normalizedFrom = DateFilterNormalizer::normalize($dateFrom);
        if ($normalizedFrom !== null) {
            $query->where('posted_at', '>=', $normalizedFrom.' 00:00:00');
        }

        $normalizedTo = DateFilterNormalizer::normalize($dateTo);
        if ($normalizedTo !== null) {
            $query->where('posted_at', '<=', $normalizedTo.' 23:59:59');
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
