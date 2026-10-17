<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\AssignOrderPickupDriverAction;
use App\Actions\AssignOrderToDriverAction;
use App\Actions\CancelPurchaseOrderItemAction;
use App\Actions\ChangePurchaseOrderSupplierAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\Actions\HoldPurchaseOrderAction;
use App\Actions\MarkPurchaseOrderCollectedAction;
use App\DTOs\AssignOrderToDriverData;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\OrderStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\PurchaseOrderStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PurchaseOrdersFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Driver $driver;

    private Warehouse $warehouse;

    private Currency $currency;

    private Customer $customer;

    private CustomerAddress $address;

    private Supplier $supplierA;

    private Supplier $supplierB;

    private ProductVariant $stockedVariant;

    private ProductVariant $onDemandA;

    private ProductVariant $onDemandB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->admin = $this->userWithRole('admin');

        $this->driver = Driver::create([
            'driver_code' => 'DRV-PO-1',
            'name' => 'مندوب متعدد المحطات',
            'phone' => '770011223',
            'vehicle_type' => 'سيارة',
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create([
            'code' => 'PO-SUP-A',
            'name' => 'مورد ني جي',
            'phone' => '771111111',
            'is_active' => true,
        ]);

        $this->supplierB = Supplier::create([
            'code' => 'PO-SUP-B',
            'name' => 'مورد بيوتفي',
            'phone' => '772222222',
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
            'name' => 'عميل طلبات الشراء',
            'phone' => '733344455',
            'normalized_phone' => '+967733344455',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => $this->customer->name,
            'contact_phone' => $this->customer->phone,
            'street' => 'شارع الاختبار',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-PO',
            'name_ar' => 'مستودع الاختبار',
            'is_active' => true,
        ]);

        Unit::create([
            'code' => 'PCS',
            'name_ar' => 'قطعة',
            'decimal_places' => 0,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'براند', 'slug' => 'brand-po', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'فئة', 'slug' => 'cat-po', 'is_active' => true]);

        $stockedProduct = Product::create([
            'name_ar' => 'منتج مخزني',
            'slug' => 'product-stocked-po',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'supplier_id' => $this->supplierA->id,
        ]);

        $this->stockedVariant = ProductVariant::create([
            'product_id' => $stockedProduct->id,
            'sku' => 'SKU-STOCK-PO',
            'status' => 'active',
            'is_default' => true,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
            'cost_price' => 50,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->stockedVariant->id,
            'currency_id' => $this->currency->id,
            'price' => 100,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->stockedVariant->id,
            'quantity' => 20,
            'reserved_quantity' => 0,
        ]);

        $productA = Product::create([
            'name_ar' => 'منتج ني جي',
            'slug' => 'product-ng-po',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'supplier_id' => $this->supplierA->id,
        ]);

        $this->onDemandA = ProductVariant::create([
            'product_id' => $productA->id,
            'sku' => 'SKU-OD-A',
            'status' => 'active',
            'is_default' => true,
            'fulfillment_mode' => ProductVariantFulfillmentMode::OnDemand,
            'supplier_id' => $this->supplierA->id,
            'cost_price' => 30,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->onDemandA->id,
            'currency_id' => $this->currency->id,
            'price' => 80,
            'is_active' => true,
        ]);

        $productB = Product::create([
            'name_ar' => 'منتج بيوتفي',
            'slug' => 'product-bt-po',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'supplier_id' => $this->supplierB->id,
        ]);

        $this->onDemandB = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'SKU-OD-B',
            'status' => 'active',
            'is_default' => true,
            'fulfillment_mode' => ProductVariantFulfillmentMode::OnDemand,
            'supplier_id' => $this->supplierB->id,
            'cost_price' => 40,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->onDemandB->id,
            'currency_id' => $this->currency->id,
            'price' => 90,
            'is_active' => true,
        ]);
    }

    public function test_confirm_creates_one_purchase_order_per_supplier(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(productVariantId: $this->stockedVariant->id, quantity: 1.0),
            new OrderItemData(productVariantId: $this->onDemandA->id, quantity: 2.0),
            new OrderItemData(productVariantId: $this->onDemandB->id, quantity: 1.0),
        ]);

        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        $this->assertSame(2, $confirmed->purchaseOrders()->count());
        $this->assertTrue($confirmed->purchaseOrders()->where('supplier_id', $this->supplierA->id)->exists());
        $this->assertTrue($confirmed->purchaseOrders()->where('supplier_id', $this->supplierB->id)->exists());
    }

    public function test_assign_blocked_until_all_purchase_orders_collected(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(productVariantId: $this->onDemandA->id, quantity: 1.0),
            new OrderItemData(productVariantId: $this->onDemandB->id, quantity: 1.0),
        ]);

        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
        app(AssignOrderPickupDriverAction::class)->execute($confirmed, $this->driver->id, $this->admin->id);
        $poA = $confirmed->purchaseOrders()->where('supplier_id', $this->supplierA->id)->firstOrFail();
        app(MarkPurchaseOrderCollectedAction::class)->execute($poA, $this->admin->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('لم يتم تأكيد استلامها من المورد بعد');

        app(AssignOrderToDriverAction::class)->execute(
            $confirmed,
            new AssignOrderToDriverData(driverId: $this->driver->id, notes: null),
            $this->admin->id
        );
    }

    public function test_collect_blocked_until_pickup_driver_assigned(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(productVariantId: $this->onDemandA->id, quantity: 1.0),
        ]);

        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
        $po = $confirmed->purchaseOrders()->firstOrFail();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('لا يمكن استلام الطلب من المورد قبل إسناد مندوب التوصيل للطلب.');

        app(MarkPurchaseOrderCollectedAction::class)->execute($po, $this->admin->id);
    }

    public function test_multistop_pickup_then_auto_ready_for_delivery(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(productVariantId: $this->stockedVariant->id, quantity: 1.0),
            new OrderItemData(productVariantId: $this->onDemandA->id, quantity: 1.0),
            new OrderItemData(productVariantId: $this->onDemandB->id, quantity: 1.0),
        ]);

        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
        app(AssignOrderPickupDriverAction::class)->execute($confirmed, $this->driver->id, $this->admin->id);

        $this->assertSame($this->driver->id, $confirmed->fresh()->pickup_driver_id);
        $this->assertSame(OrderStatus::Confirmed, $confirmed->fresh()->status);

        foreach ($confirmed->purchaseOrders()->get() as $po) {
            $this->assertSame(PurchaseOrderStatus::Assigned, $po->fresh()->status);
            app(MarkPurchaseOrderCollectedAction::class)->execute($po, $this->admin->id);
        }

        $this->assertSame(OrderStatus::AssignedToDriver, $confirmed->fresh()->status);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $confirmed->id,
            'driver_id' => $this->driver->id,
        ]);
    }

    public function test_change_supplier_moves_whole_purchase_order(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(productVariantId: $this->onDemandA->id, quantity: 1.0),
        ]);

        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
        $po = $confirmed->purchaseOrders()->firstOrFail();

        $updated = app(ChangePurchaseOrderSupplierAction::class)->execute(
            $po,
            $this->supplierB->id,
            null,
            $this->admin->id
        );

        $this->assertSame($this->supplierB->id, $updated->supplier_id);
        $this->assertSame(
            $this->supplierB->id,
            (int) $confirmed->items()->where('product_variant_id', $this->onDemandA->id)->value('sourcing_supplier_id_snapshot')
        );
    }

    public function test_cancel_missing_item_then_ready_when_remaining_collected(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(productVariantId: $this->onDemandA->id, quantity: 1.0),
            new OrderItemData(productVariantId: $this->onDemandB->id, quantity: 1.0),
        ]);

        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
        app(AssignOrderPickupDriverAction::class)->execute($confirmed, $this->driver->id, $this->admin->id);

        $poA = $confirmed->purchaseOrders()->where('supplier_id', $this->supplierA->id)->firstOrFail();
        $poB = $confirmed->purchaseOrders()->where('supplier_id', $this->supplierB->id)->firstOrFail();

        app(CancelPurchaseOrderItemAction::class)->execute(
            $poB->items()->firstOrFail(),
            'الصنف غير متوفر عند المورد',
            $this->admin->id
        );

        $this->assertSame(PurchaseOrderStatus::Cancelled, $poB->fresh()->status);
        app(MarkPurchaseOrderCollectedAction::class)->execute($poA, $this->admin->id);

        $this->assertSame(OrderStatus::AssignedToDriver, $confirmed->fresh()->status);
    }

    public function test_hold_blocks_ready_for_delivery(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(productVariantId: $this->onDemandA->id, quantity: 1.0),
        ]);

        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
        $po = $confirmed->purchaseOrders()->firstOrFail();

        app(HoldPurchaseOrderAction::class)->execute($po, 'صنف ناقص عند المورد', $this->admin->id);
        $this->assertSame(PurchaseOrderStatus::OnHold, $po->fresh()->status);

        $this->expectException(DomainException::class);
        app(AssignOrderToDriverAction::class)->execute(
            $confirmed,
            new AssignOrderToDriverData(driverId: $this->driver->id, notes: null),
            $this->admin->id
        );
    }

    public function test_purchase_orders_index_page(): void
    {
        $order = $this->createDraftOrder([
            new OrderItemData(productVariantId: $this->onDemandA->id, quantity: 1.0),
        ]);
        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
        $po = $confirmed->purchaseOrders()->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.purchase-orders.index'))
            ->assertOk()
            ->assertSee($po->po_number)
            ->assertSee($this->supplierA->name);

        $this->actingAs($this->admin)
            ->get(route('admin.purchase-orders.show', $po))
            ->assertOk()
            ->assertSee($po->po_number);
    }

    /** @param list<OrderItemData> $items */
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
