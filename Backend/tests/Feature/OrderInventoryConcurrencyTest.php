<?php

namespace Tests\Feature;

use App\Actions\ConfirmOrderAction;
use App\Enums\OrderStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Order;
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

final class OrderInventoryConcurrencyTest extends TestCase
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
            'name' => 'عميل التنافس',
            'phone' => '775566778',
            'normalized_phone' => '+967775566778',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'العنوان',
            'contact_name' => 'عميل التنافس',
            'contact_phone' => '775566778',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع التنافس',
            'code' => 'WH-CONC',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'ماركة', 'slug' => 'brand-conc', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'قسم', 'slug' => 'cat-conc', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج تنافسي محدود',
            'slug' => 'prod-conc',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-LIMITED-01',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 5000.00,
            'is_active' => true,
        ]);

        // Exactly 10 units in stock
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10.0000,
            'reserved_quantity' => 0.0000,
        ]);
    }

    public function test_competing_orders_prevent_overselling_under_concurrency(): void
    {
        // Order A requests 7 units
        $orderA = Order::create([
            'order_number' => 'ORD-CONC-01',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 35000.00,
            'total' => 35000.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 35000.00,
        ]);
        $orderA->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'منتج تنافسي محدود',
            'sku_snapshot' => $this->variant->sku,
            'quantity' => 7,
            'base_quantity' => 7,
            'unit_price' => 5000.00,
            'line_subtotal' => 35000.00,
            'line_total' => 35000.00,
            'base_currency_unit_price' => 5000.00,
            'base_currency_line_total' => 35000.00,
        ]);

        // Order B requests 5 units (total 7 + 5 = 12 > 10 available)
        $orderB = Order::create([
            'order_number' => 'ORD-CONC-02',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 25000.00,
            'total' => 25000.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 25000.00,
        ]);
        $orderB->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'منتج تنافسي محدود',
            'sku_snapshot' => $this->variant->sku,
            'quantity' => 5,
            'base_quantity' => 5,
            'unit_price' => 5000.00,
            'line_subtotal' => 25000.00,
            'line_total' => 25000.00,
            'base_currency_unit_price' => 5000.00,
            'base_currency_line_total' => 25000.00,
        ]);

        $confirmAction = app(ConfirmOrderAction::class);

        // Confirm Order A -> Succeeds
        $confirmedA = $confirmAction->execute($orderA, $this->admin->id);
        $this->assertSame(OrderStatus::Confirmed, $confirmedA->status);

        // Confirm Order B -> Must fail with DomainException
        $failed = false;
        try {
            $confirmAction->execute($orderB, $this->admin->id);
        } catch (DomainException $e) {
            $failed = true;
            $this->assertStringContainsString('غير كافٍ', $e->getMessage());
        }

        $this->assertTrue($failed, 'Expected Order B confirmation to fail due to insufficient available stock.');

        // Order B remains Draft
        $orderB->refresh();
        $this->assertSame(OrderStatus::Draft, $orderB->status);

        // Verify Invariants
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();

        $this->assertEquals(10.0000, (float) $inventory->quantity);
        $this->assertEquals(7.0000, (float) $inventory->reserved_quantity);
        $this->assertEquals(3.0000, $inventory->available_quantity);
        $this->assertTrue($inventory->reserved_quantity <= $inventory->quantity, 'Invariant violated: reserved_quantity exceeds quantity!');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
