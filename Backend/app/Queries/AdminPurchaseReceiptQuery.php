<?php

namespace App\Queries;

use App\Models\PurchaseReceipt;
use App\Support\DateFilterNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminPurchaseReceiptQuery
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseReceipt::query()
            ->with(['warehouse', 'purchaseInvoice.supplier', 'receivedBy', 'createdBy']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $normalizedFrom = DateFilterNormalizer::normalize((string) $filters['from_date']);
            if ($normalizedFrom !== null) {
                $query->whereDate('created_at', '>=', $normalizedFrom);
            }
        }

        if (! empty($filters['to_date'])) {
            $normalizedTo = DateFilterNormalizer::normalize((string) $filters['to_date']);
            if ($normalizedTo !== null) {
                $query->whereDate('created_at', '<=', $normalizedTo);
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseInvoice', function ($iq) use ($search) {
                        $iq->where('invoice_number', 'like', "%{$search}%")
                            ->orWhereHas('supplier', function ($sq) use ($search) {
                                $sq->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        return $query->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
