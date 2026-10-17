<?php

namespace App\Queries;

use App\Models\StockTransfer;
use App\Support\DateFilterNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminStockTransferQuery
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'createdBy', 'postedBy', 'items.productVariant']);

        if (! empty($filters['from_warehouse_id'])) {
            $query->where('from_warehouse_id', $filters['from_warehouse_id']);
        }

        if (! empty($filters['to_warehouse_id'])) {
            $query->where('to_warehouse_id', $filters['to_warehouse_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $normFrom = DateFilterNormalizer::normalize((string) $filters['from_date']);
            if ($normFrom) {
                $query->whereDate('created_at', '>=', $normFrom);
            }
        }

        if (! empty($filters['to_date'])) {
            $normTo = DateFilterNormalizer::normalize((string) $filters['to_date']);
            if ($normTo) {
                $query->whereDate('created_at', '<=', $normTo);
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where('transfer_number', 'like', "%{$search}%");
        }

        return $query->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
