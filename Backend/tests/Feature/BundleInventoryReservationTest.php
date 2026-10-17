<?php

namespace Tests\Feature;

use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\BundlePricingType;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BundleInventoryReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private CustomerAddress $address;

    private Warehouse $warehouse;

    private Currency $currency;

    private ProductVariant $variantA;

    private ProductVariant $variantB;

    private Bundle $bundle;

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
            'name' => 'جمال ياسين',
            'phone' => '774433221',
            'normalized_phone' => '+967774433221',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'جمال ياسين',
            'contact_phone' => '774433221',
            'address_line' => 'صنعاء',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'إلكترونيات',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'سامسونج', 'slug' => 'samsung', 'is_active' => true]);

        $productA = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name_ar' => 'هاتف ذكي',
            'name_en' => 'Smartphone',
            'slug' => 'phone-1',
            'status' => 'active',
        ]);

        $this->variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'sku' => 'PHONE-SKU',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variantA->id,
            'currency_id' => $this->currency->id,
            'price' => 500.00,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 10,
        ]);

        $productB = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name_ar' => 'سماعة رأس',
            'name_en' => 'Headphone',
            'slug' => 'head-1',
            'status' => 'active',
        ]);

        $this->variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'HEAD-SKU',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variantB->id,
            'currency_id' => $this->currency->id,
            'price' => 100.00,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 20,
        ]);

        // Bundle: Phone + Headphone = fixed price 520 (saving 80)
        $this->bundle = Bundle::create([
            'code' => 'PHONE-HEADSET',
            'name' => 'باقة الهاتف وسماعة الرأس',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 520.00,
            'is_active' => true,
        ]);

        BundleItem::create([
            'bundle_id' => $this->bundle->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 1,
        ]);

        BundleItem::create([
            'bundle_id' => $this->bundle->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 1,
        ]);
    }

    public function test_bundle_items_reserve_individual_variant_inventory(): void
    {
        $dto = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: 'طلب حزمة',
            items: [
                new OrderItemData(productVariantId: $this->variantA->id, quantity: 1),
                new OrderItemData(productVariantId: $this->variantB->id, quantity: 1),
            ],
        );

        $order = app(CreateOrderAction::class)->execute($dto, $this->admin->id);
        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        $this->assertEquals(OrderStatus::Confirmed, $confirmedOrder->status);
        $this->assertEquals(80.00, (float) $confirmedOrder->bundle_discount_total);
        $this->assertEquals(520.00, (float) $confirmedOrder->total);

        // Verify inventory reservations: should reserve 1 Phone and 1 Headphone
        $reservations = InventoryReservation::query()->where('order_id', $confirmedOrder->id)->get();
        $this->assertCount(2, $reservations);

        $phoneRes = $reservations->firstWhere('product_variant_id', $this->variantA->id);
        $this->assertNotNull($phoneRes);
        $this->assertEquals(1.0, (float) $phoneRes->quantity);
        $this->assertEquals(ReservationStatus::Active, $phoneRes->status);

        $headRes = $reservations->firstWhere('product_variant_id', $this->variantB->id);
        $this->assertNotNull($headRes);
        $this->assertEquals(1.0, (float) $headRes->quantity);
        $this->assertEquals(ReservationStatus::Active, $headRes->status);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
