<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
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
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderCreationAndDraftTest extends TestCase
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
            'name' => 'صالح محمد',
            'phone' => '771122334',
            'normalized_phone' => '+967771122334',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'صالح محمد',
            'contact_phone' => '771122334',
            'neighborhood' => 'حدة',
            'street' => 'شارع الستين',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name_ar' => 'قطعة',
            'name_en' => 'Piece',
            'decimal_places' => 0,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'علامة تجريبية', 'slug' => 'test-brand', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف تجريبي', 'slug' => 'test-cat', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج تجريبي',
            'name_en' => 'Test Product',
            'slug' => 'test-product',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-TEST-001',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 1500.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 50.0000,
            'reserved_quantity' => 0.0000,
        ]);
    }

    public function test_can_view_orders_index_and_create_pages(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/orders');
        $response->assertOk();
        $response->assertSee('طلبات المبيعات');

        $createResponse = $this->actingAs($this->admin)->get('/admin/orders/create');
        $createResponse->assertOk();
        $createResponse->assertSee('إنشاء طلب مبيعات جديد');
    }

    public function test_can_create_draft_order_without_reserving_inventory(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'discount_amount' => 100.00,
            'notes' => 'ملاحظة تجريبية على الطلب',
            'action' => 'draft',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 5,
                    'discount_amount' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/admin/orders', $payload);

        $order = Order::latest('id')->firstOrFail();
        $response->assertRedirect("/admin/orders/{$order->id}");

        $this->assertSame(OrderStatus::Draft, $order->status);
        $this->assertStringStartsWith('ORD-', $order->order_number);
        $this->assertSame('صالح محمد', $order->customer_name_snapshot);
        $this->assertSame('771122334', $order->customer_phone_snapshot);
        $this->assertEquals(7500.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->discount_amount);
        $this->assertEquals(7500.00, (float) $order->total);

        // Verify address snapshot
        $this->assertNotNull($order->deliveryAddress);
        $this->assertSame('حدة', $order->deliveryAddress->neighborhood);

        // Crucial Invariant: DRAFT has ZERO reservations and touches NO inventory
        $this->assertSame(0, InventoryReservation::where('order_id', $order->id)->count());

        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();

        $this->assertEquals(50.0000, (float) $inventory->quantity);
        $this->assertEquals(0.0000, (float) $inventory->reserved_quantity);
    }

    public function test_can_update_draft_order(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-000099',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 1500.00,
            'total' => 1500.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 1500.00,
        ]);

        $order->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'منتج تجريبي',
            'sku_snapshot' => 'SKU-TEST-001',
            'quantity' => 1,
            'base_quantity' => 1,
            'unit_price' => 1500.00,
            'line_subtotal' => 1500.00,
            'line_total' => 1500.00,
            'base_currency_unit_price' => 1500.00,
            'base_currency_line_total' => 1500.00,
        ]);

        $updatePayload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'discount_amount' => 500.00,
            'action' => 'draft',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 3, // updated to 3
                    'discount_amount' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put("/admin/orders/{$order->id}", $updatePayload);
        $response->assertRedirect("/admin/orders/{$order->id}");

        $order->refresh();
        $this->assertEquals(4500.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->discount_amount);
        $this->assertEquals(4500.00, (float) $order->total);
        $this->assertSame(3.0, (float) $order->items->first()->quantity);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
