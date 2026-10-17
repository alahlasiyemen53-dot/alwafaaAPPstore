<?php

namespace Tests\Unit;

use App\Enums\CouponDiscountType;
use App\Enums\CouponUsageStatus;
use App\Enums\OrderStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\CouponEligibilityService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CouponValidationTest extends TestCase
{
    use RefreshDatabase;

    private CouponEligibilityService $service;

    private Customer $customer;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->service = app(CouponEligibilityService::class);

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'فؤاد سالم',
            'phone' => '770001122',
            'normalized_phone' => '+967770001122',
            'is_active' => true,
        ]);
    }

    public function test_coupon_code_normalization(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE15',
            'name' => 'خصم 15%',
            'discount_type' => CouponDiscountType::Percentage,
            'discount_value' => 15.00,
            'is_active' => true,
        ]);

        // Input with lowercase and whitespace
        $result = $this->service->validate('  save15  ', 500.00, $this->customer->id);

        $this->assertTrue($result['is_valid']);
        $this->assertEquals($coupon->id, $result['coupon']->id);
        $this->assertEquals(75.00, $result['discount_amount']);
    }

    public function test_min_order_amount_enforcement(): void
    {
        Coupon::create([
            'code' => 'BIGBUY',
            'name' => 'خصم الطلبات الكبيرة',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 100.00,
            'minimum_order_amount' => 500.00,
            'is_active' => true,
        ]);

        // Subtotal below min amount
        $resultUnder = $this->service->validate('BIGBUY', 400.00, $this->customer->id);
        $this->assertFalse($resultUnder['is_valid']);
        $this->assertStringContainsString('الحد الأدنى', $resultUnder['error_message']);

        // Subtotal above min amount
        $resultValid = $this->service->validate('BIGBUY', 600.00, $this->customer->id);
        $this->assertTrue($resultValid['is_valid']);
        $this->assertEquals(100.00, $resultValid['discount_amount']);
    }

    public function test_max_discount_cap_for_percentage(): void
    {
        Coupon::create([
            'code' => 'HALFPRICE',
            'name' => 'نصف السعر',
            'discount_type' => CouponDiscountType::Percentage,
            'discount_value' => 50.00,
            'maximum_discount_amount' => 80.00,
            'is_active' => true,
        ]);

        // 50% of 400 = 200, but capped at 80
        $result = $this->service->validate('HALFPRICE', 400.00, $this->customer->id);
        $this->assertTrue($result['is_valid']);
        $this->assertEquals(80.00, $result['discount_amount']);
    }

    public function test_global_usage_limit(): void
    {
        $coupon = Coupon::create([
            'code' => 'LIMITED1',
            'name' => 'كوبون لاستخدام واحد',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 50.00,
            'usage_limit' => 1,
            'is_active' => true,
        ]);

        $this->createActiveUsage($coupon, 50.00);

        $result = $this->service->validate('LIMITED1', 200.00, $this->customer->id);
        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('الحد الأقصى', $result['error_message']);
    }

    public function test_per_customer_usage_limit(): void
    {
        $coupon = Coupon::create([
            'code' => 'ONCEPERCUST',
            'name' => 'كوبون لكل عميل',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 20.00,
            'per_customer_limit' => 1,
            'is_active' => true,
        ]);

        $this->createActiveUsage($coupon, 20.00);

        $resultSameCustomer = $this->service->validate('ONCEPERCUST', 200.00, $this->customer->id);
        $this->assertFalse($resultSameCustomer['is_valid']);
        $this->assertStringContainsString('الحد المسموح', $resultSameCustomer['error_message']);

        // Another customer is allowed
        $otherCustomer = Customer::create([
            'name' => 'عميل آخر',
            'phone' => '779988776',
            'normalized_phone' => '+967779988776',
            'is_active' => true,
        ]);

        $resultOtherCustomer = $this->service->validate('ONCEPERCUST', 200.00, $otherCustomer->id);
        $this->assertTrue($resultOtherCustomer['is_valid']);
    }

    public function test_coupon_incompatible_with_promotions_is_rejected_only_when_promotions_are_active(): void
    {
        Coupon::create([
            'code' => 'NOPROMO',
            'name' => 'كوبون دون عروض',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 30.00,
            'is_compatible_with_promotions' => false,
            'is_active' => true,
        ]);

        $resultWithPromo = $this->service->validate('NOPROMO', 300.00, $this->customer->id, true);
        $this->assertFalse($resultWithPromo['is_valid']);
        $this->assertStringContainsString('غير متوافق', $resultWithPromo['error_message']);

        $resultNoPromo = $this->service->validate('NOPROMO', 300.00, $this->customer->id, false);
        $this->assertTrue($resultNoPromo['is_valid']);
    }

    public function test_coupon_scope_filters_lines_and_calculates_discount_on_eligible_total(): void
    {
        $cat1 = Category::create(['name_ar' => 'قسم 1', 'slug' => 'cat-1', 'is_active' => true]);
        $cat2 = Category::create(['name_ar' => 'قسم 2', 'slug' => 'cat-2', 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'ماركة', 'slug' => 'br-1', 'is_active' => true]);

        $prod1 = Product::create(['category_id' => $cat1->id, 'brand_id' => $brand->id, 'name_ar' => 'منتج 1', 'slug' => 'p1', 'status' => 'active']);
        $prod2 = Product::create(['category_id' => $cat2->id, 'brand_id' => $brand->id, 'name_ar' => 'منتج 2', 'slug' => 'p2', 'status' => 'active']);

        $var1 = ProductVariant::create(['product_id' => $prod1->id, 'sku' => 'SKU-1', 'status' => 'active', 'is_default' => true]);
        $var2 = ProductVariant::create(['product_id' => $prod2->id, 'sku' => 'SKU-2', 'status' => 'active', 'is_default' => true]);

        // Category-scoped coupon (only cat1) with 10% discount
        $coupon = Coupon::create([
            'code' => 'CAT1ONLY',
            'name' => 'كوبون قسم 1',
            'discount_type' => CouponDiscountType::Percentage,
            'discount_value' => 10.00,
            'scope' => 'category',
            'is_active' => true,
        ]);
        $coupon->targets()->create([
            'target_type' => 'category',
            'target_id' => $cat1->id,
        ]);

        $lines = [
            [
                'variant' => $var1,
                'unit_price' => '100.0000',
                'quantity' => '2.0000',
                'line_subtotal' => '200.0000',
                'line_subtotal_after_item' => '200.0000',
                'item_discount_amount' => '0.0000',
            ],
            [
                'variant' => $var2,
                'unit_price' => '150.0000',
                'quantity' => '2.0000',
                'line_subtotal' => '300.0000',
                'line_subtotal_after_item' => '300.0000',
                'item_discount_amount' => '0.0000',
            ],
        ];

        $result = $this->service->validate('CAT1ONLY', $lines, $this->customer->id);

        $this->assertTrue($result['is_valid']);
        // Only line 0 (200 YER) is eligible; 10% of 200 = 20 YER (not 10% of 500 = 50)
        $this->assertEquals(20.00, $result['discount_amount']);
        $this->assertEquals([0], $result['eligible_line_indices']);
    }

    public function test_coupon_incompatibility_auto_detected_from_lines(): void
    {
        $cat = Category::create(['name_ar' => 'قسم', 'slug' => 'cat-incomp', 'is_active' => true]);
        $prod = Product::create(['category_id' => $cat->id, 'name_ar' => 'منتج', 'slug' => 'p-incomp', 'status' => 'active']);
        $var = ProductVariant::create(['product_id' => $prod->id, 'sku' => 'SKU-INCOMP', 'status' => 'active', 'is_default' => true]);

        Coupon::create([
            'code' => 'NOSTACK',
            'name' => 'كوبون غير متوافق',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 15.00,
            'scope' => 'all',
            'is_compatible_with_promotions' => false,
            'is_active' => true,
        ]);

        $linesWithDiscount = [
            [
                'variant' => $var,
                'line_subtotal' => '100.0000',
                'line_subtotal_after_item' => '80.0000',
                'item_discount_amount' => '20.0000',
            ],
        ];

        $result = $this->service->validate('NOSTACK', $linesWithDiscount, $this->customer->id);
        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('غير متوافق', $result['error_message']);
    }

    public function test_coupon_usages_unique_constraint_enforces_idempotency(): void
    {
        $coupon = Coupon::create([
            'code' => 'UNIQUEUSE',
            'name' => 'كوبون وحيد',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $usage1 = $this->createActiveUsage($coupon, 10.00);

        // Attempting to insert another usage for the same order and coupon throws QueryException
        $this->expectException(QueryException::class);
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $this->customer->id,
            'order_id' => $usage1->order_id,
            'discount_amount_snapshot' => 10.00,
            'currency_id' => $this->currency->id,
            'status' => CouponUsageStatus::Active,
            'used_at' => now(),
        ]);
    }

    private function createActiveUsage(Coupon $coupon, float $discountAmount): CouponUsage
    {
        $warehouse = Warehouse::create([
            'code' => 'WH-COUPON',
            'name_ar' => 'مستودع الكوبونات',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-COUPON-'.$coupon->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Confirmed,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
        ]);

        return CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'discount_amount_snapshot' => $discountAmount,
            'currency_id' => $this->currency->id,
            'status' => CouponUsageStatus::Active,
            'used_at' => now(),
        ]);
    }
}
