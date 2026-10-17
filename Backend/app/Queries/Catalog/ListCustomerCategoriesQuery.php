<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final class ListCustomerCategoriesQuery
{
    /**
     * Get active root categories with their active subcategories.
     *
     * @return Collection<int, Category>
     */
    public function getTree(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with([
                'media',
                'children' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name_ar')
                    ->with('media'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->get();
    }

    /**
     * Find an active category by ID or Slug with active children.
     */
    public function findActive(int|string $idOrSlug): ?Category
    {
        $query = Category::query()->where('is_active', true);

        if (is_numeric($idOrSlug)) {
            $query->where('id', (int) $idOrSlug);
        } else {
            $query->where('slug', $idOrSlug);
        }

        return $query->with([
            'media',
            'children' => fn ($subQuery) => $subQuery
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name_ar')
                ->with('media'),
            'parent' => fn ($parentQuery) => $parentQuery->where('is_active', true),
        ])->first();
    }
}
