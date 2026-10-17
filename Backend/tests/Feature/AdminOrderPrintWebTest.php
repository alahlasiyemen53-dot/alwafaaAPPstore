<?php

namespace Tests\Feature;

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
use App\Models\Setting;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminOrderPrintWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_customer_order_print_form(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->syncRoles([Role::where('name', 'super_admin')->firstOrFail()]);

        $currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        Setting::create([
            'key' => 'default_currency_id',
            'value' => (string) $currency->id,
            'type' => 'integer',
        ]);
        Setting::create([
            'key' => 'application_name',
            'value' => 'لمّة',
            'type' => 'string',
        ]);

        $customer = Customer::create([
            'name' => 'أحمد علي',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'المنزل',
            'contact_name' => 'أحمد علي',
            'contact_phone' => '771234567',
            'neighborhood' => 'حدة',
            'street' => 'شارع الستين',
            'formatted_address' => 'صنعاء - حدة - شارع الستين',
            'is_default' => true,
        ]);

        $warehouse = Warehouse::create([
            'name_ar' => 'مستودع صنعاء الرئيسي',
            'code' => 'WH-SA-01',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'سامسونج', 'slug' => 'samsung', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'إلكترونيات', 'slug' => 'electronics', 'is_active' => true]);
        $product = Product::create([
            'name_ar' => 'سماعات لاسلكية',
            'slug' => 'wireless-earbuds',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'EARBUD-001',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $currency->id,
            'price' => 3500.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 20.0,
            'reserved_quantity' => 0.0,
        ]);

        $createResponse = $this->actingAs($admin)->post('/admin/orders', [
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id,
            'warehouse_id' => $warehouse->id,
            'currency_id' => $currency->id,
            'action' => 'draft',
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ]);
        $createResponse->assertSessionHasNoErrors();
        $createResponse->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $showResponse = $this->actingAs($admin)->get("/admin/orders/{$order->id}");
        $showResponse->assertOk()
            ->assertSee('طباعة طلب العميل')
            ->assertSee(route('admin.orders.print', $order), false);

        $printResponse = $this->actingAs($admin)->get("/admin/orders/{$order->id}/print");
        $printResponse->assertOk()
            ->assertSee('نموذج طلب عميل')
            ->assertSee($order->order_number)
            ->assertSee('أحمد علي')
            ->assertSee('سماعات لاسلكية')
            ->assertSee('EARBUD-001')
            ->assertSee('طريقة الدفع:')
            ->assertSee('حالة الدفع:')
            ->assertSee('توقيع المستلم')
            ->assertSee('طباعة النموذج');

        $order->update([
            'payment_method_code' => 'jaib',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($admin)->get('/admin/orders')
            ->assertOk()
            ->assertSee('طريقة الدفع')
            ->assertSee('حالة الدفع')
            ->assertSee('مدفوع');

        $this->actingAs($admin)->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('طريقة الدفع')
            ->assertSee('مدفوع');
    }
}
