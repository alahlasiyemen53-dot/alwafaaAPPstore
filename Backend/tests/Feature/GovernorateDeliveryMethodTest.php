<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\CustomerAddress;
use App\Models\DeliveryMethod;
use App\Models\Governorate;
use App\Models\GovernorateDeliveryMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\Auth\CustomerJwtService;
use App\Support\GovernorateDeliveryMethodBackfill;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GovernorateDeliveryMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_assignment_rows_with_zero_fee_when_legacy_column_absent(): void
    {
        $country = Country::query()->where('code', 'YE')->firstOrFail();

        $governorate = Governorate::create([
            'country_id' => $country->id,
            'name_ar' => 'حضرموت',
            'name_en' => 'Hadramout',
            'slug' => 'hadramout-backfill-test',
            'is_active' => true,
        ]);

        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'standard'],
            [
                'name_ar' => 'قياسي',
                'name_en' => 'Standard',
                'price' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        GovernorateDeliveryMethodBackfill::run();

        $this->assertDatabaseHas('governorate_delivery_methods', [
            'governorate_id' => $governorate->id,
            'delivery_fee' => 0,
            'is_active' => true,
        ]);
    }

    public function test_checkout_preview_delivery_fee_differs_by_selected_shipping_method(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        Setting::updateOrCreate(['key' => 'default_currency_id'], ['value' => (string) $currency->id]);

        $country = Country::query()->where('code', 'YE')->firstOrFail();
        $governorate = Governorate::create([
            'country_id' => $country->id,
            'name_ar' => 'إب',
            'name_en' => 'Ibb',
            'slug' => 'ibb-methods',
            'is_active' => true,
        ]);

        $standard = DeliveryMethod::query()->updateOrCreate(
            ['code' => 'standard'],
            ['name_ar' => 'قياسي', 'name_en' => 'Standard', 'price' => 0, 'sort_order' => 1, 'is_active' => true]
        );
        $express = DeliveryMethod::query()->updateOrCreate(
            ['code' => 'express'],
            ['name_ar' => 'سريع', 'name_en' => 'Express', 'price' => 0, 'sort_order' => 2, 'is_active' => true]
        );

        GovernorateDeliveryMethod::query()->updateOrCreate(
            ['governorate_id' => $governorate->id, 'delivery_method_id' => $standard->id],
            ['delivery_fee' => 1000, 'is_active' => true, 'sort_order' => 1]
        );
        GovernorateDeliveryMethod::query()->updateOrCreate(
            ['governorate_id' => $governorate->id, 'delivery_method_id' => $express->id],
            ['delivery_fee' => 3500, 'is_active' => true, 'sort_order' => 2]
        );

        $customer = Customer::create([
            'name' => 'عميل',
            'phone' => '777000111',
            'normalized_phone' => '777000111',
            'phone_verified_at' => now(),
            'is_active' => true,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'governorate_id' => $governorate->id,
            'country_id' => $country->id,
            'label' => 'منزل',
            'contact_name' => 'عميل',
            'contact_phone' => '777000111',
            'address_text' => 'شارع',
            'is_default' => true,
        ]);

        Warehouse::create([
            'code' => 'WH-IBB',
            'name_ar' => 'مستودع',
            'governorate_id' => $governorate->id,
            'is_active' => true,
        ]);

        $warehouse =         $brand = Brand::create(['name_ar' => 'علامة', 'slug' => 'brand-ibb', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف', 'slug' => 'cat-ibb', 'is_active' => true]);
        $product = Product::create([
            'name_ar' => 'منتج',
            'slug' => 'prod-ibb',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => ProductStatus::Active,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-IBB',
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
            'is_default' => true,
        ]);
        VariantPrice::create(['product_variant_id' => $variant->id, 'currency_id' => $currency->id, 'price' => 5000, 'is_active' => true]);
        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $token = app(CustomerJwtService::class)->generateAccessToken($customer);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/checkout/preview', [
                'customer_address_id' => $address->id,
                'shipping_method' => 'standard',
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing.delivery_fee', 1000);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/checkout/preview', [
                'customer_address_id' => $address->id,
                'shipping_method' => 'express',
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing.delivery_fee', 3500);
    }

    public function test_checkout_preview_rejects_unassigned_shipping_method_for_governorate(): void
    {
        $currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);
        Setting::updateOrCreate(['key' => 'default_currency_id'], ['value' => (string) $currency->id]);

        $country = Country::query()->where('code', 'YE')->firstOrFail();
        $governorate = Governorate::create([
            'country_id' => $country->id,
            'name_ar' => 'المهرة',
            'name_en' => 'Mahrah',
            'slug' => 'mahrah-unassigned',
            'is_active' => true,
        ]);

        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'standard'],
            ['name_ar' => 'قياسي', 'name_en' => 'Standard', 'price' => 0, 'sort_order' => 1, 'is_active' => true]
        );
        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'same_day'],
            ['name_ar' => 'نفس اليوم', 'name_en' => 'Same day', 'price' => 0, 'sort_order' => 3, 'is_active' => true]
        );

        $this->ensureStandardDeliveryAssignment($governorate->id, 500);
        // same_day deliberately left unassigned for this governorate.

        $customer = Customer::create([
            'name' => 'عميل',
            'phone' => '777000222',
            'normalized_phone' => '777000222',
            'phone_verified_at' => now(),
            'is_active' => true,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'governorate_id' => $governorate->id,
            'country_id' => $country->id,
            'label' => 'منزل',
            'contact_name' => 'عميل',
            'contact_phone' => '777000222',
            'address_text' => 'شارع',
            'is_default' => true,
        ]);

        $warehouse = Warehouse::create([
            'code' => 'WH-MAH',
            'name_ar' => 'مستودع',
            'governorate_id' => $governorate->id,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'علامة', 'slug' => 'brand-mah', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف', 'slug' => 'cat-mah', 'is_active' => true]);
        $product = Product::create([
            'name_ar' => 'منتج',
            'slug' => 'prod-mah',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => ProductStatus::Active,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-MAH',
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
            'is_default' => true,
        ]);
        VariantPrice::create(['product_variant_id' => $variant->id, 'currency_id' => $currency->id, 'price' => 2000, 'is_active' => true]);
        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);
        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $token = app(CustomerJwtService::class)->generateAccessToken($customer);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/checkout/preview', [
                'customer_address_id' => $address->id,
                'shipping_method' => 'same_day',
            ])
            ->assertOk()
            ->assertJsonPath('data.can_checkout', false);
    }
}
