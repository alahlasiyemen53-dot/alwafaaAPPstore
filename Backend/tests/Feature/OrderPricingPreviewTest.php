<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CouponDiscountType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantPrice;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderPricingPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Customer $customer;

    private Currency $currency;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');
        $this->unauthorizedUser = $this->userWithRole('accountant');

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
            'name' => 'طارق حميد',
            'phone' => '773344556',
            'normalized_phone' => '+967773344556',
            'is_active' => true,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'طارق حميد',
            'contact_phone' => '773344556',
            'address_line_1' => 'شارع حدة',
            'is_default' => true,
        ]);

        $cat = Category::create(['name_ar' => 'عطور', 'name_en' => 'Perfumes', 'slug' => 'perfumes', 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'شانيل', 'slug' => 'chanel', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'name_ar' => 'عطر رجالي',
            'slug' => 'perfume-01',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-PRF-01',
            'barcode' => 'BAR-PRF-01',
            'status' => 'active',
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => '100.0000',
            'cost_price' => '50.0000',
        ]);
    }

    public function test_pricing_preview_rejects_guests(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'coupon_code' => null,
            'items' => [[
                'product_variant_id' => $this->variant->id,
                'quantity' => '2.0000',
                'unit_id' => null,
            ]],
        ];

        $this->postJson(route('admin.orders.pricing-preview'), $payload)
            ->assertUnauthorized();
    }

    public function test_pricing_preview_rejects_unauthorized_users(): void
    {
        $this->actingAs($this->unauthorizedUser);

        $payload = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'coupon_code' => null,
            'items' => [[
                'product_variant_id' => $this->variant->id,
                'quantity' => '2.0000',
                'unit_id' => null,
            ]],
        ];

        $this->postJson(route('admin.orders.pricing-preview'), $payload)
            ->assertForbidden();
    }

    public function test_pricing_preview_calculates_authoritative_totals(): void
    {
        $this->actingAs($this->admin);

        Coupon::create([
            'code' => 'SAVE10',
            'name' => 'خصم 10 ريال',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => '10.0000',
            'minimum_order_amount' => '50.0000',
            'is_active' => true,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'coupon_code' => 'SAVE10',
            'items' => [[
                'product_variant_id' => $this->variant->id,
                'quantity' => '2.0000',
                'unit_id' => null,
            ]],
        ];

        $response = $this->postJson(route('admin.orders.pricing-preview'), $payload)
            ->assertOk()
            ->assertJsonStructure([
                'subtotal',
                'promotion_discount_total',
                'bundle_discount_total',
                'coupon_discount_total',
                'discount_amount',
                'total',
                'base_currency_total',
                'items',
            ]);

        $this->assertEquals(200.0, (float) $response->json('subtotal'));
        $this->assertEquals(10.0, (float) $response->json('coupon_discount_total'));
        $this->assertEquals(190.0, (float) $response->json('total'));
    }

    public function test_pricing_preview_returns_422_on_invalid_coupon_domain_exception(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'coupon_code' => 'NONEXISTENT',
            'items' => [[
                'product_variant_id' => $this->variant->id,
                'quantity' => '1.0000',
                'unit_id' => null,
            ]],
        ];

        $this->postJson(route('admin.orders.pricing-preview'), $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['error_message']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
