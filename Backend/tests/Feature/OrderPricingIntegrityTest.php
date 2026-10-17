<?php

namespace Tests\Feature;

use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\CouponDiscountType;
use App\Enums\OrderStatus;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderPricingIntegrityTest extends TestCase
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
            'name' => 'سعيد باوزير',
            'phone' => '773322110',
            'normalized_phone' => '+967773322110',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'سعيد باوزير',
            'contact_phone' => '773322110',
            'neighborhood' => 'حدة',
            'street' => 'شارع بيروت',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع المركز',
            'name_en' => 'Central Warehouse',
            'code' => 'WH-CTR',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name_ar' => 'حبة',
            'name_en' => 'Piece',
            'decimal_places' => 0,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'ماركة تجريبية', 'slug' => 'exp-brand', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'صنف تجريبي', 'slug' => 'exp-cat', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج تسعير موثوق',
            'name_en' => 'Authoritative Pricing Product',
            'slug' => 'auth-pricing-prod',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-AUTH-001',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 100.0000,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 100.0000,
            'reserved_quantity' => 0.0000,
        ]);
    }

    /**
     * 1. Promotion-only draft 90.0000 remains 90.0000 after confirmation
     *    and does not re-apply draft discount as an extra discount.
     */
    public function test_promotion_only_draft_90_remains_90_after_confirmation(): void
    {
        // 10% promotion on the variant
        $promo = Promotion::create([
            'code' => 'PROMO10',
            'name' => 'عرض 10%',
            'type' => PromotionType::Percentage,
            'scope' => PromotionScope::Variant,
            'value' => 10.00,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'priority' => 1,
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $promo->id,
            'target_type' => 'variant',
            'target_id' => $this->variant->id,
        ]);

        $dto = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            items: [
                new OrderItemData(productVariantId: $this->variant->id, quantity: 1.0),
            ],
        );

        $createAction = app(CreateOrderAction::class);
        $order = $createAction->execute($dto, $this->admin->id);

        $this->assertEquals(OrderStatus::Draft, $order->status);
        $this->assertEquals(100.00, (float) $order->subtotal);
        $this->assertEquals(10.00, (float) $order->promotion_discount_total);
        $this->assertEquals(10.00, (float) $order->discount_amount);
        $this->assertEquals(90.00, (float) $order->total);

        // Confirm the order
        $confirmAction = app(ConfirmOrderAction::class);
        $confirmed = $confirmAction->execute($order, $this->admin->id);

        $this->assertEquals(OrderStatus::Confirmed, $confirmed->status);
        $this->assertEquals(100.00, (float) $confirmed->subtotal);
        $this->assertEquals(10.00, (float) $confirmed->promotion_discount_total);
        $this->assertEquals(10.00, (float) $confirmed->discount_amount);
        $this->assertEquals(90.00, (float) $confirmed->total);
    }

    /**
     * 2. Order total equals SUM(order_items.line_total) and
     * 3. Order discount equals SUM(order_items.discount_amount).
     */
    public function test_order_totals_and_discounts_equal_sum_of_items(): void
    {
        $promo = Promotion::create([
            'code' => 'PROMO2-10',
            'name' => 'عرض 10%',
            'type' => PromotionType::Percentage,
            'scope' => PromotionScope::Variant,
            'value' => 10.00,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'priority' => 1,
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $promo->id,
            'target_type' => 'variant',
            'target_id' => $this->variant->id,
        ]);

        $coupon = Coupon::create([
            'code' => 'COUPON5',
            'name' => 'كوبون 5',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 5.00,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_compatible_with_promotions' => true,
            'is_active' => true,
        ]);

        $dto = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            items: [
                new OrderItemData(productVariantId: $this->variant->id, quantity: 2.0),
            ],
            couponCode: 'COUPON5',
        );

        $order = app(CreateOrderAction::class)->execute($dto, $this->admin->id);

        // Subtotal: 2 * 100 = 200. Promo: 10% of 200 = 20. Coupon: 5. Total Discount: 25. Total: 175.
        $this->assertEquals(200.00, (float) $order->subtotal);
        $this->assertEquals(25.00, (float) $order->discount_amount);
        $this->assertEquals(175.00, (float) $order->total);

        $sumLineTotals = (float) $order->items->sum('line_total');
        $sumLineDiscounts = (float) $order->items->sum('discount_amount');

        $this->assertEquals((float) $order->total, $sumLineTotals);
        $this->assertEquals((float) $order->discount_amount, $sumLineDiscounts);
    }

    /**
     * 4. Client discount_amount and items.*.discount_amount are rejected or ignored.
     */
    public function test_client_discount_amount_in_http_request_is_ignored(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'discount_amount' => 9999.00, // Malicious manual discount
            'action' => 'draft',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                    'discount_amount' => 8888.00, // Malicious line discount
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/admin/orders', $payload);

        $order = Order::latest('id')->firstOrFail();
        $response->assertRedirect("/admin/orders/{$order->id}");

        // The authoritative price is 100, zero discount
        $this->assertEquals(100.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->discount_amount);
        $this->assertEquals(100.00, (float) $order->total);
        $this->assertEquals(0.00, (float) $order->items->first()->discount_amount);
        $this->assertEquals(100.00, (float) $order->items->first()->line_total);
    }

    /**
     * 5. coupon_code survives StoreOrderRequest and UpdateOrderRequest normalization.
     */
    public function test_coupon_code_survives_request_normalization(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE15',
            'name' => 'خصم 15',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 15.00,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'coupon_code' => '  save15  ', // Lowercase with whitespace
            'action' => 'draft',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/admin/orders', $payload);

        $order = Order::latest('id')->firstOrFail();
        $response->assertRedirect("/admin/orders/{$order->id}");

        $this->assertSame('SAVE15', $order->coupon_code_snapshot);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertEquals(15.00, (float) $order->coupon_discount_total);
        $this->assertEquals(85.00, (float) $order->total);

        // Test UpdateOrderRequest
        $updatePayload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'coupon_code' => '  SAVE15  ',
            'action' => 'draft',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $updateResponse = $this->actingAs($this->admin)->put("/admin/orders/{$order->id}", $updatePayload);
        $updateResponse->assertRedirect("/admin/orders/{$order->id}");

        $order->refresh();
        $this->assertSame('SAVE15', $order->coupon_code_snapshot);
        $this->assertEquals(200.00, (float) $order->subtotal);
        $this->assertEquals(15.00, (float) $order->coupon_discount_total);
        $this->assertEquals(185.00, (float) $order->total);
    }

    /**
     * 6. Missing Base Currency price throws DomainException and creates no Order/reservation.
     */
    public function test_missing_base_currency_price_throws_domain_exception_and_creates_no_order(): void
    {
        // Create another variant without any VariantPrice
        $product = Product::query()->first();
        $unpricedVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-UNPRICED-99',
            'status' => 'active',
            'is_default' => false,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $unpricedVariant->id,
            'quantity' => 10.0000,
            'reserved_quantity' => 0.0000,
        ]);

        $dto = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            items: [
                new OrderItemData(productVariantId: $unpricedVariant->id, quantity: 1.0),
            ],
        );

        $this->expectException(DomainException::class);
        try {
            app(CreateOrderAction::class)->execute($dto, $this->admin->id);
        } finally {
            $this->assertDatabaseMissing('orders', [
                'customer_id' => $this->customer->id,
            ]);
            $this->assertDatabaseMissing('inventory_reservations', [
                'product_variant_id' => $unpricedVariant->id,
            ]);
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
