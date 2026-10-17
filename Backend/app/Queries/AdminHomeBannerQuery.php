<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\HomeBanner;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AdminHomeBannerQuery
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return HomeBanner::query()
            ->with(['media'])
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                $term = '%'.trim($filters['search']).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('title_ar', 'like', $term)
                    ->orWhere('title_en', 'like', $term)
                    ->orWhere('subtitle_ar', 'like', $term)
                    ->orWhere('subtitle_en', 'like', $term));
            })
            ->when(isset($filters['status']) && $filters['status'] !== 'all' && $filters['status'] !== '', function (Builder $query) use ($filters): void {
                if ($filters['status'] === 'active') {
                    $query->where('is_active', true);
                } elseif ($filters['status'] === 'inactive') {
                    $query->where('is_active', false);
                } elseif ($filters['status'] === 'scheduled_now') {
                    $now = Carbon::now();
                    $query->where('is_active', true)
                        ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                        ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
                }
            })
            ->when(! empty($filters['action_type']) && $filters['action_type'] !== 'all', function (Builder $query) use ($filters): void {
                $query->where('action_type', $filters['action_type']);
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function statistics(): array
    {
        $now = Carbon::now();

        return [
            'total' => HomeBanner::query()->count(),
            'active' => HomeBanner::query()->where('is_active', true)->count(),
            'scheduled_now' => HomeBanner::query()
                ->where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->count(),
        ];
    }

    public function activeProducts(): Collection
    {
        return Product::query()
            ->where('status', \App\Enums\ProductStatus::Active)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'slug']);
    }

    public function activeCategories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'slug']);
    }

    public function activePromotions(): Collection
    {
        return Promotion::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}
