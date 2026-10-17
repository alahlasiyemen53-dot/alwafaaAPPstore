<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

final class CheckProductReviewEligibilityAction
{
    /**
     * Determines whether a customer is eligible to review a product
     * based on authoritative order history (must have a delivered order containing this product).
     */
    public function execute(Customer $customer, Product $product): bool
    {
        return OrderItem::query()
            ->whereHas('order', function (Builder $query) use ($customer): void {
                $query->where('customer_id', $customer->id)
                    ->where('status', OrderStatus::Delivered);
            })
            ->whereHas('productVariant', function (Builder $query) use ($product): void {
                $query->where('product_id', $product->id);
            })
            ->exists();
    }
}
