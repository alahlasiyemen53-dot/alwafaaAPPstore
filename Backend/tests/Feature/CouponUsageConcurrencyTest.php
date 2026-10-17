<?php

namespace Tests\Feature;

use App\Actions\CancelOrderAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\DTOs\CancelOrderData;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\CouponDiscountType;
use App\Enums\CouponUsageStatus;
use App\Enums\OrderStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CouponUsageConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer1;

    private Customer $customer2;

    private CustomerAddress $address1;

    private CustomerAddress $address2;

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

        $this->customer1 = Customer::create([
            'name' => 'عميل أول',
            'phone' => '771111111',
            'normalized_phone' => '+967771111111',
            'is_active' => true,
        ]);

        $this->address1 = CustomerAddress::create([
            'customer_id' => $this->customer1->id,
            'label' => 'المقر',
            'contact_name' => 'عميل أول',
            'contact_phone' => '771111111',
            'address_line' => 'صنعاء',
            'is_default' => true,
        ]);

        $this->customer2 = Customer::create([
            'name' => 'عميل ثان',
            'phone' => '772222222',
            'normalized_phone' => '+967772222222',
            'is_active' => true,
        ]);

        $this->address2 = CustomerAddress::create([
            'customer_id' => $this->customer2->id,
            'label' => 'المقر 2',
            'contact_name' => 'عميل ثان',
            'contact_phone' => '772222222',
            'address_line' => 'عدن',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'عام',
            'slug' => 'general-coupon-test',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'ماركة', 'slug' => 'brand-coupon-test', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name_ar' => 'منتج تجريبي',
            'name_en' => 'Test Product',
            'slug' => 'tst-prd-coupon',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TST-SKU',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 100.00,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 200,
        ]);
    }

    public function test_global_usage_limit_enforced_at_confirmation(): void
    {
        // Coupon limit = 1 globally
        $coupon = Coupon::create([
            'code' => 'FLASH1',
            'name' => 'كوبون محدود',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 30.00,
            'usage_limit' => 1,
            'is_active' => true,
        ]);

        $createAction = app(CreateOrderAction::class);
        $confirmAction = app(ConfirmOrderAction::class);

        // First order
        $dto1 = new CreateOrderData(
            customerId: $this->customer1->id,
            customerAddressId: $this->address1->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: null,
            items: [new OrderItemData(productVariantId: $this->variant->id, quantity: 1)],
            couponCode: 'FLASH1',
        );
        $order1 = $createAction->execute($dto1, $this->admin->id);

        // Second order created as draft while first order is still draft
        $dto2 = new CreateOrderData(
            customerId: $this->customer2->id,
            customerAddressId: $this->address2->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: null,
            items: [new OrderItemData(productVariantId: $this->variant->id, quantity: 1)],
            couponCode: 'FLASH1',
        );
        $order2 = $createAction->execute($dto2, $this->admin->id);

        // First confirmation succeeds
        $confirmAction->execute($order1, $this->admin->id);
        $this->assertEquals(OrderStatus::Confirmed, $order1->refresh()->status);
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'order_id' => $order1->id,
            'status' => CouponUsageStatus::Active->value,
        ]);

        // Second confirmation fails because limit reached
        $this->expectException(DomainException::class);
        $confirmAction->execute($order2, $this->admin->id);
    }

    public function test_coupon_usage_released_on_order_cancellation(): void
    {
        $coupon = Coupon::create([
            'code' => 'RELEASABLE',
            'name' => 'كوبون قابل للتحرير',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 25.00,
            'usage_limit' => 1,
            'is_active' => true,
        ]);

        $createAction = app(CreateOrderAction::class);
        $confirmAction = app(ConfirmOrderAction::class);
        $cancelAction = app(CancelOrderAction::class);

        $dto = new CreateOrderData(
            customerId: $this->customer1->id,
            customerAddressId: $this->address1->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: null,
            items: [new OrderItemData(productVariantId: $this->variant->id, quantity: 1)],
            couponCode: 'RELEASABLE',
        );

        $order = $createAction->execute($dto, $this->admin->id);
        $confirmAction->execute($order, $this->admin->id);

        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'status' => CouponUsageStatus::Active->value,
        ]);

        // Cancel order
        $cancelDto = new CancelOrderData(cancellationReason: 'إلغاء بناء على طلب العميل');
        $cancelAction->execute($order, $cancelDto, $this->admin->id);

        $this->assertEquals(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'status' => CouponUsageStatus::Released->value,
        ]);

        // Now another order can use the coupon since active usage is 0!
        $order2 = $createAction->execute($dto, $this->admin->id);
        $confirmed2 = $confirmAction->execute($order2, $this->admin->id);
        $this->assertEquals(OrderStatus::Confirmed, $confirmed2->status);
    }

    public function test_per_customer_limit_enforced_at_confirmation(): void
    {
        $coupon = Coupon::create([
            'code' => 'PERCUST1',
            'name' => 'كوبون عميل واحد',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 20.00,
            'per_customer_limit' => 1,
            'is_active' => true,
        ]);

        $createAction = app(CreateOrderAction::class);
        $confirmAction = app(ConfirmOrderAction::class);

        $dto1 = new CreateOrderData(
            customerId: $this->customer1->id,
            customerAddressId: $this->address1->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: null,
            items: [new OrderItemData(productVariantId: $this->variant->id, quantity: 1)],
            couponCode: 'PERCUST1',
        );

        $order1 = $createAction->execute($dto1, $this->admin->id);
        $order2 = $createAction->execute($dto1, $this->admin->id);

        $confirmAction->execute($order1, $this->admin->id);
        $this->assertEquals(OrderStatus::Confirmed, $order1->refresh()->status);

        // Same customer's second order confirmation fails
        $this->expectException(DomainException::class);
        $confirmAction->execute($order2, $this->admin->id);
    }

    public function test_double_confirmation_of_one_order_is_idempotent(): void
    {
        $coupon = Coupon::create([
            'code' => 'DOUBLECONF',
            'name' => 'تأكيد مضاعف',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 10.00,
            'usage_limit' => 1,
            'is_active' => true,
        ]);

        $createAction = app(CreateOrderAction::class);
        $confirmAction = app(ConfirmOrderAction::class);

        $dto = new CreateOrderData(
            customerId: $this->customer1->id,
            customerAddressId: $this->address1->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: null,
            items: [new OrderItemData(productVariantId: $this->variant->id, quantity: 1)],
            couponCode: 'DOUBLECONF',
        );

        $order = $createAction->execute($dto, $this->admin->id);
        $confirmed1 = $confirmAction->execute($order, $this->admin->id);
        $this->assertEquals(OrderStatus::Confirmed, $confirmed1->status);

        // Second confirmation returns the confirmed order safely without duplicate usages
        $confirmed2 = $confirmAction->execute($order, $this->admin->id);
        $this->assertEquals(OrderStatus::Confirmed, $confirmed2->status);
        $this->assertEquals(1, CouponUsage::where('order_id', $order->id)->count());
    }

    public function test_confirmation_fails_and_rolls_back_if_coupon_is_deactivated(): void
    {
        $coupon = Coupon::create([
            'code' => 'DEACTIVATED',
            'name' => 'كوبون سيعطل',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $createAction = app(CreateOrderAction::class);
        $confirmAction = app(ConfirmOrderAction::class);

        $dto = new CreateOrderData(
            customerId: $this->customer1->id,
            customerAddressId: $this->address1->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: null,
            items: [new OrderItemData(productVariantId: $this->variant->id, quantity: 1)],
            couponCode: 'DEACTIVATED',
        );

        $order = $createAction->execute($dto, $this->admin->id);

        // Deactivate coupon before confirmation
        $coupon->update(['is_active' => false]);

        try {
            $confirmAction->execute($order, $this->admin->id);
            $this->fail('Confirmation should have failed because coupon is inactive.');
        } catch (DomainException $e) {
            // Expected
        }

        // Order remains in Draft
        $this->assertEquals(OrderStatus::Draft, $order->refresh()->status);
        $this->assertEquals(0, CouponUsage::where('order_id', $order->id)->count());
        $this->assertEquals(0, InventoryReservation::where('order_id', $order->id)->count());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
