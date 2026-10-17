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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HistoricalSnapshotImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private CustomerAddress $address;

    private Warehouse $warehouse;

    private Currency $currency;

    private ProductVariant $variant;

    private VariantPrice $variantPrice;

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
            'name' => 'الاسم الأصلي للعميل',
            'phone' => '770001122',
            'normalized_phone' => '+967770001122',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل الأصلي',
            'contact_name' => 'الاسم الأصلي للعميل',
            'contact_phone' => '770001122',
            'neighborhood' => 'حي الأصبحي',
            'street' => 'شارع 24',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع الأمانة',
            'code' => 'WH-AMN',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'براند تاريخي', 'slug' => 'brand-hist', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'قسم تاريخي', 'slug' => 'cat-hist', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج اللقطة التاريخية',
            'slug' => 'hist-prod',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-HIST-99',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->variantPrice = VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 3000.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10.0000,
            'reserved_quantity' => 0.0000,
        ]);
    }

    public function test_customer_mutation_does_not_alter_historical_order_snapshots(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-300001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 3000.00,
            'total' => 3000.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 3000.00,
        ]);

        $order->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'منتج اللقطة التاريخية',
            'sku_snapshot' => $this->variant->sku,
            'quantity' => 1,
            'base_quantity' => 1,
            'unit_price' => 3000.00,
            'line_subtotal' => 3000.00,
            'line_total' => 3000.00,
            'base_currency_unit_price' => 3000.00,
            'base_currency_line_total' => 3000.00,
        ]);

        $order->deliveryAddress()->create([
            'order_id' => $order->id,
            'address_type' => 'delivery',
            'contact_name' => $this->address->contact_name,
            'contact_phone' => $this->address->contact_phone,
            'neighborhood' => $this->address->neighborhood,
            'street' => $this->address->street,
        ]);

        app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        // Mutate Customer data and Customer Address
        $this->customer->update([
            'name' => 'الاسم الجديد بعد التعديل',
            'phone' => '779998877',
        ]);

        $this->address->update([
            'neighborhood' => 'حي شميلة الجديد',
            'street' => 'شارع تعز العام',
        ]);

        // Mutate Product Variant catalog price
        $this->variantPrice->update([
            'price' => 99999.00,
        ]);

        $order->refresh();

        // Historical snapshots must remain completely untouched!
        $this->assertSame('الاسم الأصلي للعميل', $order->customer_name_snapshot);
        $this->assertSame('770001122', $order->customer_phone_snapshot);
        $this->assertSame('حي الأصبحي', $order->deliveryAddress->neighborhood);
        $this->assertSame('شارع 24', $order->deliveryAddress->street);

        // Historical price snapshot must remain unchanged
        $this->assertEquals(3000.00, (float) $order->subtotal);
        $this->assertEquals(3000.00, (float) $order->total);
        $this->assertEquals(3000.00, (float) $order->items->first()->unit_price);

        // Assert view displays historical snapshots
        $viewResponse = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}");
        $viewResponse->assertOk();
        $viewResponse->assertSee('الاسم الأصلي للعميل');
        $viewResponse->assertSee('حي الأصبحي');
        $viewResponse->assertSee('3,000.00');
        $viewResponse->assertDontSee('99999');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
