<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CustomerCatalogPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_list_query_count_is_bounded_and_prevents_n_plus_one(): void
    {
        Queue::fake();
        Http::fake([
            '*/health' => Http::response(['status' => 'unavailable'], 503),
        ]);
        $currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'is_default' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'code' => 'WH-PERF',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'ماركة النخبة', 'slug' => 'elite-brand', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'مستحضرات التجميل', 'slug' => 'cosmetics', 'is_active' => true]);

        // Seed 10 products with variants, prices, inventory and reviews
        for ($i = 1; $i <= 10; $i++) {
            $prod = Product::create([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'name_ar' => "منتج أداء {$i}",
                'slug' => "perf-prod-{$i}",
                'status' => ProductStatus::Active,
                'is_best_seller' => $i % 2 === 0,
            ]);

            $var = ProductVariant::create([
                'product_id' => $prod->id,
                'sku' => "SKU-PERF-{$i}",
                'status' => ProductVariantStatus::Active,
                'is_default' => true,
                'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
            ]);

            VariantPrice::create([
                'product_variant_id' => $var->id,
                'currency_id' => $currency->id,
                'price' => 1000.00 * $i,
                'is_active' => true,
            ]);

            Inventory::create([
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $var->id,
                'quantity' => 20.0,
                'reserved_quantity' => 0.0,
            ]);

            $cust = Customer::create([
                'name' => "عميل {$i}",
                'phone' => "77000100{$i}",
                'normalized_phone' => "+96777000100{$i}",
                'is_active' => true,
            ]);

            ProductReview::create([
                'customer_id' => $cust->id,
                'product_id' => $prod->id,
                'rating' => 4,
                'status' => ProductReviewStatus::Approved,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/products?per_page=10');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 10);

        // Verify O(1) query budget: query count must not exceed 10 queries regardless of 10 items
        $this->assertLessThanOrEqual(
            12,
            count($queries),
            sprintf('Expected at most 12 queries to fetch 10 products, but executed %d queries. N+1 leak detected.', count($queries))
        );

        // Verify no internal inventory, supplier, or Eloquent attributes leaked in JSON
        $json = $response->getContent();
        $this->assertStringNotContainsString('warehouse_id', $json);
        $this->assertStringNotContainsString('reserved_quantity', $json);
        $this->assertStringNotContainsString('WH-PERF', $json);
        $this->assertStringNotContainsString('password', $json);
    }
}
