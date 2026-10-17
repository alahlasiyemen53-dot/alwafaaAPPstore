<?php

namespace App\Queries;

use App\Data\ProductListData;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AdminProductQuery
{
    private const SORTS = ['name_ar', 'name_en', 'status', 'created_at'];

    public function paginate(ProductListData $filters): LengthAwarePaginator
    {
        $sort = in_array($filters->sort, self::SORTS, true) ? $filters->sort : 'created_at';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        return Product::query()
            ->with(['brand:id,name_ar', 'category:id,name_ar', 'media'])
            ->when($filters->search, fn (Builder $query, string $term) => $this->search($query, $term))
            ->when($filters->brandId, fn (Builder $query, int $brandId) => $query->where('brand_id', $brandId))
            ->when($filters->categoryId, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters->status, fn (Builder $query) => $query->where('status', $filters->status))
            ->when($filters->isBestSeller !== null, fn (Builder $query) => $query->where('is_best_seller', $filters->isBestSeller))
            ->when($filters->isNew !== null, fn (Builder $query) => $query->where('is_new', $filters->isNew))
            ->when($filters->isExclusive !== null, fn (Builder $query) => $query->where('is_exclusive', $filters->isExclusive))
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString();
    }

    public function activeBrands(): Collection
    {
        return Brand::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']);
    }

    public function activeCategoryOptions(): array
    {
        $categories = Category::query()->orderBy('sort_order')->orderBy('name_ar')
            ->get(['id', 'parent_id', 'name_ar', 'is_active']);
        $categoriesById = $categories->keyBy('id');

        return $categories->where('is_active', true)->mapWithKeys(
            fn (Category $category) => [$category->id => $this->categoryPath($category, $categoriesById)]
        )->all();
    }

    private function search(Builder $query, string $term): void
    {
        $pattern = '%'.trim($term).'%';
        $query->where(fn (Builder $names) => $names
            ->where('name_ar', 'like', $pattern)
            ->orWhere('name_en', 'like', $pattern)
            ->orWhere('slug', 'like', $pattern)
            ->orWhere('supplier_item_number', 'like', $pattern)
            ->orWhereHas('variants', fn (Builder $variants) => $variants
                ->where('sku', 'like', $pattern)
                ->orWhere('supplier_item_number', 'like', $pattern)));
    }

    private function categoryPath(Category $category, Collection $categoriesById): string
    {
        $names = [$category->name_ar];
        while ($category->parent_id !== null && ($category = $categoriesById->get($category->parent_id))) {
            array_unshift($names, $category->name_ar);
        }

        return implode(' ← ', $names);
    }
}
