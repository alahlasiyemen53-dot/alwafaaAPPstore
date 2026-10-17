<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\CustomerWishlistItem;
use App\Models\Product;

final class RemoveProductFromCustomerWishlistAction
{
    public function execute(Customer $customer, Product|int $product): bool
    {
        $productId = $product instanceof Product ? $product->id : $product;

        $deletedCount = CustomerWishlistItem::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->delete();

        return $deletedCount > 0;
    }
}
