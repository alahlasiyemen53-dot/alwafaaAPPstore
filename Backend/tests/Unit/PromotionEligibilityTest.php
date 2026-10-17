<?php

namespace Tests\Unit;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Services\PromotionEligibilityService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PromotionEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private PromotionEligibilityService $service;

    private Category $category;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->service = app(PromotionEligibilityService::class);

        $this->category = Category::create([
            'name_ar' => 'المشروبات',
            'slug' => 'beverages',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name_ar' => 'علامة تجارية',
            'slug' => 'brand-bev',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'brand_id' => $brand->id,
            'name_ar' => 'عصير برتقال',
            'name_en' => 'Orange Juice',
            'slug' => 'orange-juice',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'OJ-500ML',
            'barcode' => '1234567890123',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_percentage_discount_is_capped_at_line_subtotal(): void
    {
        $promo = Promotion::create([
            'code' => 'OVER-100',
            'name' => 'خصم يتجاوز قيمة السطر',
            'type' => PromotionType::Percentage,
            'scope' => PromotionScope::All,
            'value' => 120.00,
            'is_active' => true,
            'priority' => 10,
        ]);

        $result = $this->service->findBestPromotion($this->variant, 1000.00, 1);

        $this->assertEquals($promo->id, $result['promotion']->id);
        $this->assertEquals(1000.00, $result['discount_amount']);
        $this->assertEquals(1000.00, $result['discount_per_unit']);
    }

    public function test_fixed_amount_discount_scales_with_quantity(): void
    {
        $promo = Promotion::create([
            'code' => 'FIXED-15',
            'name' => 'خصم 15 ريال للقطعة',
            'type' => PromotionType::FixedAmount,
            'scope' => PromotionScope::All,
            'value' => 15.00,
            'is_active' => true,
            'priority' => 5,
        ]);

        $result = $this->service->findBestPromotion($this->variant, 100.00, 5);

        $this->assertEquals($promo->id, $result['promotion']->id);
        $this->assertEquals(75.00, $result['discount_amount']);
        $this->assertEquals(15.00, $result['discount_per_unit']);
    }

    public function test_fixed_price_discount(): void
    {
        $promo = Promotion::create([
            'code' => 'FIXED-PRICE-70',
            'name' => 'سعر خاص 70 ريال',
            'type' => PromotionType::FixedPrice,
            'scope' => PromotionScope::All,
            'value' => 70.00,
            'is_active' => true,
            'priority' => 5,
        ]);

        $result = $this->service->findBestPromotion($this->variant, 100.00, 1);

        $this->assertEquals($promo->id, $result['promotion']->id);
        $this->assertEquals(30.00, $result['discount_amount']);
        $this->assertEquals(30.00, $result['discount_per_unit']);
    }

    public function test_variant_scope_matches_targeted_variant(): void
    {
        $promoVariant = Promotion::create([
            'code' => 'VARIANT-OFFER',
            'name' => 'عرض لمتغير محدد',
            'type' => PromotionType::FixedAmount,
            'scope' => PromotionScope::Variant,
            'value' => 25.00,
            'is_active' => true,
            'priority' => 10,
        ]);
        PromotionTarget::create([
            'promotion_id' => $promoVariant->id,
            'target_type' => 'variant',
            'target_id' => $this->variant->id,
        ]);

        $result = $this->service->findBestPromotion($this->variant, 100.00, 1);

        $this->assertEquals($promoVariant->id, $result['promotion']->id);
        $this->assertEquals(25.00, $result['discount_per_unit']);
    }

    public function test_priority_tie_breaker(): void
    {
        Promotion::create([
            'code' => 'LOW-PRIORITY',
            'name' => 'عرض ذو أولوية منخفضة',
            'type' => PromotionType::FixedAmount,
            'scope' => PromotionScope::All,
            'value' => 15.00,
            'is_active' => true,
            'priority' => 5,
        ]);

        $highPriority = Promotion::create([
            'code' => 'HIGH-PRIORITY',
            'name' => 'عرض ذو أولوية عالية',
            'type' => PromotionType::FixedAmount,
            'scope' => PromotionScope::All,
            'value' => 15.00,
            'is_active' => true,
            'priority' => 20,
        ]);

        $result = $this->service->findBestPromotion($this->variant, 100.00, 1);

        $this->assertEquals($highPriority->id, $result['promotion']->id);
        $this->assertEquals(15.00, $result['discount_per_unit']);
    }

    #[DataProvider('unavailablePromotionStates')]
    public function test_unavailable_promotion_is_excluded(string $code, string $name, array $state): void
    {
        Promotion::create(array_merge([
            'code' => $code,
            'name' => $name,
            'type' => PromotionType::FixedAmount,
            'scope' => PromotionScope::All,
            'value' => 10.00,
            'is_active' => true,
            'priority' => 10,
        ], $state));

        $result = $this->service->findBestPromotion($this->variant, 100.00, 1);

        $this->assertNull($result['promotion']);
        $this->assertEquals(0.0, $result['discount_amount']);
    }

    public static function unavailablePromotionStates(): array
    {
        return [
            'expired' => ['EXPIRED', 'عرض منتهي', [
                'starts_at' => '2000-01-01 00:00:00',
                'ends_at' => '2000-01-02 00:00:00',
            ]],
            'inactive' => ['INACTIVE', 'عرض معطل', ['is_active' => false]],
        ];
    }
}
