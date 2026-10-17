<?php

namespace Tests\Feature;

use App\Data\AdminWishlistFilterData;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerWishlistItem;
use App\Models\Product;
use App\Queries\AdminWishlistQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WishlistNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_wishlist_query_avoids_n_plus_one_problems(): void
    {
        $category = Category::create([
            'name_ar' => 'مكياج',
            'slug' => 'makeup',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name_ar' => 'هدى بيوتي',
            'slug' => 'huda-beauty',
            'is_active' => true,
        ]);

        // Create 15 customers, products, and wishlist items
        for ($i = 1; $i <= 15; $i++) {
            $customer = Customer::create([
                'name' => "عميل {$i}",
                'phone' => "7700000{$i}",
                'normalized_phone' => "+9677700000{$i}",
                'is_active' => true,
            ]);

            $product = Product::create([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'name_ar' => "منتج {$i}",
                'slug' => "product-{$i}",
                'status' => 'active',
                'has_variants' => false,
            ]);

            CustomerWishlistItem::create([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
            ]);
        }

        $query = new AdminWishlistQuery;
        $filters = AdminWishlistFilterData::fromValidated(['per_page' => 20]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $items = $query->paginate($filters);

        // Access all relations for each item as the blade template would
        foreach ($items as $item) {
            $this->assertNotNull($item->customer->name);
            $this->assertNotNull($item->product->name_ar);
            $this->assertEquals('هدى بيوتي', $item->product->brand->name_ar);
            $this->assertEquals('مكياج', $item->product->category->name_ar);
            $item->product->media;
        }

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1 count query + 1 wishlist items query + 1 customers query + 1 products query + 1 brands query + 1 categories query + 1 media query = 7 queries total
        // Regardless of having 15 items, query count must be bounded and strictly under 10 (not 15 * 5 = 75 queries!)
        $this->assertLessThanOrEqual(10, $queryCount, "Query count {$queryCount} exceeded expected bounded query limit.");
    }
}
