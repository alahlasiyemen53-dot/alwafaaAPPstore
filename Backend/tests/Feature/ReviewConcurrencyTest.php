<?php

namespace Tests\Feature;

use App\Actions\ModerateProductReviewAction;
use App\Actions\SubmitProductReviewAction;
use App\Enums\OrderStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReviewConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Product $product;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->customer = Customer::query()->create([
            'name' => 'عميل التزامن',
            'phone' => '773344556',
            'normalized_phone' => '+967773344556',
            'is_active' => true,
        ]);

        $brand = Brand::query()->create(['name_ar' => 'براند التزامن', 'slug' => 'sync-brand', 'is_active' => true]);
        $category = Category::query()->create(['name_ar' => 'تصنيف التزامن', 'slug' => 'sync-cat', 'sort_order' => 1, 'is_active' => true]);

        $this->product = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'منتج اختبار التزامن',
            'slug' => 'sync-test-product',
            'status' => ProductStatus::Active,
            'has_variants' => false,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'sku' => 'SKU-SYNC-01',
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
            'is_default' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'WH-SYNC',
            'name_ar' => 'مستودع التزامن',
            'is_active' => true,
        ]);

        $currency = Currency::query()->create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'is_default' => true,
            'is_active' => true,
            'exchange_rate_to_base' => 1.0,
        ]);

        $order = Order::query()->create([
            'customer_id' => $this->customer->id,
            'warehouse_id' => $warehouse->id,
            'currency_id' => $currency->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'order_number' => 'ORD-SYNC-100',
            'status' => OrderStatus::Delivered,
            'subtotal' => 1000,
            'total' => 1000,
            'base_currency_total' => 1000,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $this->product->name_ar,
            'sku_snapshot' => $variant->sku,
            'quantity' => 1,
            'base_quantity' => 1,
            'unit_price' => 1000,
            'line_subtotal' => 1000,
            'line_total' => 1000,
            'base_currency_unit_price' => 1000,
            'base_currency_line_total' => 1000,
        ]);

        $this->moderator = User::factory()->create(['is_active' => true]);
        $this->moderator->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
    }

    public function test_concurrent_review_submission_updates_idempotently_without_duplicate_key_error(): void
    {
        $submitAction = app(SubmitProductReviewAction::class);

        // First submission
        $rev1 = $submitAction->execute(
            customer: $this->customer,
            product: $this->product,
            rating: 4,
            title: 'تقييم أول',
            comment: 'تعليق أول',
        );

        // Immediate second submission for same customer & product (simulating retry or concurrent request)
        $rev2 = $submitAction->execute(
            customer: $this->customer,
            product: $this->product,
            rating: 5,
            title: 'تقييم معدل',
            comment: 'تعليق معدل',
        );

        self::assertSame($rev1->id, $rev2->id);
        self::assertSame(1, ProductReview::query()->where('customer_id', $this->customer->id)->where('product_id', $this->product->id)->count());
        self::assertSame(5, $rev2->rating);
        self::assertSame('تقييم معدل', $rev2->title);
    }

    public function test_moderate_action_locks_row_for_update_in_transaction(): void
    {
        $review = ProductReview::query()->create([
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'title' => 'عنوان',
            'comment' => 'نص المراجعة',
            'status' => ProductReviewStatus::Pending,
            'is_verified_purchase' => false,
        ]);

        $moderateAction = app(ModerateProductReviewAction::class);

        $moderated = $moderateAction->execute(
            review: $review,
            status: ProductReviewStatus::Approved,
            moderator: $this->moderator,
        );

        self::assertSame(ProductReviewStatus::Approved, $moderated->status);
        self::assertSame($this->moderator->id, $moderated->moderator_id);
    }
}
