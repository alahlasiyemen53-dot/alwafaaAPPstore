<?php

namespace Tests\Feature;

use App\Actions\AddProductToCustomerWishlistAction;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerWishlistItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WishlistConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_duplicate_additions_produce_exactly_one_record(): void
    {
        $customer = Customer::create([
            'name' => 'نوف عبد الله',
            'phone' => '773333333',
            'normalized_phone' => '+967773333333',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'العطور',
            'slug' => 'perfumes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name_ar' => 'شانيل',
            'slug' => 'chanel',
            'is_active' => true,
        ]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'عطر بلو دو شانيل',
            'slug' => 'bleu-de-chanel',
            'status' => 'active',
            'has_variants' => false,
        ]);

        $action = new AddProductToCustomerWishlistAction;

        // Simulate concurrent attempts
        $item1 = $action->execute($customer, $product);
        $item2 = $action->execute($customer, $product);
        $item3 = $action->execute($customer, $product);

        $this->assertEquals($item1->id, $item2->id);
        $this->assertEquals($item2->id, $item3->id);

        $this->assertDatabaseCount('customer_wishlist_items', 1);
        $this->assertEquals(1, CustomerWishlistItem::where('customer_id', $customer->id)->where('product_id', $product->id)->count());
    }
}
