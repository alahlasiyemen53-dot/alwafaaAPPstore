<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\CustomerWishlistItem;
use App\Models\Product;
use Illuminate\Database\QueryException;

final class AddProductToCustomerWishlistAction
{
    public function execute(Customer $customer, Product $product): CustomerWishlistItem
    {
        try {
            return CustomerWishlistItem::firstOrCreate([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
            ]);
        } catch (QueryException $e) {
            // Concurrent insert race condition handled safely via unique DB constraint
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                return CustomerWishlistItem::query()
                    ->where('customer_id', $customer->id)
                    ->where('product_id', $product->id)
                    ->firstOrFail();
            }

            throw $e;
        }
    }
}
