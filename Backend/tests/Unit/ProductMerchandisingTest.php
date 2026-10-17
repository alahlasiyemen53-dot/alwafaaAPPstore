<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductMerchandisingTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->brand = Brand::query()->create(['name_ar' => 'براند تجريبي', 'slug' => 'test-brand', 'is_active' => true]);
        $this->category = Category::query()->create(['name_ar' => 'تصنيف تجريبي', 'slug' => 'test-category', 'is_active' => true]);
    }

    public function test_product_merchandising_flags_default_to_false(): void
    {
        $product = Product::query()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج تجريبي',
            'slug' => 'test-product',
        ]);

        self::assertFalse($product->is_best_seller);
        self::assertFalse($product->is_new);
        self::assertFalse($product->is_exclusive);
    }

    public function test_product_merchandising_flags_are_cast_to_boolean(): void
    {
        $product = Product::query()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج تجريبي 2',
            'slug' => 'test-product-2',
            'is_best_seller' => 1,
            'is_new' => true,
            'is_exclusive' => 0,
        ]);

        $fresh = $product->fresh();
        self::assertTrue($fresh->is_best_seller);
        self::assertTrue($fresh->is_new);
        self::assertFalse($fresh->is_exclusive);
    }

    public function test_product_merchandising_scopes(): void
    {
        $p1 = Product::query()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج 1',
            'slug' => 'product-1',
            'is_best_seller' => true,
            'is_new' => false,
            'is_exclusive' => true,
        ]);

        $p2 = Product::query()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج 2',
            'slug' => 'product-2',
            'is_best_seller' => false,
            'is_new' => true,
            'is_exclusive' => false,
        ]);

        self::assertSame([$p1->id], Product::query()->bestSeller()->pluck('id')->all());
        self::assertSame([$p2->id], Product::query()->bestSeller(false)->pluck('id')->all());

        self::assertSame([$p2->id], Product::query()->newMerchandising()->pluck('id')->all());
        self::assertSame([$p1->id], Product::query()->newMerchandising(false)->pluck('id')->all());

        self::assertSame([$p1->id], Product::query()->exclusive()->pluck('id')->all());
        self::assertSame([$p2->id], Product::query()->exclusive(false)->pluck('id')->all());
    }
}
