<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\ExchangeRate;
use App\Models\DeliveryMethod;
use App\Models\Governorate;
use App\Models\GovernorateDeliveryMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GovernorateDeliveryFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Country $yemen;

    private Currency $baseCurrency;

    private Currency $foreignCurrency;

    private Governorate $sanaa;

    private Governorate $aden;

    private Warehouse $warehouse;

    private Customer $customer;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->admin = User::factory()->create();
        $adminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->admin->addRole($adminRole);

        $this->baseCurrency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->foreignCurrency = Currency::create([
            'code' => 'SAR',
            'name_ar' => 'ريال سعودي',
            'symbol' => 'ر.س',
            'decimal_places' => 2,
            'is_default' => false,
            'is_active' => true,
        ]);

        Setting::create([
            'key' => 'default_currency_id',
            'value' => (string) $this->baseCurrency->id,
            'type' => 'integer',
        ]);

        $this->yemen = Country::query()->where('code', 'YE')->firstOrFail();

        // Exchange rate: 1 SAR = 140 YER
        ExchangeRate::create([
            'from_currency_id' => $this->foreignCurrency->id,
            'to_currency_id' => $this->baseCurrency->id,
            'rate' => '140.00000000',
            'effective_at' => now()->subMinute(),
        ]);

        $this->sanaa = Governorate::create([
            'country_id' => $this->yemen->id,
            'name_ar' => 'صنعاء',
            'name_en' => 'Sanaa',
            'slug' => 'sanaa',
            'is_active' => true,
        ]);

        $this->aden = Governorate::create([
            'country_id' => $this->yemen->id,
            'name_ar' => 'عدن',
            'name_en' => 'Aden',
            'slug' => 'aden',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-SANAA',
            'name_ar' => 'مستودع صنعاء الرئيسي',
            'governorate_id' => $this->sanaa->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'محمد اليمني',
            'phone' => '777123456',
            'normalized_phone' => '777123456',
            'phone_verified_at' => now(),
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'علامة تجريبية', 'slug' => 'test-brand', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف تجريبي', 'slug' => 'test-category', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج تجريبي',
            'slug' => 'demo-product',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-FEE-TEST',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => 10000.0000,
        ]);

        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'standard'],
            [
                'name_ar' => 'توصيل قياسي',
                'name_en' => 'Standard Delivery',
                'price' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $this->ensureStandardDeliveryAssignment($this->sanaa->id, 1500.0);
        $this->ensureStandardDeliveryAssignment($this->aden->id, 0.0);
    }

    public function test_governorate_create_does_not_require_delivery_fee(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.master-data.governorates.store'), [
            'country_id' => $this->yemen->id,
            'name_ar' => 'تعز',
            'name_en' => 'Taiz',
            'slug' => 'taiz',
            'is_active' => 1,
        ]);
        $response->assertRedirect(route('admin.master-data.governorates.index'));

        $this->assertDatabaseHas('governorates', [
            'slug' => 'taiz',
            'name_ar' => 'تعز',
        ]);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('governorates', 'delivery_fee'));
    }

    public function test_delivery_method_manage_page_lists_governorate_fees(): void
    {
        $method = DeliveryMethod::query()->where('code', 'standard')->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.master-data.delivery-methods.manage', $method))
            ->assertOk()
            ->assertSee('المحافظات والتسعير')
            ->assertSee('صنعاء')
            ->assertSee('1,500.00');
    }

    public function test_order_creation_snapshots_governorate_delivery_fee_and_includes_in_total(): void
    {
        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'governorate_id' => $this->sanaa->id,
            'label' => 'المنزل',
            'contact_name' => 'محمد',
            'contact_phone' => '777123456',
            'address_text' => 'شارع الستين',
            'is_default' => true,
        ]);

        /** @var CreateOrderAction $action */
        $action = app(CreateOrderAction::class);

        $orderData = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->baseCurrency->id,
            items: [
                new OrderItemData(
                    productVariantId: $this->variant->id,
                    quantity: 1.0,
                ),
            ],
        );

        $order = $action->execute($orderData, $this->admin->id);

        // Product = 10,000 YER, Delivery Fee = 1,500 YER, Total = 11,500 YER
        self::assertEquals('1500.0000', $order->delivery_fee);
        self::assertEquals('1500.0000', $order->base_currency_delivery_fee);
        self::assertEquals('10000.0000', $order->subtotal);
        self::assertEquals('11500.0000', $order->total);
        self::assertEquals('11500.0000', $order->base_currency_total);
    }

    public function test_order_in_foreign_currency_converts_delivery_fee_using_division(): void
    {
        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'governorate_id' => $this->sanaa->id,
            'label' => 'المنزل',
            'contact_name' => 'محمد',
            'contact_phone' => '777123456',
            'address_text' => 'شارع الستين',
            'is_default' => true,
        ]);

        /** @var CreateOrderAction $action */
        $action = app(CreateOrderAction::class);

        $orderData = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->foreignCurrency->id, // SAR
            items: [
                new OrderItemData(
                    productVariantId: $this->variant->id,
                    quantity: 1.0,
                ),
            ],
        );

        $order = $action->execute($orderData, $this->admin->id);

        // 1 SAR = 140 YER
        // Base item price = 10,000 YER / 140 = 71.43 SAR
        // Base delivery fee = 1,500 YER / 140 = 10.7143 SAR
        self::assertEquals('1500.0000', $order->base_currency_delivery_fee);
        self::assertEquals('10.7142', $order->delivery_fee);
        self::assertNotEquals('210000.0000', $order->delivery_fee); // NEVER multiplied!
        self::assertEquals('82.1427', $order->total); // 71.4285 + 10.7142
    }

    public function test_modifying_governorate_delivery_fee_does_not_alter_existing_orders(): void
    {
        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'governorate_id' => $this->sanaa->id,
            'label' => 'المنزل',
            'contact_name' => 'محمد',
            'contact_phone' => '777123456',
            'address_text' => 'شارع الستين',
            'is_default' => true,
        ]);

        /** @var CreateOrderAction $action */
        $action = app(CreateOrderAction::class);

        $orderData = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->baseCurrency->id,
            items: [
                new OrderItemData(
                    productVariantId: $this->variant->id,
                    quantity: 1.0,
                ),
            ],
        );

        $order1 = $action->execute($orderData, $this->admin->id);
        self::assertEquals('1500.0000', $order1->delivery_fee);
        self::assertEquals('11500.0000', $order1->total);

        $assignment = GovernorateDeliveryMethod::query()
            ->where('governorate_id', $this->sanaa->id)
            ->whereHas('deliveryMethod', fn ($query) => $query->where('code', 'standard'))
            ->firstOrFail();
        $assignment->update(['delivery_fee' => 2500.0000]);

        // Historical order1 remains unchanged!
        $order1Fresh = $order1->fresh();
        self::assertEquals('1500.0000', $order1Fresh->delivery_fee);
        self::assertEquals('11500.0000', $order1Fresh->total);

        // New order2 takes the updated assignment fee of 2,500 YER
        $order2 = $action->execute($orderData, $this->admin->id);
        self::assertEquals('2500.0000', $order2->delivery_fee);
        self::assertEquals('12500.0000', $order2->total);
    }

    public function test_create_order_ignores_client_supplied_zero_delivery_fee_base(): void
    {
        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'governorate_id' => $this->sanaa->id,
            'label' => 'المنزل',
            'contact_name' => 'محمد',
            'contact_phone' => '777123456',
            'address_text' => 'شارع الستين',
            'is_default' => true,
        ]);

        $orderData = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->baseCurrency->id,
            items: [
                new OrderItemData(
                    productVariantId: $this->variant->id,
                    quantity: 1.0,
                ),
            ],
            deliveryFeeBase: 0.0,
        );

        $order = app(CreateOrderAction::class)->execute($orderData, $this->admin->id);

        self::assertEquals('1500.0000', $order->delivery_fee);
    }

    public function test_inactive_or_missing_governorate_fails_safely(): void
    {
        $this->sanaa->update(['is_active' => false]);

        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'governorate_id' => $this->sanaa->id,
            'label' => 'المنزل',
            'contact_name' => 'محمد',
            'contact_phone' => '777123456',
            'address_text' => 'شارع الستين',
            'is_default' => true,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('محافظة عنوان التوصيل المحددة غير صالحة أو غير نشطة.');

        /** @var CreateOrderAction $action */
        $action = app(CreateOrderAction::class);

        $orderData = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->baseCurrency->id,
            items: [
                new OrderItemData(
                    productVariantId: $this->variant->id,
                    quantity: 1.0,
                ),
            ],
        );

        $action->execute($orderData, $this->admin->id);
    }
}
