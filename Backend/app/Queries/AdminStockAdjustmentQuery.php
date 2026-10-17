<?php

namespace App\Queries;

use App\Models\StockAdjustment;
use App\Support\DateFilterNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminStockAdjustmentQuery
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StockAdjustment::query()
            ->with(['warehouse', 'createdBy', 'postedBy', 'items.productVariant']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['reason_code'])) {
            $query->where('reason_code', $filters['reason_code']);
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
            $query->where('adjustment_number', 'like', "%{$search}%");
        }

        return $query->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
