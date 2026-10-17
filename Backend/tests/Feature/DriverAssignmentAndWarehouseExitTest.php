<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DriverAssignmentAndWarehouseExitTest extends TestCase
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
            'name' => 'طارق السائق',
            'phone' => '771122334',
            'vehicle_type' => 'دراجة نارية',
            'vehicle_plate_number' => 'ص 555',
            'is_active' => true,
        ]);

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
            'name' => 'عميل تجريبي',
            'phone' => '770011223',
            'normalized_phone' => '+967770011223',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'عميل تجريبي',
            'contact_phone' => '770011223',
            'street' => 'شارع الستين',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع صنعاء',
            'code' => 'WH-SNA',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name_ar' => 'حبة',
            'decimal_places' => 0,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'علامة تجارية', 'slug' => 'brand-1', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف رئيسي', 'slug' => 'cat-1', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'هاتف ذكي',
            'slug' => 'phone-1',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-PHONE-X',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 1000.00,
            'is_active' => true,
        ]);

        // Stock: 20 pcs
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 20.0000,
            'reserved_quantity' => 0.0000,
        ]);
    }

    public function test_assign_confirmed_order_to_driver_causes_warehouse_exit_and_consumes_reservation(): void
    {
        $order = $this->createAndConfirmOrder(quantity: 5.0);

        // Before assignment:
        $inventory = Inventory::query()->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertEquals(20.0, (float) $inventory->quantity);
        $this->assertEquals(5.0, (float) $inventory->reserved_quantity);

        $reservation = InventoryReservation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(ReservationStatus::Active, $reservation->status);

        // Assign to driver
        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'driver_id' => $this->driver->id,
            'notes' => 'يرجى التسليم في الفترة الصباحية',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify Order status
        $order->refresh();
        $this->assertSame(OrderStatus::AssignedToDriver, $order->status);

        // Verify Delivery
        $delivery = Delivery::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(DeliveryStatus::Assigned, $delivery->status);
        $this->assertSame($this->driver->id, $delivery->driver_id);
        $this->assertSame('عميل تجريبي', $delivery->recipient_name);
        $this->assertNotNull($delivery->assigned_at);
        $this->assertEquals(5000.0, (float) $delivery->cod_amount);

        // Verify Inventory (Atomic exit!)
        $inventory->refresh();
        $this->assertEquals(15.0, (float) $inventory->quantity);
        $this->assertEquals(0.0, (float) $inventory->reserved_quantity);

        // Verify Reservation consumed
        $reservation->refresh();
        $this->assertSame(ReservationStatus::Consumed, $reservation->status);

        // Verify StockMovement: SALE / OUT
        $movement = StockMovement::query()->where('reference_type', 'Order')
            ->where('reference_id', $order->id)->firstOrFail();

        $this->assertSame(StockMovementType::Sale, $movement->movement_type);
        $this->assertSame(StockMovementDirection::Out, $movement->direction);
        $this->assertEquals(5.0, (float) $movement->quantity);
    }

    public function test_cannot_assign_draft_order_to_driver(): void
    {
        $order = $this->createDraftOrder(quantity: 3.0);

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'driver_id' => $this->driver->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(OrderStatus::Draft, $order->fresh()->status);
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_cannot_assign_already_assigned_order(): void
    {
        $order = $this->createAndConfirmOrder(quantity: 4.0);

        // First assignment
        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'driver_id' => $this->driver->id,
        ]);

        $this->assertSame(OrderStatus::AssignedToDriver, $order->fresh()->status);

        // Attempt second assignment with a different driver
        $driver2 = Driver::create([
            'driver_code' => 'DRV-0002',
            'name' => 'مندوب ثان',
            'phone' => '779998881',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'driver_id' => $driver2->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_cannot_assign_to_inactive_driver(): void
    {
        $this->driver->update(['is_active' => false]);
        $order = $this->createAndConfirmOrder(quantity: 2.0);

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/assign-delivery", [
            'driver_id' => $this->driver->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertDatabaseCount('deliveries', 0);
    }

    private function createDraftOrder(float $quantity): Order
    {
        $data = new CreateOrderData(
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

        return app(CreateOrderAction::class)->execute($data, $this->admin->id);
    }

    private function createAndConfirmOrder(float $quantity): Order
    {
        $order = $this->createDraftOrder($quantity);

        return app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
