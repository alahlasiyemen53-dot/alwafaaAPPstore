<?php

namespace App\Queries;

use App\Data\AdminWishlistFilterData;
use App\Models\CustomerWishlistItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminWishlistQuery
{
    private const ALLOWED_SORTS = ['created_at', 'id'];

    public function paginate(AdminWishlistFilterData $filters): LengthAwarePaginator
    {
        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'created_at';
        $direction = $filters->direction;

        return CustomerWishlistItem::query()
            ->with([
                'customer:id,name,phone,normalized_phone,is_active',
                'product:id,brand_id,category_id,name_ar,name_en,slug,status',
                'product.brand:id,name_ar',
                'product.category:id,name_ar',
                'product.media',
            ])
            ->when($filters->search, fn (Builder $query, string $term) => $this->applySearch($query, $term))
            ->when($filters->customerId, fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters->productId, fn (Builder $query, int $productId) => $query->where('product_id', $productId))
            ->when($filters->productStatus, function (Builder $query, string $status): void {
                $query->whereHas('product', fn (Builder $pq) => $pq->where('status', $status));
            })
            ->when($filters->fromDate, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters->toDate, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /** @return array{total_items: int, distinct_customers: int, distinct_products: int} */
    public function getMetrics(): array
    {
        return [
            'total_items' => CustomerWishlistItem::query()->count(),
            'distinct_customers' => CustomerWishlistItem::query()->distinct('customer_id')->count('customer_id'),
            'distinct_products' => CustomerWishlistItem::query()->distinct('product_id')->count('product_id'),
        ];
    }

    private function applySearch(Builder $query, string $term): void
    {
        $pattern = '%'.trim($term).'%';

        $query->where(function (Builder $builder) use ($pattern): void {
            $builder->whereHas('customer', function (Builder $cq) use ($pattern): void {
                $cq->where('name', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern)
                    ->orWhere('normalized_phone', 'like', $pattern);
            })->orWhereHas('product', function (Builder $pq) use ($pattern): void {
                $pq->where('name_ar', 'like', $pattern)
                    ->orWhere('name_en', 'like', $pattern);
            });
        });
    }
}
