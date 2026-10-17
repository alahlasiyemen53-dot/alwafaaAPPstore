<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CatalogMediaPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CatalogMediaPresenterTest extends TestCase
{
    use RefreshDatabase;

    private CatalogMediaPresenter $presenter;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->presenter = new CatalogMediaPresenter;

        $brand = Brand::query()->create(['name_ar' => 'براند', 'slug' => 'brand-1', 'is_active' => true]);
        $category = Category::query()->create(['name_ar' => 'قسم', 'slug' => 'cat-1', 'is_active' => true]);

        $this->product = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'منتج تجريبي',
            'slug' => 'test-product',
            'status' => 'active',
            'has_variants' => true,
        ]);
    }

    public function test_default_placeholder_url_returns_placeholder_asset(): void
    {
        $url = $this->presenter->getDefaultPlaceholderUrl();
        self::assertStringContainsString('placeholder.svg', $url);
    }

    public function test_product_primary_url_falls_back_to_placeholder_when_no_media(): void
    {
        $url = $this->presenter->getPrimaryUrlForProduct($this->product);
        self::assertStringContainsString('placeholder.svg', $url);
    }

    public function test_variant_primary_url_falls_back_to_parent_product_and_then_placeholder(): void
    {
        $variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'sku' => 'SKU-001',
            'status' => 'active',
            'is_default' => true,
        ]);

        // When neither has media -> placeholder
        self::assertStringContainsString('placeholder.svg', $this->presenter->getPrimaryUrlForVariant($variant));

        // When product has media but variant does not -> parent primary image
        $productFile = UploadedFile::fake()->image('prod.jpg', 600, 600);
        $productMedia = $this->product->addMedia($productFile)
            ->withCustomProperties(['is_primary' => true])
            ->toMediaCollection('product_images');

        $this->product->refresh();
        $variant->refresh();

        self::assertSame($productMedia->getUrl(), $this->presenter->getPrimaryUrlForVariant($variant));

        // When variant has its own media -> variant media
        $variantFile = UploadedFile::fake()->image('variant.jpg', 600, 600);
        $variantMedia = $variant->addMedia($variantFile)
            ->withCustomProperties(['is_primary' => true])
            ->toMediaCollection('variant_images');

        $variant->refresh();
        self::assertSame($variantMedia->getUrl(), $this->presenter->getPrimaryUrlForVariant($variant));
    }

    public function test_present_product_and_present_variant_return_media_item_data(): void
    {
        $variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'sku' => 'SKU-002',
            'status' => 'active',
            'is_default' => true,
        ]);

        $file1 = UploadedFile::fake()->image('p1.jpg', 600, 600);
        $this->product->addMedia($file1)->withCustomProperties(['is_primary' => true])->toMediaCollection('product_images');

        $file2 = UploadedFile::fake()->image('v1.jpg', 600, 600);
        $variant->addMedia($file2)->withCustomProperties(['is_primary' => true])->toMediaCollection('variant_images');

        $productItems = $this->presenter->presentProduct($this->product->fresh());
        $variantItems = $this->presenter->presentVariant($variant->fresh());

        self::assertCount(1, $productItems);
        self::assertTrue($productItems[0]->isPrimary);
        self::assertSame('p1.jpg', $productItems[0]->fileName);

        self::assertCount(1, $variantItems);
        self::assertTrue($variantItems[0]->isPrimary);
        self::assertSame('v1.jpg', $variantItems[0]->fileName);
    }
}
