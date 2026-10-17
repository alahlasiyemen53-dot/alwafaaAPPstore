<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CustomerProductDetailApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Warehouse $warehouse;

    private Brand $brand;

    private Category $category;

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

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المخزن الرئيسي',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->brand = Brand::create(['name_ar' => 'ماركة أصيلة', 'slug' => 'authentic-brand', 'is_active' => true]);
        $this->category = Category::create(['name_ar' => 'عطور نسائية', 'slug' => 'women-perfumes', 'is_active' => true]);
    }

    public function test_product_detail_returns_active_product_with_variants_and_attributes(): void
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'عطر الياسمين الملكي',
            'name_en' => 'Royal Jasmine',
            'slug' => 'royal-jasmine',
            'description_ar' => 'عطر نسائي فاخر بخلاصة زهور الياسمين.',
            'description_en' => 'Luxury perfume with jasmine extracts.',
            'status' => ProductStatus::Active,
            'is_best_seller' => true,
        ]);

        $sizeAttr = Attribute::create(['name_ar' => 'الحجم', 'name_en' => 'Size', 'code' => 'size', 'is_active' => true]);
        $val50 = AttributeValue::create(['attribute_id' => $sizeAttr->id, 'value_ar' => '50 مل', 'value_en' => '50ml', 'code' => '50ml', 'is_active' => true]);
        $val100 = AttributeValue::create(['attribute_id' => $sizeAttr->id, 'value_ar' => '100 مل', 'value_en' => '100ml', 'code' => '100ml', 'is_active' => true]);

        // Variant 1 (50ml)
        $var1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ROYAL-50',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
        ]);
        $var1->attributeValues()->attach($val50->id, ['attribute_id' => $sizeAttr->id]);
        VariantPrice::create([
            'product_variant_id' => $var1->id,
            'currency_id' => $this->currency->id,
            'price' => 12000.00,
            'compare_at_price' => 15000.00,
            'is_active' => true,
        ]);
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $var1->id,
            'quantity' => 5.0,
            'reserved_quantity' => 0.0,
        ]);

        // Variant 2 (100ml)
        $var2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ROYAL-100',
            'status' => ProductVariantStatus::Active,
            'is_default' => false,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
        ]);
        $var2->attributeValues()->attach($val100->id, ['attribute_id' => $sizeAttr->id]);
        VariantPrice::create([
            'product_variant_id' => $var2->id,
            'currency_id' => $this->currency->id,
            'price' => 20000.00,
            'is_active' => true,
        ]);

        // Rating
        $customer = Customer::create(['name' => 'سارة', 'phone' => '772223344', 'normalized_phone' => '+967772223344', 'is_active' => true]);
        ProductReview::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'rating' => 5, 'status' => ProductReviewStatus::Approved]);

        // Detail request by ID
        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name_ar', 'عطر الياسمين الملكي')
            ->assertJsonPath('data.description_ar', 'عطر نسائي فاخر بخلاصة زهور الياسمين.')
            ->assertJsonPath('data.badges.is_best_seller', true)
            ->assertJsonPath('data.rating_summary.average_rating', 5)
            ->assertJsonPath('data.rating_summary.reviews_count', 1)
            ->assertJsonPath('data.variants.0.id', $var1->id)
            ->assertJsonPath('data.variants.0.sku', 'ROYAL-50')
            ->assertJsonPath('data.variants.0.is_default', true)
            ->assertJsonPath('data.variants.0.price.amount', '12000.00')
            ->assertJsonPath('data.variants.0.price.compare_at', '15000.00')
            ->assertJsonPath('data.variants.0.price.has_discount', true)
            ->assertJsonPath('data.variants.0.availability.is_available', true)
            ->assertJsonPath('data.variants.0.availability.status', 'in_stock')
            ->assertJsonPath('data.variants.0.attributes.0.code', 'size')
            ->assertJsonPath('data.variants.0.attributes.0.value_ar', '50 مل')
            ->assertJsonPath('data.variants.1.id', $var2->id)
            ->assertJsonPath('data.variants.1.sku', 'ROYAL-100')
            ->assertJsonPath('data.variants.1.price.amount', '20000.00');
    }

    public function test_product_detail_by_slug_succeeds(): void
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'عطر العود الفاخر',
            'name_en' => 'Luxury Oud',
            'slug' => 'luxury-oud-special',
            'status' => ProductStatus::Active,
        ]);

        $response = $this->getJson('/api/v1/products/luxury-oud-special');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.slug', 'luxury-oud-special');
    }

    public function test_product_detail_returns_each_variant_own_media_gallery(): void
    {
        Storage::fake('public');

        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج بصور خيارات',
            'slug' => 'variant-media-product',
            'status' => ProductStatus::Active,
        ]);
        $firstVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VARIANT-IMAGE-A',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);
        $secondVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VARIANT-IMAGE-B',
            'status' => ProductVariantStatus::Active,
        ]);

        $firstVariant->addMedia(UploadedFile::fake()->image('red.jpg'))
            ->toMediaCollection('variant_images');
        $secondVariant->addMedia(UploadedFile::fake()->image('blue.jpg'))
            ->toMediaCollection('variant_images');

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.variants.0.images.0.url', $firstVariant->fresh()->getFirstMediaUrl('variant_images'))
            ->assertJsonPath('data.variants.1.images.0.url', $secondVariant->fresh()->getFirstMediaUrl('variant_images'));
    }

    public function test_product_detail_returns_404_for_inactive_or_missing_product(): void
    {
        $inactive = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج مغلق',
            'slug' => 'closed-product',
            'status' => ProductStatus::Inactive,
        ]);

        $respInactive = $this->getJson("/api/v1/products/{$inactive->id}");
        $respInactive->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::ProductNotFound->value);

        $respMissing = $this->getJson('/api/v1/products/nonexistent-item-999');
        $respMissing->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::ProductNotFound->value);
    }
}
