<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\CustomerRecentlyViewedProduct;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MergeGuestRecentlyViewedAction
{
    /**
     * @param  list<int>  $productIds  Most-recent-first guest product IDs.
     */
    public function execute(Customer $customer, array $productIds): void
    {
        $limit = max(1, (int) config('customer_experience.recently_viewed_limit', 20));
        $uniqueIds = array_values(array_unique(array_filter(
            array_map('intval', $productIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($uniqueIds === []) {
            return;
        }

        $validIds = Product::query()
            ->whereIn('id', $uniqueIds)
            ->where('status', ProductStatus::Active)
            ->pluck('id')
            ->all();

        $orderedValid = [];
        foreach ($uniqueIds as $id) {
            if (in_array($id, $validIds, true)) {
                $orderedValid[] = $id;
            }
            if (count($orderedValid) >= $limit) {
                break;
            }
        }

        if ($orderedValid === []) {
            return;
        }

        DB::transaction(function () use ($customer, $orderedValid, $limit): void {
            $base = Carbon::now();
            foreach (array_reverse($orderedValid) as $offset => $productId) {
                CustomerRecentlyViewedProduct::query()->updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'product_id' => $productId,
                    ],
                    ['last_viewed_at' => $base->copy()->addSeconds($offset)],
                );
            }

            $keepIds = CustomerRecentlyViewedProduct::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('last_viewed_at')
                ->limit($limit)
                ->pluck('id');

            CustomerRecentlyViewedProduct::query()
                ->where('customer_id', $customer->id)
                ->whereNotIn('id', $keepIds)
                ->delete();
        });
    }
}
