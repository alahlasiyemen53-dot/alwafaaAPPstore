<?php

namespace App\Queries;

use App\Models\StockMovement;
use App\Support\DateFilterNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminStockMovementQuery
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StockMovement::query()
            ->with(['warehouse', 'productVariant.product', 'createdBy', 'reference']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (! empty($filters['from_date'])) {
            $normFrom = DateFilterNormalizer::normalize((string) $filters['from_date']);
            if ($normFrom) {
                $query->whereDate('occurred_at', '>=', $normFrom);
            }
        }

        if (! empty($filters['to_date'])) {
            $normTo = DateFilterNormalizer::normalize((string) $filters['to_date']);
            if ($normTo) {
                $query->whereDate('occurred_at', '<=', $normTo);
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhere('reason_code', 'like', "%{$search}%")
                    ->orWhereHas('productVariant', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%")
                            ->orWhere('supplier_item_number', 'like', "%{$search}%")
                            ->orWhereHas('product', function ($pq) use ($search) {
                                $pq->where('name_ar', 'like', "%{$search}%")
                                    ->orWhere('name_en', 'like', "%{$search}%");
                            });
                    });
            });
        }

        return $query->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
