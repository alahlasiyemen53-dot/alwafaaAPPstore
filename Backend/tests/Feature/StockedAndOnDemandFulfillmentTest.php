<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\AssignOrderPickupDriverAction;
use App\Actions\AssignOrderToDriverAction;
use App\Actions\AssignSourcingToDriverAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\ConfirmSourcingCollectedAction;
use App\Actions\CreateOrderAction;
use App\DTOs\AssignOrderToDriverData;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\OrderStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ReservationStatus;
use App\Enums\SourcingStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderSourcingRequirement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\Catalog\ProductAvailabilityService;
use Database\Seeders\AuthorizationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StockedAndOnDemandFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Driver $driver;

    private Driver $sourcingDriver;

    private Warehouse $warehouse;

    private Currency $currency;

    private Customer $customer;

    private CustomerAddress $address;

    private Supplier $activeSupplier;

    private Supplier $inactiveSupplier;

    private Product $product;

    private Product $onDemandProduct;

    private ProductVariant $stockedVariant;

    private ProductVariant $onDemandVariant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');

        $this->driver = Driver::create([
            'driver_code' => 'DRV-DELIVERY-1',
            'name' => 'مندوب التوصيل الأساسي',
            'phone' => '771122334',
            'vehicle_type' => 'دراجة نارية',
            'is_active' => true,
        ]);

        $this->sourcingDriver = Driver::create([
            'driver_code' => 'DRV-SOURCING-1',
            'name' => 'مندوب التوفير الميداني',
            'phone' => '779988776',
            'vehicle_type' => 'سيارة',
            'is_active' => true,
        ]);

        $this->activeSupplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'مؤسسة التوفير السريع',
            'phone' => '773344556',
            'is_active' => true,
        ]);

        $this->inactiveSupplier = Supplier::create([
            'code' => 'SUP-002',
            'name' => 'مورد متوقف عن العمل',
            'phone' => '774455667',
            'is_active' => false,
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
            'street' => 'شارع حدة',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي - صنعاء',
            'code' => 'WH-SNA-MAIN',
            'is_active' => true,
        ]);

        Unit::create([
            'code' => 'PCS',
            'name_ar' => 'قطعة',
            'decimal_places' => 0,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'علامة تجارية', 'slug' => 'brand-sprint', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف رئيسي', 'slug' => 'cat-sprint', 'is_active' => true]);

        $this->product = Product::create([
            'name_ar' => 'منتج متكامل الاختبار (مخزني)',
            'slug' => 'sprint-product-stocked',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->onDemandProduct = Product::create([
            'name_ar' => 'منتج متكامل الاختبار (توفير عند الطلب)',
            'slug' => 'sprint-product-ondemand',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        // Stocked variant with 10 units in warehouse
        $this->stockedVariant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SKU-STOCKED-01',
            'status' => 'active',
            'is_default' => true,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
            'supplier_id' => null,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->stockedVariant->id,
            'currency_id' => $this->currency->id,
            'price' => 5000.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->stockedVariant->id,
            'quantity' => 10.0000,
            'reserved_quantity' => 0.0000,
        ]);

        // On-demand variant with 0 units in warehouse and active supplier
        $this->onDemandVariant = ProductVariant::create([
            'product_id' => $this->onDemandProduct->id,
            'sku' => 'SKU-ONDEMAND-01',
            'status' => 'active',
            'is_default' => true,
            'fulfillment_mode' => ProductVariantFulfillmentMode::OnDemand,
            'supplier_id' => $this->activeSupplier->id,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->onDemandVariant->id,
            'currency_id' => $this->currency->id,
            'price' => 7500.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->onDemandVariant->id,
            'quantity' => 0.0000,
            'reserved_quantity' => 0.0000,
        ]);
    }

    /**
     * Scenario A: Catalog configuration of STOCKED variant without supplier succeeds.
     * Available stock check works as before.
     */
    public function test_scenario_a_stocked_variant_configuration_and_availability(): void
    {
        $newProduct = Product::create([
            'name_ar' => 'منتج جديد لاختبار إضافة متغير',
            'slug' => 'product-new-variant-test',
            'brand_id' => $this->product->brand_id,
            'category_id' => $this->product->category_id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/products/{$newProduct->id}/variants", [
            'sku' => 'SKU-STOCKED-NEW',
            'price' => 1200.00,
            'status' => 'active',
            'fulfillment_mode' => 'stocked',
            'supplier_id' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $created = ProductVariant::query()->where('sku', 'SKU-STOCKED-NEW')->firstOrFail();
        $this->assertSame(ProductVariantFulfillmentMode::Stocked, $created->fulfillment_mode);
        $this->assertNull($created->supplier_id);
        $this->assertTrue($created->isStocked());
        $this->assertFalse($created->isOnDemand());

        // Test availability service
        $availabilityService = app(ProductAvailabilityService::class);

        // Variant with 10 units in stock is sellable for requested 5
        $res = $availabilityService->evaluate($this->stockedVariant, 5.0, $this->warehouse->id);
        $this->assertTrue($res->isSellable);
        $this->assertSame(ProductVariantFulfillmentMode::Stocked, $res->fulfillmentMode);
        $this->assertSame(10.0, $res->warehouseAvailableStock);

        // Variant with 10 units in stock is not sellable for requested 15
        $resExcess = $availabilityService->evaluate($this->stockedVariant, 15.0, $this->warehouse->id);
        $this->assertFalse($resExcess->isSellable);
        $this->assertNotNull($resExcess->reason);
    }

    /**
     * Scenario B: Catalog configuration of ON_DEMAND variant without supplier fails validation.
     */
    public function test_scenario_b_ondemand_variant_without_supplier_fails_validation(): void
    {
        $response = $this->actingAs($this->admin)->post("/admin/products/{$this->product->id}/variants", [
            'sku' => 'SKU-ONDEMAND-NOSUPP',
            'price' => 2000.00,
            'status' => 'active',
            'fulfillment_mode' => 'on_demand',
            'supplier_id' => null,
        ]);

        $response->assertSessionHasErrors(['supplier_id']);
        $this->assertDatabaseMissing('product_variants', ['sku' => 'SKU-ONDEMAND-NOSUPP']);
    }

    /**
     * Scenario C: Catalog configuration of ON_DEMAND variant with inactive supplier fails validation.
     */
    public function test_scenario_c_ondemand_variant_with_inactive_supplier_fails_validation(): void
    {
        $response = $this->actingAs($this->admin)->post("/admin/products/{$this->product->id}/variants", [
            'sku' => 'SKU-ONDEMAND-INACTIVESUPP',
            'price' => 3000.00,
            'status' => 'active',
            'fulfillment_mode' => 'on_demand',
            'supplier_id' => $this->inactiveSupplier->id,
        ]);

        $response->assertSessionHasErrors(['supplier_id']);
        $this->assertDatabaseMissing('product_variants', ['sku' => 'SKU-ONDEMAND-INACTIVESUPP']);
    }

    /**
     * Scenario D: Centralized sellability evaluation returns sellable for ON_DEMAND variant
     * with active supplier even when warehouse inventory is zero, with mode on_demand.
     */
    public function test_scenario_d_ondemand_variant_is_sellable_with_zero_warehouse_stock(): void
    {
        $availabilityService = app(ProductAvailabilityService::class);

        // Requested quantity: 5 units with 0 warehouse inventory
        $res = $availabilityService->evaluate($this->onDemandVariant, 5.0, $this->warehouse->id);

        $this->assertTrue($res->isSellable);
        $this->assertSame(ProductVariantFulfillmentMode::OnDemand, $res->fulfillmentMode);
        $this->assertSame(0.0, $res->warehouseAvailableStock);
        $this->assertSame($this->activeSupplier->id, $res->supplierId);
        $this->assertSame($this->activeSupplier->name, $res->supplierName);

        // If supplier is deactivated, it is no longer sellable
        $this->activeSupplier->update(['is_active' => false]);
        $resDeactivated = $availabilityService->evaluate($this->onDemandVariant->fresh(), 5.0, $this->warehouse->id);
        $this->assertFalse($resDeactivated->isSellable);
        $this->assertStringContainsString('معطل', (string) $resDeactivated->reason);
    }

    /**
     * Scenario E: Order confirmation creates OrderSourcingRequirement for ON_DEMAND items
     * without creating InventoryReservation or negative stock.
     */
    public function test_scenario_e_pure_ondemand_order_confirmation_creates_sourcing_and_no_reservations(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(
                productVariantId: $this->onDemandVariant->id,
                quantity: 3.0,
            ),
        ]);

        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        $this->assertSame(OrderStatus::Confirmed, $confirmedOrder->status);

        // Item snapshot verification
        $item = $confirmedOrder->items()->firstOrFail();
        $this->assertSame(ProductVariantFulfillmentMode::OnDemand, $item->fulfillment_mode_snapshot);
        $this->assertSame($this->activeSupplier->id, $item->sourcing_supplier_id_snapshot);
        $this->assertSame($this->activeSupplier->name, $item->supplier_name_snapshot);

        // Sourcing requirement created in PENDING status
        $this->assertDatabaseCount('order_sourcing_requirements', 1);
        $sourcing = OrderSourcingRequirement::query()->where('order_id', $confirmedOrder->id)->firstOrFail();
        $this->assertSame($item->id, $sourcing->order_item_id);
        $this->assertSame($this->onDemandVariant->id, $sourcing->product_variant_id);
        $this->assertSame($this->activeSupplier->id, $sourcing->supplier_id);
        $this->assertEquals(3.0, (float) $sourcing->quantity_requested);
        $this->assertSame(SourcingStatus::Pending, $sourcing->status);

        // Zero reservations created
        $this->assertDatabaseCount('inventory_reservations', 0);

        // Inventory remains 0 quantity, 0 reserved
        $inventory = Inventory::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->onDemandVariant->id)
            ->firstOrFail();

        $this->assertSame('0.0000', (string) $inventory->quantity);
        $this->assertSame('0.0000', (string) $inventory->reserved_quantity);
    }

    /**
     * Scenario F: Order confirmation for mixed order creates reservations only for STOCKED items
     * and sourcing requirements for ON_DEMAND items.
     */
    public function test_scenario_f_mixed_order_confirmation_splits_reservations_and_sourcing(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(
                productVariantId: $this->stockedVariant->id,
                quantity: 4.0,
            ),
            new OrderItemData(
                productVariantId: $this->onDemandVariant->id,
                quantity: 2.0,
            ),
        ]);

        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        $this->assertSame(OrderStatus::Confirmed, $confirmedOrder->status);

        // 1. Stocked item has reservation for 4.0
        $this->assertDatabaseCount('inventory_reservations', 1);
        $reservation = InventoryReservation::query()->where('order_id', $confirmedOrder->id)->firstOrFail();
        $this->assertSame($this->stockedVariant->id, $reservation->product_variant_id);
        $this->assertEquals(4.0, (float) $reservation->quantity);
        $this->assertSame(ReservationStatus::Active, $reservation->status);

        // Stocked inventory reserved_quantity increased to 4.0, physical quantity still 10.0
        $stockedInv = Inventory::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->stockedVariant->id)
            ->firstOrFail();
        $this->assertSame('10.0000', (string) $stockedInv->quantity);
        $this->assertSame('4.0000', (string) $stockedInv->reserved_quantity);

        // 2. On-demand item has sourcing requirement for 2.0 and no reservation
        $this->assertDatabaseCount('order_sourcing_requirements', 1);
        $sourcing = OrderSourcingRequirement::query()->where('order_id', $confirmedOrder->id)->firstOrFail();
        $this->assertSame($this->onDemandVariant->id, $sourcing->product_variant_id);
        $this->assertEquals(2.0, (float) $sourcing->quantity_requested);
        $this->assertSame(SourcingStatus::Pending, $sourcing->status);
    }

    /**
     * Scenario G: Order assignment to delivery driver fails if any ON_DEMAND item has not reached COLLECTED status.
     */
    public function test_scenario_g_order_assignment_to_driver_fails_when_sourcing_not_collected(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(
                productVariantId: $this->stockedVariant->id,
                quantity: 2.0,
            ),
            new OrderItemData(
                productVariantId: $this->onDemandVariant->id,
                quantity: 1.0,
            ),
        ]);

        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        $assignAction = app(AssignOrderToDriverAction::class);
        $dto = new AssignOrderToDriverData(driverId: $this->driver->id, notes: null);

        // Attempt while sourcing requirement is PENDING
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('لم يتم تأكيد استلامها من المورد بعد');
        $assignAction->execute($confirmedOrder, $dto, $this->admin->id);
    }

    /**
     * Scenario H: Order assignment to delivery driver succeeds after all ON_DEMAND items are COLLECTED,
     * and stock movements (Sale/Out) are created strictly for STOCKED items.
     */
    public function test_scenario_h_order_assignment_succeeds_after_collection_and_deducts_only_stocked(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(
                productVariantId: $this->stockedVariant->id,
                quantity: 3.0,
            ),
            new OrderItemData(
                productVariantId: $this->onDemandVariant->id,
                quantity: 2.0,
            ),
        ]);

        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        // Find sourcing requirement and progress it to COLLECTED (requires order pickup driver first)
        /** @var OrderSourcingRequirement $sourcing */
        $sourcing = $confirmedOrder->sourcingRequirements()->firstOrFail();
        app(AssignOrderPickupDriverAction::class)->execute($confirmedOrder, $this->driver->id, $this->admin->id);
        app(AssignSourcingToDriverAction::class)->execute($sourcing, $this->sourcingDriver->id, $this->admin->id);
        app(ConfirmSourcingCollectedAction::class)->execute($sourcing, $this->admin->id);

        $this->assertSame(SourcingStatus::Collected, $sourcing->fresh()->status);

        // Pickup driver auto-promotes order to delivery after all supplier collections
        $this->assertSame(OrderStatus::AssignedToDriver, $confirmedOrder->fresh()->status);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $confirmedOrder->id,
            'driver_id' => $this->driver->id,
        ]);

        // Stocked variant: reservation CONSUMED, warehouse quantity deducted 10 -> 7, reserved 3 -> 0
        $stockedInv = Inventory::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->stockedVariant->id)
            ->firstOrFail();
        $this->assertSame('7.0000', (string) $stockedInv->quantity);
        $this->assertSame('0.0000', (string) $stockedInv->reserved_quantity);

        // Exactly 1 StockMovement created (Sale/Out for the stocked variant)
        $this->assertDatabaseCount('stock_movements', 1);
        $movement = StockMovement::query()->where('reference_id', $confirmedOrder->id)->firstOrFail();
        $this->assertSame($this->warehouse->id, $movement->warehouse_id);
        $this->assertSame($this->stockedVariant->id, $movement->product_variant_id);
        $this->assertSame(StockMovementType::Sale, $movement->movement_type);
        $this->assertSame(StockMovementDirection::Out, $movement->direction);
        $this->assertEquals(3.0, (float) $movement->quantity);

        // On-demand variant: inventory untouched at 0.0 / 0.0, zero stock movements
        $onDemandInv = Inventory::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->onDemandVariant->id)
            ->firstOrFail();
        $this->assertSame('0.0000', (string) $onDemandInv->quantity);
        $this->assertSame('0.0000', (string) $onDemandInv->reserved_quantity);
    }

    /**
     * Scenario I: Pure ON_DEMAND order confirmation creates delivery upon driver assignment
     * without consuming inventory or creating stock movements.
     */
    public function test_scenario_i_pure_ondemand_order_lifecycle(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(
                productVariantId: $this->onDemandVariant->id,
                quantity: 5.0,
            ),
        ]);

        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        /** @var OrderSourcingRequirement $sourcing */
        $sourcing = $confirmedOrder->sourcingRequirements()->firstOrFail();
        app(AssignOrderPickupDriverAction::class)->execute($confirmedOrder, $this->driver->id, $this->admin->id);
        app(ConfirmSourcingCollectedAction::class)->execute($sourcing, $this->admin->id);

        $this->assertSame(OrderStatus::AssignedToDriver, $confirmedOrder->fresh()->status);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $confirmedOrder->id,
            'driver_id' => $this->driver->id,
        ]);

        // Pure on-demand creates zero stock movements
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
    }

    /**
     * Scenario J: Sourcing task assignment to driver is distinct from order assignment to driver
     * and does not prematurely advance order custody or create stock movements.
     * Also tests web endpoints for the Sourcing Queue.
     */
    public function test_scenario_j_sourcing_queue_web_controller_lifecycle(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(
                productVariantId: $this->onDemandVariant->id,
                quantity: 2.0,
            ),
        ]);

        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
        $sourcing = $confirmedOrder->sourcingRequirements()->firstOrFail();

        // 1. Visit Sourcing Queue index page
        $indexResponse = $this->actingAs($this->admin)->get(route('admin.sourcing.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee($order->order_number);
        $indexResponse->assertSee($this->activeSupplier->name);

        // 2. Assign Sourcing Driver via Web Route
        $assignResponse = $this->actingAs($this->admin)->post(route('admin.sourcing.assign-driver', $sourcing), [
            'driver_id' => $this->sourcingDriver->id,
            'notes' => 'يرجى التوجه إلى المورد واستلام القطع',
        ]);
        $assignResponse->assertRedirect();
        $assignResponse->assertSessionHas('success');

        $sourcing->refresh();
        $this->assertSame(SourcingStatus::Assigned, $sourcing->status);
        $this->assertSame($this->sourcingDriver->id, $sourcing->driver_id);
        $this->assertNotNull($sourcing->assigned_at);

        // Order custody MUST NOT have advanced (remains Confirmed)
        $this->assertSame(OrderStatus::Confirmed, $confirmedOrder->fresh()->status);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        // 3. Collect without order pickup driver must be rejected
        $blockedCollect = $this->actingAs($this->admin)->post(route('admin.sourcing.confirm-collected', $sourcing));
        $blockedCollect->assertRedirect();
        $blockedCollect->assertSessionHas('error');
        $this->assertSame(SourcingStatus::Assigned, $sourcing->fresh()->status);

        // 4. Assign pickup driver on the order, then collect succeeds
        app(AssignOrderPickupDriverAction::class)->execute($confirmedOrder, $this->driver->id, $this->admin->id);

        $collectResponse = $this->actingAs($this->admin)->post(route('admin.sourcing.confirm-collected', $sourcing));
        $collectResponse->assertRedirect();
        $collectResponse->assertSessionHas('success');

        $sourcing->refresh();
        $this->assertSame(SourcingStatus::Collected, $sourcing->status);
        $this->assertNotNull($sourcing->collected_at);

        // After all POs collected with a pickup driver, order auto-promotes to delivery
        $this->assertSame(OrderStatus::AssignedToDriver, $confirmedOrder->fresh()->status);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $confirmedOrder->id,
            'driver_id' => $this->driver->id,
        ]);
    }

    /**
     * Scenario K: Safe Mode Change Rule (Section 50)
     * Cannot switch STOCKED -> ON_DEMAND if active reservations exist.
     * Cannot switch ON_DEMAND -> STOCKED if pending or assigned sourcing requirements exist.
     */
    public function test_scenario_k_safe_mode_change_validation(): void
    {
        // 1. Create order reserving stockedVariant
        $order = $this->createDraftOrder([
            new OrderItemData(
                productVariantId: $this->stockedVariant->id,
                quantity: 2.0,
            ),
        ]);
        app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        // Attempt to change stockedVariant to on_demand should fail
        $response = $this->actingAs($this->admin)->put("/admin/products/{$this->product->id}/variants/{$this->stockedVariant->id}", [
            'sku' => $this->stockedVariant->sku,
            'price' => 5000.00,
            'status' => 'active',
            'fulfillment_mode' => 'on_demand',
            'supplier_id' => $this->activeSupplier->id,
        ]);

        $response->assertSessionHasErrors(['fulfillment_mode']);

        // 2. Create order with onDemandVariant
        $order2 = $this->createDraftOrder([
            new OrderItemData(
                productVariantId: $this->onDemandVariant->id,
                quantity: 1.0,
            ),
        ]);
        app(ConfirmOrderAction::class)->execute($order2, $this->admin->id);

        // Attempt to change onDemandVariant to stocked should fail
        $response2 = $this->actingAs($this->admin)->put("/admin/products/{$this->onDemandProduct->id}/variants/{$this->onDemandVariant->id}", [
            'sku' => $this->onDemandVariant->sku,
            'price' => 7500.00,
            'status' => 'active',
            'fulfillment_mode' => 'stocked',
            'supplier_id' => null,
        ]);

        $response2->assertSessionHasErrors(['fulfillment_mode']);
    }

    /**
     * @param  array<OrderItemData>  $items
     */
    private function createDraftOrder(array $items): Order
    {
        $data = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            items: $items,
            notes: null,
        );

        return app(CreateOrderAction::class)->execute($data, $this->admin->id);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
