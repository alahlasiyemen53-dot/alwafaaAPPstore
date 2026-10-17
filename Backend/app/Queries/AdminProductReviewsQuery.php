<?php

namespace App\Queries;

use App\Data\AdminProductReviewFilterData;
use App\Enums\ProductReviewStatus;
use App\Models\ProductReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminProductReviewsQuery
{
    private const ALLOWED_SORTS = ['created_at', 'rating', 'id'];

    public function paginate(AdminProductReviewFilterData $filters): LengthAwarePaginator
    {
        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'created_at';
        $direction = $filters->direction;

        return ProductReview::query()
            ->with([
                'customer:id,name,phone,normalized_phone,is_active',
                'product:id,name_ar,name_en,slug,status,brand_id,category_id',
                'product.brand:id,name_ar',
                'product.category:id,name_ar',
                'product.media',
                'moderator:id,name,email',
            ])
            ->when($filters->search, fn (Builder $query, string $term) => $this->applySearch($query, $term))
            ->when($filters->status, fn (Builder $query, ProductReviewStatus $status) => $query->where('status', $status))
            ->when($filters->rating, fn (Builder $query, int $rating) => $query->where('rating', $rating))
            ->when($filters->verified !== null, fn (Builder $query) => $query->where('is_verified_purchase', $filters->verified))
            ->when($filters->productId, fn (Builder $query, int $productId) => $query->where('product_id', $productId))
            ->when($filters->customerId, fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters->fromDate, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters->toDate, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /**
     * @return array{
     *     total: int,
     *     pending: int,
     *     approved: int,
     *     rejected: int,
     *     hidden: int
     * }
     */
    public function getMetrics(): array
    {
        return [
            'total' => ProductReview::query()->count(),
            'pending' => ProductReview::query()->where('status', ProductReviewStatus::Pending)->count(),
            'approved' => ProductReview::query()->where('status', ProductReviewStatus::Approved)->count(),
            'rejected' => ProductReview::query()->where('status', ProductReviewStatus::Rejected)->count(),
            'hidden' => ProductReview::query()->where('status', ProductReviewStatus::Hidden)->count(),
        ];
    }

    private function applySearch(Builder $query, string $term): void
    {
        $pattern = '%'.trim($term).'%';

        $query->where(function (Builder $builder) use ($pattern): void {
            $builder->where('title', 'like', $pattern)
                ->orWhere('body', 'like', $pattern)
                ->orWhereHas('customer', function (Builder $cq) use ($pattern): void {
                    $cq->where('name', 'like', $pattern)
                        ->orWhere('phone', 'like', $pattern)
                        ->orWhere('normalized_phone', 'like', $pattern);
                })
                ->orWhereHas('product', function (Builder $pq) use ($pattern): void {
                    $pq->where('name_ar', 'like', $pattern)
                        ->orWhere('name_en', 'like', $pattern);
                });
        });
    }
}
