<?php

namespace App\Queries;

use App\Models\CustomerTier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AdminCustomerTierQuery
{
    /** @return LengthAwarePaginator<CustomerTier> */
    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        $query = CustomerTier::query()
            ->withCount(['customers as active_customers_count'])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($search = $request->input('search')) {
            $term = trim($search);
            $query->where(function (Builder $q) use ($term): void {
                $q->where('code', 'like', "%{$term}%")
                    ->orWhere('name_ar', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
