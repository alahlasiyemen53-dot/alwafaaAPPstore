<?php

namespace Tests\Feature;

use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\BundlePricingType;
use App\Enums\CouponDiscountType;
use App\Enums\OrderStatus;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderMarketingPricingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private CustomerAddress $address;

    private Warehouse $warehouse;

    private Currency $currency;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');

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
            'name' => 'طارق حميد',
            'phone' => '773344556',
            'normalized_phone' => '+967773344556',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'طارق حميد',
            'contact_phone' => '773344556',
            'address_line' => 'صنعاء - حدة',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'الزيوت والمواد الغذائية',
            'slug' => 'oils-groc',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name_ar' => 'العلامة الفاخرة',
            'slug' => 'premium-brand',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name_ar' => 'زيت زيتون بكر',
            'name_en' => 'Extra Virgin Olive Oil',
            'slug' => 'evoo-1',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'EVOO-1L',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 200.00,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 100,
        ]);
    }

    public function test_full_pricing_pipeline_with_promotion_and_coupon(): void
    {
        // 1. Setup 10% promotion on all products
        Promotion::create([
            'code' => 'SEASON-10',
            'name' => 'خصم الموسم 10%',
            'type' => PromotionType::Percentage,
            'scope' => PromotionScope::All,
            'value' => 10.00,
            'is_active' => true,
            'priority' => 1,
        ]);

        // 2. Setup coupon: 50 YER fixed discount
        $coupon = Coupon::create([
            'code' => 'WELCOME50',
            'name' => 'خصم الترحيب',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 50.00,
            'minimum_order_amount' => 100.00,
            'is_compatible_with_promotions' => true,
            'is_active' => true,
        ]);

        // 3. Create Draft Order: 2 units * 200 = 400.
        // Promotion discount: 10% of 400 = 40. Subtotal after promo = 360.
        // Coupon discount: 50.
        // Final Total = 310.
        $dto = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: 'طلب تجريبي مع كوبون وعرض',
            items: [
                new OrderItemData(
                    productVariantId: $this->variant->id,
                    quantity: 2,
                ),
            ],
            couponCode: 'WELCOME50',
        );

        $action = app(CreateOrderAction::class);
        $order = $action->execute($dto, $this->admin->id);

        $this->assertEquals(OrderStatus::Draft, $order->status);
        $this->assertEquals(400.00, (float) $order->subtotal);
        $this->assertEquals(40.00, (float) $order->promotion_discount_total);
        $this->assertEquals(50.00, (float) $order->coupon_discount_total);
        $this->assertEquals(310.00, (float) $order->total);

        // 4. Confirm Order and verify snapshots
        $confirmAction = app(ConfirmOrderAction::class);
        $confirmedOrder = $confirmAction->execute($order, $this->admin->id);

        $this->assertEquals(OrderStatus::Confirmed, $confirmedOrder->status);
        $this->assertEquals('WELCOME50', $confirmedOrder->coupon_code_snapshot);
        $this->assertEquals(50.00, (float) $confirmedOrder->coupon_discount_total);
        $this->assertEquals(40.00, (float) $confirmedOrder->promotion_discount_total);
        $this->assertEquals(310.00, (float) $confirmedOrder->total);

        // Verify OrderItem snapshots
        $item = $confirmedOrder->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals(40.00, (float) $item->item_discount_amount);
        $this->assertEquals(50.00, (float) $item->allocated_order_discount);
        $this->assertEquals(310.00, (float) $item->line_total);
        $this->assertEquals('خصم الموسم 10%', $item->promotion_name_snapshot);

        // 5. Immutability check: Even if coupon is deactivated or promotion edited, confirmed order does not change
        $coupon->update(['is_active' => false, 'discount_value' => 0.00]);
        $confirmedOrder->refresh();

        $this->assertEquals('WELCOME50', $confirmedOrder->coupon_code_snapshot);
        $this->assertEquals(50.00, (float) $confirmedOrder->coupon_discount_total);
        $this->assertEquals(310.00, (float) $confirmedOrder->total);
    }

    public function test_bundle_and_promotion_interaction_with_surplus_quantity(): void
    {
        // Setup a second variant
        $productB = Product::create([
            'category_id' => Category::first()->id,
            'brand_id' => Brand::first()->id,
            'name_ar' => 'خل التفاح',
            'name_en' => 'Apple Cider Vinegar',
            'slug' => 'acv-1',
            'status' => 'active',
        ]);

        $variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'ACV-1L',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variantB->id,
            'currency_id' => $this->currency->id,
            'price' => 100.00,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $variantB->id,
            'quantity' => 100,
        ]);

        // 1. Bundle: Variant A (qty 1) + Variant B (qty 1), fixed price 250 YER (normal total = 200 + 100 = 300 YER, discount = 50 YER)
        // Allocation: Variant A = 200/300 * 50 = 33.3333 YER, Variant B = 100/300 * 50 = 16.6667 YER
        $bundle = Bundle::create([
            'code' => 'BUNDLE-OILS',
            'name' => 'عرض زيت وخل',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 250.00,
            'is_active' => true,
            'priority' => 1,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1.00,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $variantB->id,
            'quantity' => 1.00,
        ]);

        // 2. Promotion: 20% on all products
        Promotion::create([
            'code' => 'PROMO-20',
            'name' => 'عرض 20%',
            'type' => PromotionType::Percentage,
            'scope' => PromotionScope::All,
            'value' => 20.00,
            'is_active' => true,
            'priority' => 1,
        ]);

        // 3. Order: Variant A qty 2 (total 400), Variant B qty 1 (total 100). Subtotal = 500.
        // Bundle consumes: 1 of A and 1 of B -> bundle discount = 50 (33.3333 on A, 16.6667 on B).
        // Surplus for A = 2 - 1 = 1 unit. Promotion applies only to surplus: 1 * 200 * 20% = 40.00 discount!
        // Surplus for B = 0 -> no promo discount on B.
        // Total bundle discount = 50.00. Total promo discount = 40.00.
        // Total order = 500 - 50 - 40 = 410.00.
        $dto = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: 'طلب حزمة وفائض',
            items: [
                new OrderItemData(
                    productVariantId: $this->variant->id,
                    quantity: 2,
                ),
                new OrderItemData(
                    productVariantId: $variantB->id,
                    quantity: 1,
                ),
            ],
        );

        $order = app(CreateOrderAction::class)->execute($dto, $this->admin->id);

        $this->assertEquals(500.00, (float) $order->subtotal);
        $this->assertEquals(50.00, (float) $order->bundle_discount_total);
        $this->assertEquals(40.00, (float) $order->promotion_discount_total);
        $this->assertEquals(410.00, (float) $order->total);

        // Confirm order
        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
        $this->assertEquals(OrderStatus::Confirmed, $confirmedOrder->status);
        $this->assertEquals(410.00, (float) $confirmedOrder->total);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
