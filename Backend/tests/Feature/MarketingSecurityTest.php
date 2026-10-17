<?php

namespace Tests\Feature;

use App\Enums\BundlePricingType;
use App\Enums\CouponDiscountType;
use App\Enums\ProductVariantStatus;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Currency $baseCurrency;

    private Warehouse $warehouse;

    private Customer $customer;

    private CustomerAddress $address;

    private ProductVariant $activeVariant;

    private ProductVariant $inactiveVariant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');
        $this->unauthorizedUser = $this->userWithRole('cashier');

        $this->baseCurrency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-SEC',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'عميل أمني',
            'phone' => '770000000',
            'normalized_phone' => '+967770000000',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'الرئيسي',
            'contact_name' => 'عميل أمني',
            'contact_phone' => '770000000',
            'address_line' => 'صنعاء',
            'is_default' => true,
        ]);

        $cat = Category::create(['name_ar' => 'إلكترونيات', 'slug' => 'electronics', 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'سامسونج', 'slug' => 'samsung', 'is_active' => true]);

        $prod = Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'name_ar' => 'هاتف',
            'slug' => 'phone',
            'status' => 'active',
        ]);

        $this->activeVariant = ProductVariant::create([
            'product_id' => $prod->id,
            'sku' => 'PHONE-ACT',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);
        VariantPrice::create([
            'product_variant_id' => $this->activeVariant->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => 50000.00,
        ]);
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->activeVariant->id,
            'quantity' => 20,
        ]);

        $this->inactiveVariant = ProductVariant::create([
            'product_id' => $prod->id,
            'sku' => 'PHONE-INACT',
            'status' => ProductVariantStatus::Inactive,
            'is_default' => false,
        ]);
        VariantPrice::create([
            'product_variant_id' => $this->inactiveVariant->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => 45000.00,
        ]);
    }

    public function test_guest_is_redirected_to_login_on_all_marketing_and_pricing_routes(): void
    {
        $promo = Promotion::create([
            'code' => 'P1',
            'name' => 'P1',
            'type' => PromotionType::Percentage,
            'value' => 10,
            'scope' => PromotionScope::All,
            'is_active' => true,
        ]);
        $coupon = Coupon::create([
            'code' => 'C1',
            'name' => 'C1',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 100,
            'scope' => 'all',
            'is_active' => true,
        ]);
        $bundle = Bundle::create([
            'code' => 'B1',
            'name' => 'B1',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 1000,
            'is_active' => true,
        ]);

        $this->get(route('admin.marketing.promotions.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.marketing.promotions.create'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.marketing.promotions.store'), [])->assertRedirect(route('admin.login'));
        $this->get(route('admin.marketing.promotions.edit', $promo))->assertRedirect(route('admin.login'));
        $this->put(route('admin.marketing.promotions.update', $promo), [])->assertRedirect(route('admin.login'));
        $this->patch(route('admin.marketing.promotions.toggle', $promo))->assertRedirect(route('admin.login'));

        $this->get(route('admin.marketing.coupons.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.marketing.coupons.create'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.marketing.coupons.store'), [])->assertRedirect(route('admin.login'));
        $this->get(route('admin.marketing.coupons.edit', $coupon))->assertRedirect(route('admin.login'));
        $this->put(route('admin.marketing.coupons.update', $coupon), [])->assertRedirect(route('admin.login'));
        $this->patch(route('admin.marketing.coupons.toggle', $coupon))->assertRedirect(route('admin.login'));
        $this->get(route('admin.marketing.coupons.usages', $coupon))->assertRedirect(route('admin.login'));

        $this->get(route('admin.marketing.bundles.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.marketing.bundles.create'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.marketing.bundles.store'), [])->assertRedirect(route('admin.login'));
        $this->get(route('admin.marketing.bundles.edit', $bundle))->assertRedirect(route('admin.login'));
        $this->put(route('admin.marketing.bundles.update', $bundle), [])->assertRedirect(route('admin.login'));
        $this->patch(route('admin.marketing.bundles.toggle', $bundle))->assertRedirect(route('admin.login'));

        $this->post(route('admin.orders.pricing-preview'), [])->assertRedirect(route('admin.login'));
    }

    public function test_user_without_permission_receives_403(): void
    {
        $this->actingAs($this->unauthorizedUser);

        $promo = Promotion::create([
            'code' => 'P2',
            'name' => 'P2',
            'type' => PromotionType::Percentage,
            'value' => 10,
            'scope' => PromotionScope::All,
            'is_active' => true,
        ]);
        $coupon = Coupon::create([
            'code' => 'C2',
            'name' => 'C2',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 100,
            'scope' => 'all',
            'is_active' => true,
        ]);
        $bundle = Bundle::create([
            'code' => 'B2',
            'name' => 'B2',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 1000,
            'is_active' => true,
        ]);

        $this->get(route('admin.marketing.promotions.index'))->assertForbidden();
        $this->get(route('admin.marketing.promotions.create'))->assertForbidden();
        $this->post(route('admin.marketing.promotions.store'), [])->assertForbidden();
        $this->patch(route('admin.marketing.promotions.toggle', $promo))->assertForbidden();

        $this->get(route('admin.marketing.coupons.index'))->assertForbidden();
        $this->get(route('admin.marketing.coupons.create'))->assertForbidden();
        $this->post(route('admin.marketing.coupons.store'), [])->assertForbidden();
        $this->patch(route('admin.marketing.coupons.toggle', $coupon))->assertForbidden();

        $this->get(route('admin.marketing.bundles.index'))->assertForbidden();
        $this->get(route('admin.marketing.bundles.create'))->assertForbidden();
        $this->post(route('admin.marketing.bundles.store'), [])->assertForbidden();
        $this->patch(route('admin.marketing.bundles.toggle', $bundle))->assertForbidden();

        $accountant = $this->userWithRole('accountant');
        $this->actingAs($accountant)->post(route('admin.orders.pricing-preview'), [])->assertForbidden();
    }

    public function test_cross_resource_target_injection_is_rejected(): void
    {
        $this->actingAs($this->admin);

        // Scope = category, but submitting a brand or non-existent ID
        $response = $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'CROSS-INJECT',
            'name' => 'حقن تصنيفات',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::Category->value,
            'value' => 10,
            'target_ids' => [99999],
        ]);

        $response->assertSessionHasErrors(['target_ids.0']);
    }

    public function test_inactive_product_variant_cannot_be_targeted_or_ordered(): void
    {
        $this->actingAs($this->admin);

        // Ordering inactive variant fails with domain exception
        $response = $this->postJson(route('admin.orders.pricing-preview'), [
            'currency_id' => $this->baseCurrency->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'product_variant_id' => $this->inactiveVariant->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('غير نشط', $response->json('error_message'));
    }

    public function test_malformed_arrays_are_rejected(): void
    {
        $this->actingAs($this->admin);

        // Empty items in pricing preview
        $this->postJson(route('admin.orders.pricing-preview'), [
            'currency_id' => $this->baseCurrency->id,
            'customer_id' => $this->customer->id,
            'items' => [],
        ])->assertStatus(422);

        // Negative quantity in pricing preview
        $this->postJson(route('admin.orders.pricing-preview'), [
            'currency_id' => $this->baseCurrency->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'product_variant_id' => $this->activeVariant->id,
                    'quantity' => -5,
                ],
            ],
        ])->assertStatus(422);

        // Zero quantity in pricing preview
        $this->postJson(route('admin.orders.pricing-preview'), [
            'currency_id' => $this->baseCurrency->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'product_variant_id' => $this->activeVariant->id,
                    'quantity' => 0,
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_normalized_duplicate_marketing_codes_are_rejected(): void
    {
        $this->actingAs($this->admin);

        Coupon::create([
            'code' => 'SAVE10',
            'name' => 'وفر 10',
            'discount_type' => CouponDiscountType::Percentage,
            'discount_value' => 10,
            'scope' => 'all',
            'is_active' => true,
        ]);

        // Submitting lowercase with whitespace ' save10 ' should be normalized and fail uniqueness
        $response = $this->post(route('admin.marketing.coupons.store'), [
            'code' => '  save10  ',
            'name' => 'وفر 10 مجدداً',
            'discount_type' => CouponDiscountType::Percentage->value,
            'discount_value' => 10,
            'scope' => 'all',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_arbitrary_client_totals_in_order_submission_are_ignored(): void
    {
        $this->actingAs($this->admin);

        // Submit client trying to inject custom subtotal, total, or discount_amount
        $response = $this->post(route('admin.orders.store'), [
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->baseCurrency->id,
            'subtotal' => 1.00,
            'total' => 1.00,
            'discount_amount' => 99999.00,
            'items' => [
                [
                    'product_variant_id' => $this->activeVariant->id,
                    'quantity' => 2,
                    'unit_price' => 0.50, // client price manipulation attempt
                ],
            ],
        ]);

        $order = Order::latest('id')->firstOrFail();
        // The authoritative price for PHONE-ACT is 50,000 YER * 2 = 100,000 YER
        // Client-injected numbers (1.00, 0.50, 99999.00) MUST NOT be persisted!
        $this->assertEquals(100000.00, (float) $order->subtotal);
        $this->assertEquals(100000.00, (float) $order->total);
        $this->assertEquals(0.00, (float) $order->discount_amount);
    }

    public function test_direct_calls_to_pricing_preview_work_and_validate(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson(route('admin.orders.pricing-preview'), [
            'currency_id' => $this->baseCurrency->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'product_variant_id' => $this->activeVariant->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'subtotal' => 50000.0,
            'total' => 50000.0,
            'discount_amount' => 0.0,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
