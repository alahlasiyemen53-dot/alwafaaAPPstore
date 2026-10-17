<?php

namespace App\Queries;

use App\Models\Attribute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class AdminAttributeQuery
{
    public function paginate(
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = Attribute::query()
            ->withCount('values')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(function ($q) use ($term): void {
                $q->where('name_ar', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active' || $status === '1');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return Collection<int, Attribute>
     */
    public function activeAttributesWithValues(): Collection
    {
        return Attribute::query()
            ->where('is_active', true)
            ->with(['values' => function ($q): void {
                $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
