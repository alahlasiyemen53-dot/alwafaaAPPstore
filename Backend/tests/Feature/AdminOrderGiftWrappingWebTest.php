<?php

namespace Tests\Feature;

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
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\GiftWrappingService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminOrderGiftWrappingWebTest extends TestCase
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

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->syncRoles([Role::where('name', 'super_admin')->firstOrFail()]);

        $this->currency = Currency::create([
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
            'value' => (string) $this->currency->id,
            'type' => 'integer',
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
            'name_ar' => 'مستودع صنعاء الرئيسي',
            'code' => 'WH-SA-01',
            'is_active' => true,
        ]);

        $unit = Unit::create(['name_ar' => 'حبة', 'code' => 'PCS', 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'سامسونج', 'slug' => 'samsung', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'إلكترونيات', 'slug' => 'electronics', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'ساعة ذكية',
            'slug' => 'smart-watch',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'WATCH-001',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 5000.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 20.0,
            'reserved_quantity' => 0.0,
        ]);

        // Enable gift wrapping and message in settings by default
        Setting::create(['key' => 'gift_wrapping_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'gift_message_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'gift_message_max_length', 'value' => '250', 'type' => 'integer']);
        app(GiftWrappingService::class)->clearCache();
    }

    public function test_can_create_order_with_gift_wrapping_and_message(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'gift_wrapping_requested' => 1,
            'gift_message' => 'كل عام وأنتم بخير بمناسبة النجاح!',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/admin/orders', $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $this->assertTrue($order->gift_wrapping_requested);
        $this->assertSame('كل عام وأنتم بخير بمناسبة النجاح!', $order->gift_message_snapshot);
        $this->assertTrue($order->hasGiftWrapping());
        $this->assertTrue($order->hasGiftMessage());

        // Verify financial safety: V1 gift wrapping is strictly free ($0 added)
        $this->assertEquals(10000.00, (float) $order->subtotal);
        $this->assertEquals(10000.00, (float) $order->total);

        // Verify show page renders gift wrapping snapshot
        $showResponse = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}");
        $showResponse->assertOk()
            ->assertSee('خدمة تغليف الهدايا (لقطة تاريخية للطلب)')
            ->assertSee('تم طلب تغليف الهدية (مجاني V1)')
            ->assertSee('نعم (Yes)')
            ->assertSee('كل عام وأنتم بخير بمناسبة النجاح!')
            ->assertSee('مجاني V1');
    }

    public function test_rejects_gift_wrapping_when_service_is_disabled(): void
    {
        Setting::updateOrCreate(['key' => 'gift_wrapping_enabled'], ['value' => '0']);
        app(GiftWrappingService::class)->clearCache();

        $payload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'gift_wrapping_requested' => 1,
            'gift_message' => 'مبروك',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/admin/orders', $payload);
        $response->assertSessionHasErrors('gift_wrapping_requested');
    }

    public function test_rejects_gift_message_when_message_is_disabled(): void
    {
        Setting::updateOrCreate(['key' => 'gift_message_enabled'], ['value' => '0']);
        app(GiftWrappingService::class)->clearCache();

        $payload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'gift_wrapping_requested' => 1,
            'gift_message' => 'رسالة غير مسموحة',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/admin/orders', $payload);
        $response->assertSessionHasErrors('gift_message');
    }

    public function test_rejects_oversized_gift_message(): void
    {
        Setting::updateOrCreate(['key' => 'gift_message_max_length'], ['value' => '20']);
        app(GiftWrappingService::class)->clearCache();

        $payload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'gift_wrapping_requested' => 1,
            'gift_message' => 'رسالة طويلة جداً تتجاوز الحد الأقصى المحدد في الإعدادات',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/admin/orders', $payload);
        $response->assertSessionHasErrors('gift_message');
    }

    public function test_gift_message_xss_is_escaped_on_view(): void
    {
        $xssMessage = '<script>alert("hacked")</script>ألف مبروك!';

        $payload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'gift_wrapping_requested' => 1,
            'gift_message' => $xssMessage,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/admin/orders', $payload);
        $response->assertSessionHasNoErrors();

        $order = Order::latest('id')->firstOrFail();
        // The action sanitizes HTML via strip_tags
        $this->assertStringNotContainsString('<script>', (string) $order->gift_message_snapshot);

        // And the Blade template escapes any output
        $showResponse = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}");
        $showResponse->assertOk();
        $showResponse->assertDontSee('<script>', false);
        $showResponse->assertSee('ألف مبروك!');
    }

    public function test_can_update_draft_order_gift_wrapping(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'gift_wrapping_requested' => 0,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $this->actingAs($this->admin)->post('/admin/orders', $payload);
        $order = Order::latest('id')->firstOrFail();
        $this->assertFalse($order->gift_wrapping_requested);

        // Update to enable gift wrapping with message
        $updatePayload = [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'gift_wrapping_requested' => 1,
            'gift_message' => 'تمت إضافة رسالة بعد التعديل',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put("/admin/orders/{$order->id}", $updatePayload);
        $response->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertTrue($order->gift_wrapping_requested);
        $this->assertSame('تمت إضافة رسالة بعد التعديل', $order->gift_message_snapshot);
    }
}
