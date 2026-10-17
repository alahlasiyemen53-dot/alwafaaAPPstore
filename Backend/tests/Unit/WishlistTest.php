<?php

namespace Tests\Unit;

use App\Actions\AddProductToCustomerWishlistAction;
use App\Actions\RemoveProductFromCustomerWishlistAction;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerWishlistItem;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'name' => 'فاطمة محمد',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'العناية بالبشرة',
            'slug' => 'skincare',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name_ar' => 'سيرافي',
            'slug' => 'cerave',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'غسول مرطب',
            'slug' => 'hydrating-cleanser',
            'status' => 'active',
            'has_variants' => false,
        ]);
    }

    public function test_customer_and_product_relationships_with_wishlist(): void
    {
        $item = CustomerWishlistItem::create([
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);

        $this->assertTrue($this->customer->wishlistItems->contains($item));
        $this->assertTrue($this->product->wishlistItems->contains($item));
        $this->assertEquals($this->customer->id, $item->customer->id);
        $this->assertEquals($this->product->id, $item->product->id);
    }

    public function test_db_unique_constraint_prevents_duplicate_entries(): void
    {
        CustomerWishlistItem::create([
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);

        $this->expectException(QueryException::class);

        CustomerWishlistItem::create([
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_add_action_is_idempotent(): void
    {
        $action = new AddProductToCustomerWishlistAction;

        $first = $action->execute($this->customer, $this->product);
        $second = $action->execute($this->customer, $this->product);

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('customer_wishlist_items', 1);
    }

    public function test_remove_action_respects_ownership(): void
    {
        $otherCustomer = Customer::create([
            'name' => 'سارة علي',
            'phone' => '779876543',
            'normalized_phone' => '+967779876543',
        ]);

        $action = new AddProductToCustomerWishlistAction;
        $action->execute($this->customer, $this->product);

        $removeAction = new RemoveProductFromCustomerWishlistAction;

        // Trying to remove as otherCustomer should NOT remove this customer's item
        $removed = $removeAction->execute($otherCustomer, $this->product);
        $this->assertFalse($removed);
        $this->assertDatabaseHas('customer_wishlist_items', [
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);

        // Removing with proper ownership works
        $removed = $removeAction->execute($this->customer, $this->product);
        $this->assertTrue($removed);
        $this->assertDatabaseMissing('customer_wishlist_items', [
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_deleting_customer_cascades_to_wishlist(): void
    {
        $action = new AddProductToCustomerWishlistAction;
        $action->execute($this->customer, $this->product);

        $this->customer->delete();

        $this->assertDatabaseCount('customer_wishlist_items', 0);
    }

    public function test_deleting_product_cascades_to_wishlist(): void
    {
        $action = new AddProductToCustomerWishlistAction;
        $action->execute($this->customer, $this->product);

        $this->product->delete();

        $this->assertDatabaseCount('customer_wishlist_items', 0);
    }
}
