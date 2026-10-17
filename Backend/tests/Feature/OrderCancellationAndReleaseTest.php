<?php

namespace Tests\Feature;

use App\Actions\CancelOrderAction;
use App\Actions\ConfirmOrderAction;
use App\DTOs\CancelOrderData;
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
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderCancellationAndReleaseTest extends TestCase
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
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'جمال عبد الله',
            'phone' => '773344556',
            'normalized_phone' => '+967773344556',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'جمال عبد الله',
            'contact_phone' => '773344556',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع صنعاء',
            'code' => 'WH-SANAA',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'ماركة', 'slug' => 'brand-c', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'قسم', 'slug' => 'cat-c', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج للإلغاء',
            'slug' => 'prod-cancel',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-CANCEL-01',
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
            'quantity' => 15.0000,
            'reserved_quantity' => 0.0000,
        ]);
    }

    public function test_cancelling_confirmed_order_releases_inventory_reservations(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-200001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 2500.00,
            'total' => 2500.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 2500.00,
        ]);

        $order->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'منتج للإلغاء',
            'sku_snapshot' => $this->variant->sku,
            'quantity' => 5,
            'base_quantity' => 5,
            'unit_price' => 500.00,
            'line_subtotal' => 2500.00,
            'line_total' => 2500.00,
            'base_currency_unit_price' => 500.00,
            'base_currency_line_total' => 2500.00,
        ]);

        // First confirm
        app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        $invBefore = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();
        $this->assertEquals(5.0000, (float) $invBefore->reserved_quantity);

        // Now cancel with reason
        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/cancel", [
            'cancellation_reason' => 'طلب العميل إلغاء الشحنة لعدم الحاجة',
        ]);
        $response->assertRedirect("/admin/orders/{$order->id}");

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('طلب العميل إلغاء الشحنة لعدم الحاجة', $order->cancellation_reason);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame($this->admin->id, $order->cancelled_by);

        // Invariant: Reservations marked Released
        $reservations = InventoryReservation::where('order_id', $order->id)->get();
        $this->assertCount(1, $reservations);
        $this->assertSame(ReservationStatus::Released, $reservations->first()->status);
        $this->assertNotNull($reservations->first()->released_at);

        // Invariant: reserved_quantity decreased back to 0
        $invAfter = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();

        $this->assertEquals(0.0000, (float) $invAfter->reserved_quantity);
        $this->assertEquals(15.0000, $invAfter->available_quantity);
    }

    public function test_cancellation_requires_reason(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-200002',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 1000.00,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/cancel", [
            'cancellation_reason' => '', // Empty
        ]);

        $response->assertSessionHasErrors(['cancellation_reason']);
        $order->refresh();
        $this->assertSame(OrderStatus::Draft, $order->status);
    }

    public function test_double_cancellation_is_safe(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-200003',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 1000.00,
        ]);

        $order->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'منتج للإلغاء',
            'sku_snapshot' => $this->variant->sku,
            'quantity' => 2,
            'base_quantity' => 2,
            'unit_price' => 500.00,
            'line_subtotal' => 1000.00,
            'line_total' => 1000.00,
            'base_currency_unit_price' => 500.00,
            'base_currency_line_total' => 1000.00,
        ]);

        app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        $cancelAction = app(CancelOrderAction::class);
        $dto = new CancelOrderData('إلغاء لمرة أولى');

        $cancelAction->execute($order, $dto, $this->admin->id);
        // Execute second cancellation
        $cancelAction->execute($order, $dto, $this->admin->id);

        $inv = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();

        // Reserved quantity never drops below 0
        $this->assertEquals(0.0000, (float) $inv->reserved_quantity);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
