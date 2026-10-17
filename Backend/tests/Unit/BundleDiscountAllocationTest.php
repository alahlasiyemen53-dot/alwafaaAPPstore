<?php

namespace Tests\Unit;

use App\Enums\BundlePricingType;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BundlePricingService;
use App\Services\DiscountAllocationService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BundleDiscountAllocationTest extends TestCase
{
    use RefreshDatabase;

    private BundlePricingService $bundleService;

    private DiscountAllocationService $allocationService;

    private ProductVariant $variantA;

    private ProductVariant $variantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->bundleService = app(BundlePricingService::class);
        $this->allocationService = app(DiscountAllocationService::class);

        $category = Category::create([
            'name_ar' => 'تصنيف عام',
            'slug' => 'general-cat',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name_ar' => 'علامة',
            'slug' => 'brand-gen',
            'is_active' => true,
        ]);

        $prodA = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name_ar' => 'منتج أ',
            'name_en' => 'Product A',
            'slug' => 'prod-a',
            'status' => 'active',
        ]);

        $this->variantA = ProductVariant::create([
            'product_id' => $prodA->id,
            'sku' => 'SKU-A',
            'status' => 'active',
            'is_default' => true,
        ]);

        $prodB = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name_ar' => 'منتج ب',
            'name_en' => 'Product B',
            'slug' => 'prod-b',
            'status' => 'active',
        ]);

        $this->variantB = ProductVariant::create([
            'product_id' => $prodB->id,
            'sku' => 'SKU-B',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_fixed_price_bundle_calculation_and_allocation(): void
    {
        // Variant A (price 60), Variant B (price 40). Bundle fixed price 80 (saving 20).
        $bundle = Bundle::create([
            'code' => 'CARE-BUNDLE',
            'name' => 'حزمة العناية',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 80.00,
            'is_active' => true,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 1,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 1,
        ]);

        $items = [
            [
                'product_variant_id' => $this->variantA->id,
                'quantity' => 1,
                'unit_price' => 60.00,
                'line_subtotal' => 60.00,
            ],
            [
                'product_variant_id' => $this->variantB->id,
                'quantity' => 1,
                'unit_price' => 40.00,
                'line_subtotal' => 40.00,
            ],
        ];

        $result = $this->bundleService->evaluateBundles($items);

        $this->assertEquals(20.00, $result['total_bundle_discount']);
        $this->assertCount(2, $result['bundle_info_by_item']);
        $this->assertCount(2, $result['bundle_discounts_by_item']);

        $sumAllocated = array_sum($result['bundle_discounts_by_item']);
        $this->assertEquals(20.00, round($sumAllocated, 2));

        $this->assertEquals(12.00, $result['bundle_discounts_by_item'][0]);
        $this->assertEquals(8.00, $result['bundle_discounts_by_item'][1]);
        $this->assertEquals($bundle->id, $result['bundle_info_by_item'][0]['bundle_id']);
        $this->assertEquals($bundle->id, $result['bundle_info_by_item'][1]['bundle_id']);
    }

    public function test_deterministic_rounding_residual_allocation(): void
    {
        $lines = [
            1 => 100.00,
            2 => 100.00,
            3 => 100.00,
        ];

        $allocated = $this->allocationService->allocate($lines, 10.00);

        $this->assertCount(3, $allocated);
        $this->assertEquals(3.3333, $allocated[1]);
        $this->assertEquals(3.3333, $allocated[2]);
        $this->assertEquals(3.3334, $allocated[3]);

        $totalSum = array_sum($allocated);
        $this->assertEquals(10.00, $totalSum);
    }

    public function test_bundle_single_occurrence_with_surplus_leaves_surplus_undiscounted(): void
    {
        // Variant A (price 60), Variant B (price 40). Bundle fixed price 80 (saving 20).
        $bundle = Bundle::create([
            'code' => 'CARE-BUNDLE-SURPLUS',
            'name' => 'حزمة العناية بفائض',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 80.00,
            'is_active' => true,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 1,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 1,
        ]);

        // A has qty 2 (1 bundle + 1 surplus), B has qty 1
        $items = [
            'item_a' => [
                'product_variant_id' => $this->variantA->id,
                'quantity' => 2,
                'unit_price' => 60.00,
                'line_subtotal' => 120.00,
            ],
            'item_b' => [
                'product_variant_id' => $this->variantB->id,
                'quantity' => 1,
                'unit_price' => 40.00,
                'line_subtotal' => 40.00,
            ],
        ];

        $result = $this->bundleService->evaluateBundles($items);

        // Exactly 1 occurrence applied
        $this->assertEquals(20.00, $result['total_bundle_discount']);
        $this->assertEquals(12.00, $result['bundle_discounts_by_item']['item_a']);
        $this->assertEquals(8.00, $result['bundle_discounts_by_item']['item_b']);

        // 1 of A participated, leaving 1 surplus
        $this->assertEquals('1.0000', $result['bundle_participating_qty_by_item']['item_a']);
        $this->assertEquals('1.0000', $result['bundle_participating_qty_by_item']['item_b']);
    }

    public function test_bundle_multiple_occurrences(): void
    {
        $bundle = Bundle::create([
            'code' => 'CARE-BUNDLE-2X',
            'name' => 'حزمة العناية مضاعفة',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 80.00,
            'is_active' => true,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 1,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 1,
        ]);

        // A has qty 2, B has qty 2 => 2 occurrences
        $items = [
            'item_a' => [
                'product_variant_id' => $this->variantA->id,
                'quantity' => 2,
                'unit_price' => 60.00,
                'line_subtotal' => 120.00,
            ],
            'item_b' => [
                'product_variant_id' => $this->variantB->id,
                'quantity' => 2,
                'unit_price' => 40.00,
                'line_subtotal' => 80.00,
            ],
        ];

        $result = $this->bundleService->evaluateBundles($items);

        // 2 occurrences * 20.00 discount = 40.00
        $this->assertEquals(40.00, $result['total_bundle_discount']);
        $this->assertEquals(24.00, $result['bundle_discounts_by_item']['item_a']);
        $this->assertEquals(16.00, $result['bundle_discounts_by_item']['item_b']);
        $this->assertEquals('2.0000', $result['bundle_participating_qty_by_item']['item_a']);
        $this->assertEquals('2.0000', $result['bundle_participating_qty_by_item']['item_b']);
    }

    public function test_fractional_quantity_insufficient_for_bundle(): void
    {
        $bundle = Bundle::create([
            'code' => 'CARE-BUNDLE-FRAC',
            'name' => 'حزمة العناية جزئية',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 80.00,
            'is_active' => true,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 1,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 1,
        ]);

        // A has qty 0.5 (cannot satisfy required 1.0)
        $items = [
            'item_a' => [
                'product_variant_id' => $this->variantA->id,
                'quantity' => 0.5,
                'unit_price' => 60.00,
                'line_subtotal' => 30.00,
            ],
            'item_b' => [
                'product_variant_id' => $this->variantB->id,
                'quantity' => 1.0,
                'unit_price' => 40.00,
                'line_subtotal' => 40.00,
            ],
        ];

        $result = $this->bundleService->evaluateBundles($items);

        $this->assertEquals(0.00, $result['total_bundle_discount']);
        $this->assertEmpty($result['bundle_discounts_by_item']);
    }

    public function test_fixed_amount_and_percentage_bundle_calculations(): void
    {
        // Percentage Bundle: 25% discount
        $pctBundle = Bundle::create([
            'code' => 'PCT-BUNDLE',
            'name' => 'حزمة خصم نسبة',
            'pricing_type' => BundlePricingType::PercentageDiscount,
            'value' => 25.00,
            'is_active' => true,
        ]);

        BundleItem::create([
            'bundle_id' => $pctBundle->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 1,
        ]);

        BundleItem::create([
            'bundle_id' => $pctBundle->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 1,
        ]);

        $items = [
            'a' => [
                'product_variant_id' => $this->variantA->id,
                'quantity' => 1,
                'unit_price' => 60.00,
                'line_subtotal' => 60.00,
            ],
            'b' => [
                'product_variant_id' => $this->variantB->id,
                'quantity' => 1,
                'unit_price' => 40.00,
                'line_subtotal' => 40.00,
            ],
        ];

        $resultPct = $this->bundleService->evaluateBundles($items);

        // 25% of 100 = 25.00
        $this->assertEquals(25.00, $resultPct['total_bundle_discount']);
        $this->assertEquals(15.00, $resultPct['bundle_discounts_by_item']['a']);
        $this->assertEquals(10.00, $resultPct['bundle_discounts_by_item']['b']);

        // Fixed Amount Bundle: 30 discount
        $pctBundle->delete();
        $this->bundleService->clearCache();

        $fixedBundle = Bundle::create([
            'code' => 'FIXED-AMT-BUNDLE',
            'name' => 'حزمة خصم مبلغ ثابت',
            'pricing_type' => BundlePricingType::FixedAmountDiscount,
            'value' => 30.00,
            'is_active' => true,
        ]);

        BundleItem::create([
            'bundle_id' => $fixedBundle->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 1,
        ]);

        BundleItem::create([
            'bundle_id' => $fixedBundle->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 1,
        ]);

        $resultFixed = $this->bundleService->evaluateBundles($items);

        // Fixed 30.00 discount on 100
        $this->assertEquals(30.00, $resultFixed['total_bundle_discount']);
        $this->assertEquals(18.00, $resultFixed['bundle_discounts_by_item']['a']);
        $this->assertEquals(12.00, $resultFixed['bundle_discounts_by_item']['b']);
    }
}
