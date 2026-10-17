<?php

namespace Tests\Feature;

use App\Data\AdminProductReviewFilterData;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Queries\AdminProductReviewsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ReviewNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_reviews_query_avoids_n_plus_one_problems(): void
    {
        $category = Category::query()->create([
            'name_ar' => 'عطور',
            'slug' => 'perfumes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $brand = Brand::query()->create([
            'name_ar' => 'براند راقي',
            'slug' => 'luxury-brand',
            'is_active' => true,
        ]);

        $moderator = User::factory()->create(['name' => 'مشرف النظام', 'is_active' => true]);

        // Create 15 customers, products, and reviews
        for ($i = 1; $i <= 15; $i++) {
            $customer = Customer::query()->create([
                'name' => "عميل {$i}",
                'phone' => "7700000{$i}",
                'normalized_phone' => "+9677700000{$i}",
                'is_active' => true,
            ]);

            $product = Product::query()->create([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'name_ar' => "منتج {$i}",
                'slug' => "product-rev-{$i}",
                'status' => ProductStatus::Active,
                'has_variants' => false,
            ]);

            ProductReview::query()->create([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'rating' => ($i % 5) + 1,
                'title' => "مراجعة {$i}",
                'comment' => "تعليق تجربة المنتج {$i}",
                'status' => ProductReviewStatus::Approved,
                'moderator_id' => $moderator->id,
                'moderated_at' => now(),
                'is_verified_purchase' => ($i % 2 === 0),
            ]);
        }

        $query = new AdminProductReviewsQuery;
        $filters = AdminProductReviewFilterData::fromArray(['per_page' => 20]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $items = $query->paginate($filters);

        // Access all relations for each review as the blade template would
        foreach ($items as $item) {
            $this->assertNotNull($item->customer->name);
            $this->assertNotNull($item->product->name_ar);
            $this->assertSame('براند راقي', $item->product->brand->name_ar);
            $this->assertSame('عطور', $item->product->category->name_ar);
            $this->assertSame('مشرف النظام', $item->moderator->name);
        }

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1 count query + 1 reviews query + 1 customers query + 1 products query + 1 brands query + 1 categories query + 1 moderators query = 7 queries
        // Regardless of having 15 items, query count must be bounded and strictly under 10 (not 15 * 5 = 75 queries!)
        $this->assertLessThanOrEqual(10, $queryCount, "Query count {$queryCount} exceeded expected bounded query limit.");
    }
}
