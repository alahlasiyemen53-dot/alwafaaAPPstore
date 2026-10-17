<?php

namespace Tests\Feature;

use App\Actions\ConfirmOrderAction;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
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

final class OrderConfirmationAndReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private CustomerAddress $address;

    private Warehouse $warehouse;

    private Currency $currency;

    private ProductVariant $variant1;

    private ProductVariant $variant2;

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
            'name' => 'محمد العلي',
            'phone' => '772233445',
            'normalized_phone' => '+967772233445',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'العمل',
            'contact_name' => 'محمد العلي',
            'contact_phone' => '772233445',
            'neighborhood' => 'الصافية',
            'street' => 'شارع تعز',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'code' => 'BOX',
            'name_ar' => 'كرتون',
            'name_en' => 'Box',
            'decimal_places' => 0,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'براند', 'slug' => 'brand-test', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف', 'slug' => 'cat-test', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج مبيعات',
            'slug' => 'sales-prod',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-ITEM-1',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->variant2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-ITEM-2',
            'status' => 'active',
            'is_default' => false,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant1->id,
            'currency_id' => $this->currency->id,
            'price' => 1000.00,
            'is_active' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant2->id,
            'currency_id' => $this->currency->id,
            'price' => 2000.00,
            'is_active' => true,
        ]);

        // Initial inventory setup
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant1->id,
            'quantity' => 10.0000,
            'reserved_quantity' => 0.0000,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant2->id,
            'quantity' => 20.0000,
            'reserved_quantity' => 0.0000,
        ]);
    }

    public function test_confirming_order_reserves_stock_without_physical_deduction(): void
    {
        // 1. Create a draft order
        $order = Order::create([
            'order_number' => 'ORD-100001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 8000.00,
            'total' => 8000.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 8000.00,
        ]);

        $order->items()->create([
            'product_variant_id' => $this->variant1->id,
            'product_name_snapshot' => 'منتج مبيعات 1',
            'sku_snapshot' => $this->variant1->sku,
            'quantity' => 4,
            'base_quantity' => 4,
            'unit_price' => 1000.00,
            'line_subtotal' => 4000.00,
            'line_total' => 4000.00,
            'base_currency_unit_price' => 1000.00,
            'base_currency_line_total' => 4000.00,
        ]);

        $order->items()->create([
            'product_variant_id' => $this->variant2->id,
            'product_name_snapshot' => 'منتج مبيعات 2',
            'sku_snapshot' => $this->variant2->sku,
            'quantity' => 2,
            'base_quantity' => 2,
            'unit_price' => 2000.00,
            'line_subtotal' => 4000.00,
            'line_total' => 4000.00,
            'base_currency_unit_price' => 2000.00,
            'base_currency_line_total' => 4000.00,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/confirm");
        $response->assertRedirect("/admin/orders/{$order->id}");

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertSame($this->admin->id, $order->confirmed_by);

        // Verify reservations created
        $reservations = InventoryReservation::where('order_id', $order->id)->get();
        $this->assertCount(2, $reservations);
        $this->assertTrue($reservations->every(fn ($r) => $r->status === ReservationStatus::Active));

        // CRITICAL INVARIANT: reserved_quantity increased, physical quantity UNCHANGED
        $inv1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant1->id)
            ->firstOrFail();

        $this->assertEquals(10.0000, (float) $inv1->quantity); // Unchanged!
        $this->assertEquals(4.0000, (float) $inv1->reserved_quantity); // Increased!
        $this->assertEquals(6.0000, $inv1->available_quantity); // Available = 10 - 4 = 6

        $inv2 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant2->id)
            ->firstOrFail();

        $this->assertEquals(20.0000, (float) $inv2->quantity); // Unchanged!
        $this->assertEquals(2.0000, (float) $inv2->reserved_quantity); // Increased!
        $this->assertEquals(18.0000, $inv2->available_quantity); // Available = 20 - 2 = 18

        // CRITICAL INVARIANT: NO StockMovement recorded (no physical SALE movement)
        $this->assertSame(0, StockMovement::count());
    }

    public function test_insufficient_stock_blocks_confirmation_completely(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-100002',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 50000.00,
            'total' => 50000.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 50000.00,
        ]);

        // Request 15 units when only 10 exist
        $order->items()->create([
            'product_variant_id' => $this->variant1->id,
            'product_name_snapshot' => 'منتج مبيعات 1',
            'sku_snapshot' => $this->variant1->sku,
            'quantity' => 15,
            'base_quantity' => 15,
            'unit_price' => 1000.00,
            'line_subtotal' => 15000.00,
            'line_total' => 15000.00,
            'base_currency_unit_price' => 1000.00,
            'base_currency_line_total' => 15000.00,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/confirm");
        $response->assertSessionHas('error');

        $order->refresh();
        $this->assertSame(OrderStatus::Draft, $order->status); // Remains Draft!
        $this->assertSame(0, InventoryReservation::where('order_id', $order->id)->count());

        $inv1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant1->id)
            ->firstOrFail();

        $this->assertEquals(0.0000, (float) $inv1->reserved_quantity);
    }

    public function test_double_confirmation_is_idempotent(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-100003',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 2000.00,
            'total' => 2000.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 2000.00,
        ]);

        $order->items()->create([
            'product_variant_id' => $this->variant1->id,
            'product_name_snapshot' => 'منتج مبيعات 1',
            'sku_snapshot' => $this->variant1->sku,
            'quantity' => 2,
            'base_quantity' => 2,
            'unit_price' => 1000.00,
            'line_subtotal' => 2000.00,
            'line_total' => 2000.00,
            'base_currency_unit_price' => 1000.00,
            'base_currency_line_total' => 2000.00,
        ]);

        $action = app(ConfirmOrderAction::class);
        $action->execute($order, $this->admin->id);

        // Execute again directly
        $action->execute($order, $this->admin->id);

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);

        // Only 1 reservation record, not 2
        $this->assertSame(1, InventoryReservation::where('order_id', $order->id)->count());

        $inv1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant1->id)
            ->firstOrFail();

        // Reserved quantity is strictly 2.0000, not 4.0000
        $this->assertEquals(2.0000, (float) $inv1->reserved_quantity);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
