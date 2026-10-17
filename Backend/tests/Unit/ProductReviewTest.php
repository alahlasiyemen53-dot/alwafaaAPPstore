<?php

namespace Tests\Unit;

use App\Actions\CheckProductReviewEligibilityAction;
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
use App\Models\Warehouse;
use App\Queries\GetProductRatingSummaryQuery;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->customer = Customer::query()->create([
            'name' => 'عميل اختبار',
            'phone' => '770000001',
            'normalized_phone' => '+967770000001',
            'is_active' => true,
        ]);

        $brand = Brand::query()->create(['name_ar' => 'ماركة اختبار', 'slug' => 'test-brand', 'is_active' => true]);
        $category = Category::query()->create(['name_ar' => 'تصنيف اختبار', 'slug' => 'test-cat', 'sort_order' => 1, 'is_active' => true]);

        $this->product = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'منتج مراجعة',
            'slug' => 'review-product',
            'status' => ProductStatus::Active,
            'has_variants' => false,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'sku' => 'SKU-REV-01',
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'code' => 'WH-REV',
            'name_ar' => 'مستودع المراجعات',
            'is_active' => true,
        ]);

        $this->currency = Currency::query()->create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'is_default' => true,
            'is_active' => true,
            'exchange_rate_to_base' => 1.0,
        ]);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $action = app(SubmitProductReviewAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->execute($this->customer, $this->product, 0);
    }

    public function test_rating_greater_than_five_throws_exception(): void
    {
        $action = app(SubmitProductReviewAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->execute($this->customer, $this->product, 6);
    }

    public function test_review_status_state_machine_transitions(): void
    {
        self::assertTrue(ProductReviewStatus::Pending->canTransitionTo(ProductReviewStatus::Approved));
        self::assertTrue(ProductReviewStatus::Pending->canTransitionTo(ProductReviewStatus::Rejected));
        self::assertFalse(ProductReviewStatus::Pending->canTransitionTo(ProductReviewStatus::Hidden));
        self::assertFalse(ProductReviewStatus::Pending->canTransitionTo(ProductReviewStatus::Pending));

        self::assertTrue(ProductReviewStatus::Approved->canTransitionTo(ProductReviewStatus::Hidden));
        self::assertFalse(ProductReviewStatus::Approved->canTransitionTo(ProductReviewStatus::Rejected));
        self::assertFalse(ProductReviewStatus::Approved->canTransitionTo(ProductReviewStatus::Pending));
        self::assertFalse(ProductReviewStatus::Approved->canTransitionTo(ProductReviewStatus::Approved));

        self::assertTrue(ProductReviewStatus::Rejected->canTransitionTo(ProductReviewStatus::Pending));
        self::assertFalse(ProductReviewStatus::Rejected->canTransitionTo(ProductReviewStatus::Approved));
        self::assertFalse(ProductReviewStatus::Rejected->canTransitionTo(ProductReviewStatus::Rejected));

        self::assertTrue(ProductReviewStatus::Hidden->canTransitionTo(ProductReviewStatus::Approved));
        self::assertFalse(ProductReviewStatus::Hidden->canTransitionTo(ProductReviewStatus::Pending));
        self::assertFalse(ProductReviewStatus::Hidden->canTransitionTo(ProductReviewStatus::Hidden));
    }

    public function test_eligibility_returns_false_when_no_delivered_orders_exist(): void
    {
        $eligibilityAction = app(CheckProductReviewEligibilityAction::class);

        self::assertFalse($eligibilityAction->execute($this->customer, $this->product));
    }

    public function test_eligibility_returns_false_when_order_is_not_delivered(): void
    {
        $this->createDeliveredOrder($this->customer, $this->variant, OrderStatus::Confirmed);

        $eligibilityAction = app(CheckProductReviewEligibilityAction::class);
        self::assertFalse($eligibilityAction->execute($this->customer, $this->product));
    }

    public function test_eligibility_returns_true_when_order_is_authoritatively_delivered(): void
    {
        $this->createDeliveredOrder($this->customer, $this->variant, OrderStatus::Delivered);

        $eligibilityAction = app(CheckProductReviewEligibilityAction::class);
        self::assertTrue($eligibilityAction->execute($this->customer, $this->product));
    }

    public function test_submitting_review_creates_pending_record_with_verified_flag(): void
    {
        // Give customer a delivered order
        $this->createDeliveredOrder($this->customer, $this->variant, OrderStatus::Delivered);

        $action = app(SubmitProductReviewAction::class);
        $review = $action->execute(
            customer: $this->customer,
            product: $this->product,
            rating: 5,
            title: 'منتج ممتاز جداً',
            comment: 'التوصيل سريع والمنتج أصلي',
        );

        self::assertSame(5, $review->rating);
        self::assertSame('منتج ممتاز جداً', $review->title);
        self::assertSame('التوصيل سريع والمنتج أصلي', $review->comment);
        self::assertSame(ProductReviewStatus::Pending, $review->status);
        self::assertTrue($review->is_verified_purchase);
        self::assertNull($review->moderated_at);

        // Updating existing review for same customer & product
        $updated = $action->execute(
            customer: $this->customer,
            product: $this->product,
            rating: 4,
            title: 'تحديث تقييم',
            comment: 'تعديل التجربة',
        );

        self::assertSame($review->id, $updated->id);
        self::assertSame(4, $updated->rating);
        self::assertSame('تحديث تقييم', $updated->title);
        self::assertSame(ProductReviewStatus::Pending, $updated->status);
    }

    public function test_rating_summary_query_calculates_approved_reviews_only(): void
    {
        $summaryQuery = app(GetProductRatingSummaryQuery::class);

        // Before any reviews
        $summary = $summaryQuery->execute($this->product);
        self::assertSame(0.0, $summary['average_rating']);
        self::assertSame(0, $summary['reviews_count']);
        self::assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], $summary['distribution']);

        // Create 2 extra customers
        $c2 = Customer::query()->create(['name' => 'عميل 2', 'phone' => '770000002', 'normalized_phone' => '+967770000002', 'is_active' => true]);
        $c3 = Customer::query()->create(['name' => 'عميل 3', 'phone' => '770000003', 'normalized_phone' => '+967770000003', 'is_active' => true]);
        $c4 = Customer::query()->create(['name' => 'عميل 4', 'phone' => '770000004', 'normalized_phone' => '+967770000004', 'is_active' => true]);

        // Create reviews with various statuses
        ProductReview::query()->create([
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'status' => ProductReviewStatus::Approved,
            'is_verified_purchase' => true,
        ]);

        ProductReview::query()->create([
            'customer_id' => $c2->id,
            'product_id' => $this->product->id,
            'rating' => 3,
            'status' => ProductReviewStatus::Approved,
            'is_verified_purchase' => false,
        ]);

        ProductReview::query()->create([
            'customer_id' => $c3->id,
            'product_id' => $this->product->id,
            'rating' => 1,
            'status' => ProductReviewStatus::Pending, // Must be excluded
            'is_verified_purchase' => false,
        ]);

        ProductReview::query()->create([
            'customer_id' => $c4->id,
            'product_id' => $this->product->id,
            'rating' => 1,
            'status' => ProductReviewStatus::Rejected, // Must be excluded
            'is_verified_purchase' => false,
        ]);

        $summary = $summaryQuery->execute($this->product);
        // Average of 5 and 3 = 4.0
        self::assertSame(4.0, $summary['average_rating']);
        self::assertSame(2, $summary['reviews_count']);
        self::assertSame(1, $summary['distribution'][5]);
        self::assertSame(1, $summary['distribution'][3]);
        self::assertSame(0, $summary['distribution'][1]);
    }

    private function createDeliveredOrder(Customer $customer, ProductVariant $variant, OrderStatus $status = OrderStatus::Delivered): Order
    {
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'order_number' => 'ORD-'.uniqid(),
            'status' => $status,
            'subtotal' => 1000,
            'total' => 1000,
            'base_currency_total' => 1000,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $variant->product->name_ar,
            'sku_snapshot' => $variant->sku,
            'quantity' => 1,
            'base_quantity' => 1,
            'unit_price' => 1000,
            'line_subtotal' => 1000,
            'line_total' => 1000,
            'base_currency_unit_price' => 1000,
            'base_currency_line_total' => 1000,
        ]);

        return $order;
    }
}
