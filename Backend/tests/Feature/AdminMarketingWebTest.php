<?php

namespace Tests\Feature;

use App\Enums\BundlePricingType;
use App\Enums\CouponDiscountType;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AdminMarketingWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');
        $this->unauthorizedUser = $this->userWithRole('cashier');

        Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $cat = Category::create(['name_ar' => 'قسم', 'name_en' => 'Cat', 'slug' => 'cat-mkt', 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'ماركة', 'slug' => 'brand-mkt', 'is_active' => true]);
        $prod = Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'name_ar' => 'منتج تسويقي',
            'slug' => 'mkt-01',
            'status' => 'active',
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $prod->id,
            'sku' => 'MKT-SKU',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    #[DataProvider('marketingIndexRoutes')]
    public function test_unauthorized_user_is_forbidden_from_marketing_index(string $routeName): void
    {
        $this->actingAs($this->unauthorizedUser);

        $this->get(route($routeName))->assertForbidden();
    }

    public function test_promotion_admin_pages_render(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.marketing.promotions.index'))->assertOk();
        $this->get(route('admin.marketing.promotions.create'))->assertOk();
        $this->get(route('admin.marketing.promotions.edit', $this->createPromotion()))->assertOk();
    }

    public function test_admin_can_create_promotion(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'SUMMER',
            'name' => 'عرض الصيف',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::All->value,
            'value' => 15.00,
            'priority' => 5,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.marketing.promotions.index'));
        $promo = Promotion::where('name', 'عرض الصيف')->firstOrFail();
        $this->assertEquals(15.00, (float) $promo->value);
        $this->assertTrue($promo->is_active);
    }

    public function test_admin_can_update_promotion(): void
    {
        $this->actingAs($this->admin);
        $promo = $this->createPromotion();

        $this->put(route('admin.marketing.promotions.update', $promo), [
            'code' => $promo->code,
            'name' => 'عرض الصيف المحدث',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::All->value,
            'value' => 20.00,
            'priority' => 10,
            'is_active' => '1',
        ])->assertRedirect(route('admin.marketing.promotions.index'));

        $this->assertEquals(20.00, (float) $promo->fresh()->value);
    }

    public function test_admin_can_toggle_promotion(): void
    {
        $this->actingAs($this->admin);
        $promo = $this->createPromotion();

        $this->patch(route('admin.marketing.promotions.toggle', $promo))->assertRedirect();
        $this->assertFalse($promo->fresh()->is_active);
    }

    public function test_coupon_admin_pages_render(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.marketing.coupons.index'))->assertOk();
        $this->get(route('admin.marketing.coupons.create'))->assertOk();

        $coupon = $this->createCoupon();
        $this->get(route('admin.marketing.coupons.edit', $coupon))->assertOk();
        $this->get(route('admin.marketing.coupons.usages', $coupon))->assertOk();
    }

    public function test_admin_can_create_coupon(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.marketing.coupons.store'), [
            'code' => 'DISCOUNT50',
            'name' => 'خصم 50 ريال',
            'discount_type' => CouponDiscountType::FixedAmount->value,
            'discount_value' => 50.00,
            'minimum_order_amount' => 100.00,
            'usage_limit' => 100,
            'per_customer_limit' => 1,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.marketing.coupons.index'));
        $coupon = Coupon::where('code', 'DISCOUNT50')->firstOrFail();
        $this->assertEquals(50.00, (float) $coupon->discount_value);
        $this->assertEquals(100.00, (float) $coupon->minimum_order_amount);
    }

    public function test_admin_can_toggle_coupon(): void
    {
        $this->actingAs($this->admin);
        $coupon = $this->createCoupon();

        $this->patch(route('admin.marketing.coupons.toggle', $coupon))->assertRedirect();
        $this->assertFalse($coupon->fresh()->is_active);
    }

    public function test_bundle_admin_pages_render(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.marketing.bundles.index'))->assertOk();
        $this->get(route('admin.marketing.bundles.create'))->assertOk();
        $this->get(route('admin.marketing.bundles.edit', $this->createBundle()))->assertOk();
    }

    public function test_admin_can_create_bundle(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.marketing.bundles.store'), [
            'code' => 'EID-BUNDLE',
            'name' => 'حزمة العيد',
            'pricing_type' => BundlePricingType::FixedBundlePrice->value,
            'value' => 150.00,
            'is_active' => '1',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.marketing.bundles.index'));
        $bundle = Bundle::where('name', 'حزمة العيد')->firstOrFail();
        $this->assertEquals(150.00, (float) $bundle->value);
        $this->assertCount(1, $bundle->items);
    }

    public function test_admin_can_toggle_bundle(): void
    {
        $this->actingAs($this->admin);
        $bundle = $this->createBundle();

        $this->patch(route('admin.marketing.bundles.toggle', $bundle))->assertRedirect();
        $this->assertFalse($bundle->fresh()->is_active);
    }

    public function test_promotion_scope_all_accepts_no_targets_and_stores_none(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'ALL-SCOPE',
            'name' => 'عرض للكل',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::All->value,
            'value' => 10.00,
            'target_ids' => [$this->variant->id, 999], // even if submitted, ignored
        ]);

        $response->assertRedirect(route('admin.marketing.promotions.index'));
        $promo = Promotion::where('code', 'ALL-SCOPE')->firstOrFail();
        $this->assertCount(0, $promo->targets);
    }

    public function test_promotion_scope_category_requires_valid_category_ids_and_stores_category_target(): void
    {
        $this->actingAs($this->admin);
        $category = Category::firstOrFail();

        // 1. Invalid ID fails
        $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'CAT-FAIL',
            'name' => 'عرض فئات فاشل',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::Category->value,
            'value' => 10.00,
            'target_ids' => [99999],
        ])->assertSessionHasErrors(['target_ids.0']);

        // 2. Valid ID succeeds
        $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'CAT-PASS',
            'name' => 'عرض فئات ناجح',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::Category->value,
            'value' => 10.00,
            'target_ids' => [$category->id],
        ])->assertRedirect(route('admin.marketing.promotions.index'));

        $promo = Promotion::where('code', 'CAT-PASS')->firstOrFail();
        $this->assertCount(1, $promo->targets);
        $this->assertEquals('category', $promo->targets->first()->target_type);
        $this->assertEquals($category->id, $promo->targets->first()->target_id);
    }

    public function test_promotion_scope_brand_product_variant_validates_against_own_tables(): void
    {
        $this->actingAs($this->admin);
        $brand = Brand::firstOrFail();
        $product = Product::firstOrFail();

        // Scope Brand
        $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'BRAND-TEST',
            'name' => 'عرض ماركة',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::Brand->value,
            'value' => 10.00,
            'target_ids' => [$brand->id],
        ])->assertRedirect();
        $promoBrand = Promotion::where('code', 'BRAND-TEST')->firstOrFail();
        $this->assertEquals('brand', $promoBrand->targets->first()->target_type);

        // Scope Product
        $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'PROD-TEST',
            'name' => 'عرض منتج',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::Product->value,
            'value' => 10.00,
            'target_ids' => [$product->id],
        ])->assertRedirect();
        $promoProd = Promotion::where('code', 'PROD-TEST')->firstOrFail();
        $this->assertEquals('product', $promoProd->targets->first()->target_type);

        // Scope Variant
        $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'VAR-TEST',
            'name' => 'عرض متغير',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::Variant->value,
            'value' => 10.00,
            'target_ids' => [$this->variant->id],
        ])->assertRedirect();
        $promoVar = Promotion::where('code', 'VAR-TEST')->firstOrFail();
        $this->assertEquals('variant', $promoVar->targets->first()->target_type);
    }

    public function test_promotion_changing_scope_replaces_old_targets_atomically(): void
    {
        $this->actingAs($this->admin);
        $category = Category::firstOrFail();
        $brand = Brand::firstOrFail();

        $promo = $this->createPromotion([
            'code' => 'ATOMIC-REPLACE',
            'scope' => PromotionScope::Category,
        ]);
        $promo->targets()->create([
            'target_type' => 'category',
            'target_id' => $category->id,
        ]);

        $this->put(route('admin.marketing.promotions.update', $promo), [
            'code' => 'ATOMIC-REPLACE',
            'name' => 'محدث إلى ماركة',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::Brand->value,
            'value' => 15.00,
            'target_ids' => [$brand->id],
            'is_active' => '1',
        ])->assertRedirect(route('admin.marketing.promotions.index'));

        $promo->refresh();
        $this->assertCount(1, $promo->targets);
        $this->assertEquals('brand', $promo->targets->first()->target_type);
        $this->assertEquals($brand->id, $promo->targets->first()->target_id);
    }

    public function test_promotion_percentage_rejects_over_100_and_accepts_100(): void
    {
        $this->actingAs($this->admin);

        // 100.0001 fails
        $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'OVER-PERC',
            'name' => 'نسبة زائدة',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::All->value,
            'value' => 100.0001,
        ])->assertSessionHasErrors(['value']);

        // 100.0000 succeeds
        $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'EXACT-100',
            'name' => 'نسبة كاملة',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::All->value,
            'value' => 100.0000,
        ])->assertRedirect(route('admin.marketing.promotions.index'));
    }

    public function test_promotion_code_normalized_to_uppercase_and_trimmed(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.marketing.promotions.store'), [
            'code' => '  lower-code  ',
            'name' => 'كود صغير',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::All->value,
            'value' => 10.00,
        ])->assertRedirect(route('admin.marketing.promotions.index'));

        $this->assertNotNull(Promotion::where('code', 'LOWER-CODE')->first());

        // Duplicate code with different casing or whitespace fails unique rule
        $this->post(route('admin.marketing.promotions.store'), [
            'code' => 'lower-code',
            'name' => 'كود مكرر',
            'type' => PromotionType::Percentage->value,
            'scope' => PromotionScope::All->value,
            'value' => 10.00,
        ])->assertSessionHasErrors(['code']);
    }

    public static function marketingIndexRoutes(): array
    {
        return [
            'promotions' => ['admin.marketing.promotions.index'],
            'coupons' => ['admin.marketing.coupons.index'],
            'bundles' => ['admin.marketing.bundles.index'],
        ];
    }

    private function createPromotion(array $attributes = []): Promotion
    {
        return Promotion::create(array_merge([
            'code' => 'TEST-PROMO',
            'name' => 'عرض تجريبي',
            'type' => PromotionType::Percentage,
            'scope' => PromotionScope::All,
            'value' => 10.00,
            'is_active' => true,
        ], $attributes));
    }

    private function createCoupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'TEST-COUPON',
            'name' => 'كوبون تجريبي',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 10.00,
            'is_active' => true,
        ], $attributes));
    }

    private function createBundle(array $attributes = []): Bundle
    {
        return Bundle::create(array_merge([
            'code' => 'TEST-BUNDLE',
            'name' => 'حزمة تجريبية',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 100.00,
            'is_active' => true,
        ], $attributes));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
