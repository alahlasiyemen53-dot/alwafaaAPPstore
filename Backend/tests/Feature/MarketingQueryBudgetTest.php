<?php

namespace Tests\Feature;

use App\Enums\BundlePricingType;
use App\Enums\CouponDiscountType;
use App\Enums\ProductVariantStatus;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MarketingQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Currency $baseCurrency;

    private Customer $customer;

    private CustomerAddress $address;

    private Warehouse $warehouse;

    /** @var array<int, ProductVariant> */
    private array $variants = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');

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
            'name_ar' => 'مستودع الميزانية',
            'name_en' => 'Budget WH',
            'code' => 'WH-BGT',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'عميل الفحص',
            'phone' => '772233445',
            'normalized_phone' => '+967772233445',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'عنوان',
            'contact_name' => 'عميل الفحص',
            'contact_phone' => '772233445',
            'address_line' => 'صنعاء',
            'is_default' => true,
        ]);

        $cat = Category::create(['name_ar' => 'تصنيف ميزانية', 'slug' => 'budget-cat', 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'ماركة ميزانية', 'slug' => 'budget-brand', 'is_active' => true]);

        // Create 20 products and variants
        for ($i = 1; $i <= 20; $i++) {
            $product = Product::create([
                'category_id' => $cat->id,
                'brand_id' => $brand->id,
                'name_ar' => "منتج {$i}",
                'slug' => "product-{$i}",
                'status' => 'active',
            ]);

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => "SKU-BGT-{$i}",
                'status' => ProductVariantStatus::Active,
                'is_default' => true,
            ]);

            VariantPrice::create([
                'product_variant_id' => $variant->id,
                'currency_id' => $this->baseCurrency->id,
                'price' => 100.00 * $i,
            ]);

            Inventory::create([
                'warehouse_id' => $this->warehouse->id,
                'product_variant_id' => $variant->id,
                'quantity' => 50,
            ]);

            $this->variants[$i] = $variant;
        }

        // Active campaigns
        $promo = Promotion::create([
            'name' => 'عرض ميزانية',
            'code' => 'PROMO-BGT',
            'type' => PromotionType::Percentage,
            'value' => 10.00,
            'scope' => PromotionScope::Category,
            'priority' => 1,
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $promo->id,
            'target_type' => 'category',
            'target_id' => $cat->id,
        ]);

        $bundle = Bundle::create([
            'name' => 'حزمة ميزانية',
            'code' => 'BUNDLE-BGT',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 200.00,
            'priority' => 5,
            'is_active' => true,
        ]);
        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variants[1]->id,
            'quantity' => 1,
        ]);
        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variants[2]->id,
            'quantity' => 1,
        ]);

        Coupon::create([
            'name' => 'كوبون ميزانية',
            'code' => 'COUPON-BGT',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 50.00,
            'scope' => 'all',
            'is_compatible_with_promotions' => true,
            'is_active' => true,
        ]);
    }

    public function test_pricing_preview_respects_query_budget(): void
    {
        $this->actingAs($this->admin);

        // 1. Warm-up request with 5 lines
        $warmupItems = [];
        for ($i = 1; $i <= 5; $i++) {
            $warmupItems[] = [
                'product_variant_id' => $this->variants[$i]->id,
                'quantity' => 1,
            ];
        }

        $this->postJson(route('admin.orders.pricing-preview'), [
            'currency_id' => $this->baseCurrency->id,
            'customer_id' => $this->customer->id,
            'coupon_code' => 'COUPON-BGT',
            'items' => $warmupItems,
        ])->assertOk();

        // 2. Measure 10-line request
        $tenLineItems = [];
        for ($i = 1; $i <= 10; $i++) {
            $tenLineItems[] = [
                'product_variant_id' => $this->variants[$i]->id,
                'quantity' => 1,
            ];
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response10 = $this->postJson(route('admin.orders.pricing-preview'), [
            'currency_id' => $this->baseCurrency->id,
            'customer_id' => $this->customer->id,
            'coupon_code' => 'COUPON-BGT',
            'items' => $tenLineItems,
        ]);

        $response10->assertOk();
        $queries10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(30, $queries10, "10-line preview exceeded 30 queries budget (executed {$queries10})");

        // 3. Measure 20-line request
        $twentyLineItems = [];
        for ($i = 1; $i <= 20; $i++) {
            $twentyLineItems[] = [
                'product_variant_id' => $this->variants[$i]->id,
                'quantity' => 1,
            ];
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response20 = $this->postJson(route('admin.orders.pricing-preview'), [
            'currency_id' => $this->baseCurrency->id,
            'customer_id' => $this->customer->id,
            'coupon_code' => 'COUPON-BGT',
            'items' => $twentyLineItems,
        ]);

        $response20->assertOk();
        $queries20 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $delta = abs($queries20 - $queries10);
        $this->assertLessThanOrEqual(
            5,
            $delta,
            "20-line preview added {$delta} queries over 10-line preview (limit: <= 5). Queries 10: {$queries10}, Queries 20: {$queries20}"
        );
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
