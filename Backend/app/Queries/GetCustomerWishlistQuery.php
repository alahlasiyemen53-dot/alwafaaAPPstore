<?php

namespace App\Queries;

use App\Models\Customer;
use App\Models\CustomerWishlistItem;
use Illuminate\Database\Eloquent\Collection;

final class GetCustomerWishlistQuery
{
    /**
     * @return Collection<int, CustomerWishlistItem>
     */
    public function getBounded(Customer $customer, int $limit = 10): Collection
    {
        return CustomerWishlistItem::query()
            ->where('customer_id', $customer->id)
            ->with([
                'product:id,brand_id,category_id,name_ar,name_en,slug,status',
                'product.brand:id,name_ar',
                'product.category:id,name_ar',
                'product.media',
            ])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function count(Customer $customer): int
    {
        return CustomerWishlistItem::query()
            ->where('customer_id', $customer->id)
            ->count();
    }
}
