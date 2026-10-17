<?php

namespace App\Queries;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class AdminSupplierQuery
{
    public function paginate(
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = Supplier::query()
            ->withCount('purchaseInvoices')
            ->orderBy('id', 'desc');

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('contact_person', 'like', "%{$term}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active' || $status === '1');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return Collection<int, Supplier>
     */
    public function activeSuppliers(): Collection
    {
        return Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
