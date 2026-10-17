<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\AssignOrderToDriverAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\Actions\RecordStockMovementAction;
use App\DTOs\AssignOrderToDriverData;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
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

final class DeliveryOutcomeAndReturnTest extends TestCase
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
            'name' => 'جمال المندوب',
            'phone' => '779988776',
            'vehicle_type' => 'دراجة نارية',
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
            'name' => 'زبون رقم 1',
            'phone' => '778899001',
            'normalized_phone' => '+967778899001',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'زبون رقم 1',
            'contact_phone' => '778899001',
            'street' => 'حدة',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع حدة',
            'code' => 'WH-HDA',
            'is_active' => true,
        ]);

        $unit = Unit::create(['code' => 'PCS', 'name_ar' => 'حبة', 'decimal_places' => 0, 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'ماركة', 'slug' => 'brand-m', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'قسم', 'slug' => 'cat-m', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'سماعات بلوتوث',
            'slug' => 'headset-1',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-HEADSET-BT',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 2000.00,
            'is_active' => true,
        ]);

        // Initial Stock: 20 pcs recorded via movement
        app(RecordStockMovementAction::class)->execute(
            warehouseId: $this->warehouse->id,
            productVariantId: $this->variant->id,
            movementType: StockMovementType::AdjustmentIn,
            direction: StockMovementDirection::In,
            quantity: 20,
            reference: $this->warehouse,
        );
    }

    public function test_mark_delivered_updates_status_without_secondary_stock_deduction(): void
    {
        $delivery = $this->createAssignedDelivery(quantity: 5.0);

        // After assignment, stock is 15
        $inventory = Inventory::query()->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertEquals(15.0, (float) $inventory->quantity);
        $this->assertEquals(0.0, (float) $inventory->reserved_quantity);

        $initialMovementsCount = StockMovement::query()->count();

        // Mark Delivered
        $response = $this->actingAs($this->admin)->post("/admin/deliveries/{$delivery->id}/delivered");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame($this->admin->id, $delivery->delivered_by);
        $this->assertEquals(
            (float) $delivery->cod_amount_to_collect,
            (float) $delivery->cod_collected_amount
        );
        $this->assertGreaterThan(1.0, (float) $delivery->cod_collected_amount);

        $order = $delivery->order->fresh();
        $this->assertSame(OrderStatus::AwaitingCustomerConfirmation, $order->status);
        $this->assertNotNull($order->customer_confirm_deadline_at);
        $this->assertEquals((float) $order->total, (float) $delivery->cod_collected_amount);

        // Stock remains 15 (NO secondary deduction)
        $inventory->refresh();
        $this->assertEquals(15.0, (float) $inventory->quantity);
        $this->assertEquals(0.0, (float) $inventory->reserved_quantity);

        // No new stock movement created
        $this->assertSame($initialMovementsCount, StockMovement::query()->count());
    }

    public function test_mark_failed_records_reason_and_keeps_stock_in_driver_custody(): void
    {
        $delivery = $this->createAssignedDelivery(quantity: 4.0);

        // Mark Failed
        $response = $this->actingAs($this->admin)->post("/admin/deliveries/{$delivery->id}/failed", [
            'failure_reason' => DeliveryFailureReason::CustomerUnavailable->value,
            'notes' => 'العميل مغلق هاتفه بعد عدة محاولات',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);

        $order = $delivery->order->fresh();
        $this->assertSame(OrderStatus::DeliveryFailed, $order->status);

        // Delivery failure recorded
        $this->assertSame(DeliveryFailureReason::CustomerUnavailable, $delivery->failure_reason_code);
        $this->assertSame('العميل مغلق هاتفه بعد عدة محاولات', $delivery->failure_notes);

        // Stock remains 16 (goods are in driver custody, NOT returned yet)
        $inventory = Inventory::query()->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertEquals(16.0, (float) $inventory->quantity);
        $this->assertEquals(0.0, (float) $inventory->reserved_quantity);
    }

    public function test_return_failed_delivery_to_warehouse_restores_inventory_and_records_delivery_return_in(): void
    {
        $delivery = $this->createAssignedDelivery(quantity: 5.0);

        // Mark Failed first
        $this->actingAs($this->admin)->post("/admin/deliveries/{$delivery->id}/failed", [
            'failure_reason' => DeliveryFailureReason::CustomerRefused->value,
            'notes' => 'العميل رفض استلام الطلب',
        ]);

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);

        // Return to warehouse
        $response = $this->actingAs($this->admin)->post("/admin/deliveries/{$delivery->id}/return-to-warehouse", [
            'notes' => 'تم فحص الطرد وإعادته للمستودع بحالة سليمة',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::ReturnedToWarehouse, $delivery->status);

        $order = $delivery->order->fresh();
        $this->assertSame(OrderStatus::ReturnedToWarehouse, $order->status);

        // Inventory restored: 15 -> 20!
        $inventory = Inventory::query()->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)->firstOrFail();
        $this->assertEquals(20.0, (float) $inventory->quantity);
        $this->assertEquals(0.0, (float) $inventory->reserved_quantity);

        // Movement: DELIVERY_RETURN / IN
        $returnMovement = StockMovement::query()
            ->where('movement_type', StockMovementType::DeliveryReturn)
            ->where('reference_id', $delivery->id)
            ->firstOrFail();

        $this->assertSame(StockMovementDirection::In, $returnMovement->direction);
        $this->assertEquals(5.0, (float) $returnMovement->quantity);

        // Run inventory reconcile command - must pass with 0 errors!
        $this->artisan('inventory:reconcile')
            ->assertExitCode(0);
    }

    private function createAssignedDelivery(float $quantity): Delivery
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
        $order = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

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
