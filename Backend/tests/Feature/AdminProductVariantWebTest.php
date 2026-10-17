<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantPrice;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminProductVariantWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private Currency $baseCurrency;

    private Currency $sarCurrency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);

        $category = Category::query()->create([
            'name_ar' => 'عناية بالبشرة',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'name_ar' => 'سيروم فيتامين سي',
            'slug' => 'vitamin-c-serum',
            'status' => ProductStatus::Active,
            'has_variants' => true,
        ]);

        $this->baseCurrency = Currency::query()->where('code', 'YER')->firstOrFail();
        $this->sarCurrency = Currency::query()->where('code', 'SAR')->firstOrFail();

        ExchangeRate::query()->create([
            'from_currency_id' => $this->baseCurrency->id,
            'to_currency_id' => $this->sarCurrency->id,
            'rate' => '0.00188000',
            'effective_at' => now()->subMinute(),
        ]);
    }

    public function test_authorized_admin_can_create_variant_with_attributes_and_base_price(): void
    {
        $this->actingAs($this->admin);

        $shade = Attribute::query()->create([
            'name_ar' => 'الدرجة',
            'code' => 'shade',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $light = AttributeValue::query()->create([
            'attribute_id' => $shade->id,
            'value_ar' => 'فاتح',
            'code' => 'light',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->post(route('admin.products.variants.store', $this->product), [
            'sku' => 'VIT-C-LIGHT',
            'barcode' => '628100000001',
            'status' => 'active',
            'is_default' => '1',
            'price' => '25000',
            'compare_at_price' => '30000',
            'attribute_value_ids' => [$light->id],
        ]);

        $response->assertRedirect(route('admin.products.show', $this->product));

        $variant = ProductVariant::query()->where('sku', 'VIT-C-LIGHT')->firstOrFail();
        self::assertSame($this->product->id, $variant->product_id);
        self::assertTrue($variant->is_default);
        self::assertSame(ProductVariantStatus::Active, $variant->status);
        self::assertTrue($variant->attributeValues->contains($light));

        $price = VariantPrice::query()->where('product_variant_id', $variant->id)->firstOrFail();
        self::assertSame($this->baseCurrency->id, $price->currency_id);
        self::assertEquals('25000.0000', $price->price);
        self::assertEquals('30000.0000', $price->compare_at_price);

        // Verify show page displays variant and converted previews
        $showPage = $this->get(route('admin.products.show', $this->product));
        $showPage->assertOk();
        $showPage->assertSee('VIT-C-LIGHT');
        $showPage->assertSee('25,000 ر.ي');
        // 25000 * 0.00188 = 47.00 SAR
        $showPage->assertSee('47.00 ر.س');
    }

    public function test_default_variant_switching_is_atomic(): void
    {
        $this->actingAs($this->admin);

        $variant1 = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'sku' => 'VAR-1',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);

        $variant2 = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'sku' => 'VAR-2',
            'status' => ProductVariantStatus::Active,
            'is_default' => false,
        ]);

        $this->patch(route('admin.products.variants.set-default', [$this->product, $variant2]))
            ->assertRedirect();

        self::assertFalse($variant1->fresh()->is_default);
        self::assertTrue($variant2->fresh()->is_default);
    }

    public function test_duplicate_combination_on_same_product_is_rejected(): void
    {
        $this->actingAs($this->admin);

        $shade = Attribute::query()->create(['name_ar' => 'الدرجة', 'code' => 'shade', 'is_active' => true, 'sort_order' => 1]);
        $light = AttributeValue::query()->create(['attribute_id' => $shade->id, 'value_ar' => 'فاتح', 'code' => 'light', 'is_active' => true, 'sort_order' => 1]);

        $this->post(route('admin.products.variants.store', $this->product), [
            'sku' => 'VAR-1',
            'price' => '1000',
            'status' => 'active',
            'attribute_value_ids' => [$light->id],
        ])->assertRedirect();

        // Second variant with same attribute combination should be rejected
        $this->post(route('admin.products.variants.store', $this->product), [
            'sku' => 'VAR-2',
            'price' => '1200',
            'status' => 'active',
            'attribute_value_ids' => [$light->id],
        ])->assertSessionHas('error');

        self::assertDatabaseMissing('product_variants', ['sku' => 'VAR-2']);
    }

    public function test_variant_price_update_works_and_audits(): void
    {
        $this->actingAs($this->admin);

        $variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'sku' => 'VAR-PRICE-TEST',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);

        $this->put(route('admin.products.variants.price.update', [$this->product, $variant]), [
            'price' => '18500',
            'compare_at_price' => '22000',
        ])->assertRedirect();

        $price = VariantPrice::query()->where('product_variant_id', $variant->id)->firstOrFail();
        self::assertEquals('18500.0000', $price->price);
        self::assertEquals('22000.0000', $price->compare_at_price);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.variant_price.updated',
            'subject_id' => $price->id,
        ]);
    }

    public function test_cross_product_variant_modification_is_rejected_with_404(): void
    {
        $this->actingAs($this->admin);

        $category = Category::query()->firstOrFail();
        $otherProduct = Product::query()->create([
            'category_id' => $category->id,
            'name_ar' => 'منتج آخر',
            'slug' => 'other-product',
            'status' => ProductStatus::Active,
            'has_variants' => true,
        ]);

        $foreignVariant = ProductVariant::query()->create([
            'product_id' => $otherProduct->id,
            'sku' => 'FOREIGN-SKU',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);

        $this->put(route('admin.products.variants.update', [$this->product, $foreignVariant]), [
            'sku' => 'FOREIGN-SKU-HACK',
            'status' => 'active',
            'price' => '500',
        ])->assertNotFound();
    }

    public function test_updating_variant_with_empty_attribute_strings_preserves_its_sku(): void
    {
        $this->actingAs($this->admin);

        $variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'sku' => 'VAR-EMPTY-ATTR-TEST',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);

        $response = $this->put(route('admin.products.variants.update', [$this->product, $variant]), [
            'sku' => 'VAR-EMPTY-ATTR-UPDATED',
            'status' => 'active',
            'price' => '15000',
            'compare_at_price' => '',
            'attribute_value_ids' => ['', ''],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('admin.products.show', $this->product));
        self::assertSame('VAR-EMPTY-ATTR-TEST', $variant->fresh()->sku);
    }

    public function test_creating_variant_with_empty_attribute_strings_succeeds_without_validation_integer_error(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.products.variants.store', $this->product), [
            'sku' => 'VAR-EMPTY-STORE-TEST',
            'status' => 'active',
            'price' => '12000',
            'attribute_value_ids' => ['', ''],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('admin.products.show', $this->product));
        self::assertDatabaseHas('product_variants', ['sku' => 'VAR-EMPTY-STORE-TEST']);
    }

    public function test_compare_at_price_less_than_price_is_rejected(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.products.variants.store', $this->product), [
            'sku' => 'VAR-INVALID-COMPARE',
            'status' => 'active',
            'price' => '10000',
            'compare_at_price' => '8000',
        ]);

        $response->assertSessionHasErrors(['compare_at_price']);
    }
}
