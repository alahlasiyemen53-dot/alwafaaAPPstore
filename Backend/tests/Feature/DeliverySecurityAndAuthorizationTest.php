<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\AssignOrderToDriverAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\DTOs\AssignOrderToDriverData;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeliverySecurityAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Driver $driver;

    private Warehouse $warehouse;

    private Currency $currency;

    private Customer $customer;

    private CustomerAddress $address;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');

        $this->driver = Driver::create([
            'driver_code' => 'DRV-0001',
            'name' => 'مندوب أمان',
            'phone' => '770001112',
            'is_active' => true,
        ]);

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'عميل الصلاحيات',
            'phone' => '772223334',
            'normalized_phone' => '+967772223334',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المكتب',
            'contact_name' => 'عميل الصلاحيات',
            'contact_phone' => '772223334',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع الأمان',
            'code' => 'WH-SEC',
            'is_active' => true,
        ]);

        $unit = Unit::create(['code' => 'PCS', 'name_ar' => 'حبة', 'decimal_places' => 0, 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'ماركة', 'slug' => 'brand-sec', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف', 'slug' => 'cat-sec', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج الصلاحيات',
            'slug' => 'sec-prod',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-SEC',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 500.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10.0000,
            'reserved_quantity' => 0.0000,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/drivers')->assertRedirect('/admin/login');
        $this->get('/admin/deliveries')->assertRedirect('/admin/login');
    }

    public function test_user_without_permission_cannot_access_drivers_or_deliveries(): void
    {
        $unauthorizedUser = $this->userWithRole('cashier');

        $this->actingAs($unauthorizedUser)->get('/admin/drivers')->assertForbidden();
        $this->actingAs($unauthorizedUser)->get('/admin/deliveries')->assertForbidden();
    }

    public function test_delivery_supervisor_has_access_to_assign_and_update_deliveries(): void
    {
        $supervisor = $this->userWithRole('delivery_supervisor');

        $this->actingAs($supervisor)->get('/admin/drivers')->assertOk();
        $this->actingAs($supervisor)->get('/admin/deliveries')->assertOk();

        // Assign confirmed order
        $order = $this->createAndConfirmOrder(2.0);
        $assignResponse = $this->actingAs($supervisor)->post("/admin/orders/{$order->id}/assign-delivery", [
            'driver_id' => $this->driver->id,
        ]);
        $assignResponse->assertRedirect();

        $delivery = Delivery::query()->where('order_id', $order->id)->firstOrFail();

        // Mark failed
        $failResponse = $this->actingAs($supervisor)->post("/admin/deliveries/{$delivery->id}/failed", [
            'failure_reason' => DeliveryFailureReason::DeliveryIssue->value,
            'notes' => 'مشكلة في التوصيل',
        ]);
        $failResponse->assertRedirect();
    }

    public function test_warehouse_manager_can_return_delivery_to_warehouse(): void
    {
        $warehouseManager = $this->userWithRole('warehouse_manager');
        $delivery = $this->createAssignedDelivery(2.0);

        // Fail it first
        $this->actingAs($this->admin)->post("/admin/deliveries/{$delivery->id}/failed", [
            'failure_reason' => DeliveryFailureReason::WrongAddress->value,
            'notes' => 'العنوان غير مطابق',
        ]);

        $delivery->refresh();

        // Warehouse manager receives the returned goods
        $response = $this->actingAs($warehouseManager)->post("/admin/deliveries/{$delivery->id}/return-to-warehouse", [
            'notes' => 'تم الاستلام والفحص في المستودع',
        ]);

        $response->assertRedirect();
        $this->assertSame(DeliveryStatus::ReturnedToWarehouse, $delivery->fresh()->status);
    }

    public function test_authorized_admin_can_view_delivery_details(): void
    {
        $delivery = $this->createAssignedDelivery(2.0);

        $response = $this->actingAs($this->admin)->get("/admin/deliveries/{$delivery->id}");

        $response->assertOk();
        $response->assertSee($delivery->delivery_number);
        $response->assertSee($this->driver->name);
        $response->assertSee($this->variant->sku);
    }

    private function createAndConfirmOrder(float $quantity): Order
    {
        $orderData = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            items: [
                new OrderItemData(
                    productVariantId: $this->variant->id,
                    quantity: $quantity,
                ),
            ],
            notes: null,
        );

        $order = app(CreateOrderAction::class)->execute($orderData, $this->admin->id);

        return app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
    }

    private function createAssignedDelivery(float $quantity): Delivery
    {
        $order = $this->createAndConfirmOrder($quantity);

        return app(AssignOrderToDriverAction::class)->execute(
            order: $order,
            data: new AssignOrderToDriverData(driverId: $this->driver->id, notes: null),
            userId: $this->admin->id,
        );
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
