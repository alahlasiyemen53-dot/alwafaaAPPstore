<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\CustomerRecentlyViewedProduct;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RecordProductViewAction
{
    public function execute(Customer $customer, Product $product): void
    {
        if ($product->status !== ProductStatus::Active) {
            return;
        }

        $now = Carbon::now();

        DB::transaction(function () use ($customer, $product, $now): void {
            CustomerRecentlyViewedProduct::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'product_id' => $product->id,
                ],
                ['last_viewed_at' => $now],
            );

            $this->prune($customer);
        });
    }

    private function prune(Customer $customer): void
    {
        $limit = max(1, (int) config('customer_experience.recently_viewed_limit', 20));

        $keepIds = CustomerRecentlyViewedProduct::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('last_viewed_at')
            ->limit($limit)
            ->pluck('id');

        CustomerRecentlyViewedProduct::query()
            ->where('customer_id', $customer->id)
            ->when($keepIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $keepIds))
            ->when($keepIds->isEmpty(), fn ($q) => $q)
            ->delete();
    }
}
