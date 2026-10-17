<?php

namespace App\Queries;

use App\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminInventoryQuery
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Inventory::query()
            ->with(['warehouse', 'productVariant.product']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['stock_status'])) {
            if ($filters['stock_status'] === 'in_stock') {
                $query->where('quantity', '>', 0);
            } elseif ($filters['stock_status'] === 'out_of_stock') {
                $query->where('quantity', '<=', 0);
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('productVariant', function ($vq) use ($search) {
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

        return $query->orderBy('warehouse_id')
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
