<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Actions\CreateProductAction;
use App\Actions\CreateProductVariantAction;
use App\Actions\UpdateProductAction;
use App\Actions\UpdateProductVariantAction;
use App\Data\CreateProductData;
use App\Data\ProductVariantData;
use App\Data\UpdateProductData;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MasterDataService;
use App\Services\SkuGenerator;
use App\Services\SlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImmutableSkuAndSlugTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Category $category;

    private Brand $brand;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name_ar' => 'العناية بالبشرة',
            'name_en' => 'Skin Care',
            'slug' => 'skin-care',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name_ar' => 'سيرافي',
            'name_en' => 'CeraVe',
            'slug' => 'cerave',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@lamma.test',
        ]);
    }

    public function test_sku_generator_produces_unique_bounded_skus(): void
    {
        $generator = new SkuGenerator;

        $sku1 = $generator->generate();
        $sku2 = $generator->generate();

        $this->assertNotEmpty($sku1);
        $this->assertNotEmpty($sku2);
        $this->assertNotEquals($sku1, $sku2);
        $this->assertLessThanOrEqual(64, strlen($sku1));
        $this->assertMatchesRegularExpression('/^SKU-[A-Z0-9]+$/', $sku1);
    }

    public function test_slug_generator_handles_english_and_arabic_with_collision_resolution(): void
    {
        $generator = new SlugGenerator;

        $slug1 = $generator->generate('Moisturizing Cream', Product::class);
        $this->assertEquals('moisturizing-cream', $slug1);

        // Create product with this slug
        Product::create([
            'category_id' => $this->category->id,
            'name_ar' => 'كريم مرطب',
            'name_en' => 'Moisturizing Cream',
            'slug' => $slug1,
            'status' => ProductStatus::Active,
        ]);

        // Second creation with same name should resolve collision with -2
        $slug2 = $generator->generate('Moisturizing Cream', Product::class);
        $this->assertEquals('moisturizing-cream-2', $slug2);

        // Arabic title generation
        $slugAr = $generator->generate('غسول وجه فاخر', Product::class);
        $this->assertNotEmpty($slugAr);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugAr);
    }

    public function test_create_product_action_generates_sku_for_single_variant_products(): void
    {
        $createAction = app(CreateProductAction::class);

        $productData = new CreateProductData(
            brandId: $this->brand->id,
            categoryId: $this->category->id,
            nameAr: 'منتج فردي',
            nameEn: 'Single Product',
            slug: 'single-product-auto',
            status: ProductStatus::Active,
            descriptionAr: null,
            descriptionEn: null,
            hasVariants: false,
        );

        $product = $createAction->execute($productData);

        $this->assertCount(1, $product->variants);
        $variant = $product->variants->first();

        $this->assertNotNull($variant->sku);
        $this->assertNotEmpty($variant->sku);
        $this->assertMatchesRegularExpression('/^SKU-[A-Z0-9]+$/', $variant->sku);
        $this->assertNotEquals(strtoupper($product->slug), $variant->sku);
    }

    public function test_create_product_variant_action_generates_sku_if_empty(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name_ar' => 'منتج متعدد',
            'name_en' => 'Multi Variant Product',
            'slug' => 'multi-variant-product',
            'status' => ProductStatus::Active,
            'has_variants' => true,
        ]);

        $action = app(CreateProductVariantAction::class);

        $variantData = new ProductVariantData(
            productId: $product->id,
            sku: '', // empty to trigger auto-generation
            barcode: null,
            status: ProductVariantStatus::Active,
            isDefault: true,
            attributeValueIds: [],
            price: '1500.00',
            compareAtPrice: null,
            fulfillmentMode: ProductVariantFulfillmentMode::Stocked,
        );

        $variant = $action->execute($variantData);

        $this->assertNotEmpty($variant->sku);
        $this->assertMatchesRegularExpression('/^SKU-[A-Z0-9]+$/', $variant->sku);
    }

    public function test_sku_is_strictly_immutable_on_variant_update(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name_ar' => 'منتج اختبار',
            'name_en' => 'Test Product',
            'slug' => 'test-product-sku-immutability',
            'status' => ProductStatus::Active,
            'has_variants' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IMMUTABLE-SKU-001',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
        ]);

        $updateAction = app(UpdateProductVariantAction::class);

        $variantData = new ProductVariantData(
            productId: $product->id,
            sku: 'HACKED-TAMPERED-SKU',
            barcode: '123456789',
            status: ProductVariantStatus::Active,
            isDefault: true,
            attributeValueIds: [],
            price: '2500.00',
            compareAtPrice: null,
        );

        $updated = $updateAction->execute($variant, $variantData);

        // SKU must remain original!
        $this->assertEquals('IMMUTABLE-SKU-001', $updated->sku);
        $this->assertEquals('IMMUTABLE-SKU-001', $variant->fresh()->sku);

        // Direct model update attempt is also protected by booted listener
        $variant->update(['sku' => 'DIRECT-TAMPER']);
        $this->assertEquals('IMMUTABLE-SKU-001', $variant->fresh()->sku);
    }

    public function test_product_slug_is_strictly_immutable_when_name_is_updated(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name_ar' => 'غسول رغوي',
            'name_en' => 'Foaming Cleanser',
            'slug' => 'foaming-cleanser',
            'status' => ProductStatus::Active,
        ]);

        $updateAction = app(UpdateProductAction::class);

        $updateData = new UpdateProductData(
            brandId: $this->brand->id,
            categoryId: $this->category->id,
            nameAr: 'غسول رغوي مطوّر 2026',
            nameEn: 'Foaming Cleanser Advanced 2026',
            slug: 'foaming-cleanser-advanced-2026', // Tampered slug attempted
        );

        $updated = $updateAction->execute($product, $updateData);

        // Name is updated, but slug MUST remain unchanged!
        $this->assertEquals('غسول رغوي مطوّر 2026', $updated->name_ar);
        $this->assertEquals('foaming-cleanser', $updated->slug);
        $this->assertEquals('foaming-cleanser', $product->fresh()->slug);

        // Direct model update attempt is also protected by booted listener
        $product->update(['slug' => 'tampered-slug-direct']);
        $this->assertEquals('foaming-cleanser', $product->fresh()->slug);
    }

    public function test_category_and_brand_slugs_are_automatically_generated_and_immutable(): void
    {
        $masterDataService = app(MasterDataService::class);

        // Category creation via MasterDataService
        $catData = $masterDataService->prepareStoreData('categories', Category::class, [
            'name_ar' => 'مستحضرات التجميل',
            'name_en' => 'Cosmetics',
        ]);

        $this->assertEquals('cosmetics', $catData['slug']);
        $category = Category::create($catData);

        // Category update
        $updateCatData = $masterDataService->prepareUpdateData('categories', [
            'name_ar' => 'مستحضرات التجميل الفاخرة',
            'slug' => 'luxury-cosmetics',
        ]);

        $this->assertArrayNotHasKey('slug', $updateCatData);
        $category->update($updateCatData);
        $this->assertEquals('cosmetics', $category->fresh()->slug);

        // Brand creation via MasterDataService
        $brandData = $masterDataService->prepareStoreData('brands', Brand::class, [
            'name_ar' => 'لوريال',
            'name_en' => "L'Oreal",
        ]);

        $this->assertNotEmpty($brandData['slug']);
        $brand = Brand::create($brandData);
        $originalBrandSlug = $brand->slug;

        // Brand update
        $updateBrandData = $masterDataService->prepareUpdateData('brands', [
            'name_ar' => 'لوريال باريس',
            'slug' => 'loreal-paris',
        ]);

        $this->assertArrayNotHasKey('slug', $updateBrandData);
        $brand->update($updateBrandData);
        $this->assertEquals($originalBrandSlug, $brand->fresh()->slug);
    }

    public function test_public_product_endpoint_resolves_via_immutable_slug(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name_ar' => 'عطر الورد',
            'name_en' => 'Rose Perfume',
            'slug' => 'rose-perfume',
            'status' => ProductStatus::Active,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ROSE-PERFUME-100',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);

        // Request via original slug
        $response = $this->getJson('/api/v1/products/rose-perfume');
        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'rose-perfume')
            ->assertJsonPath('data.name_ar', 'عطر الورد');

        // Rename the product
        $product->update(['name_ar' => 'عطر الورد الفاخر والمميز']);

        // URL /api/v1/products/rose-perfume MUST still work and return the updated name
        $responseAfterRename = $this->getJson('/api/v1/products/rose-perfume');
        $responseAfterRename->assertStatus(200)
            ->assertJsonPath('data.slug', 'rose-perfume')
            ->assertJsonPath('data.name_ar', 'عطر الورد الفاخر والمميز');
    }
}
