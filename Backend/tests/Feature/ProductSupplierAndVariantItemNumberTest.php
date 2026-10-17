<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariantPrice;
use App\Services\Catalog\ProductAvailabilityService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProductSupplierAndVariantItemNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Brand $brand;

    private Category $category;

    private Supplier $supplierA;

    private Supplier $supplierB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);

        $this->brand = Brand::query()->create([
            'name_ar' => 'علامة مورد',
            'slug' => 'brand-supplier-test',
            'is_active' => true,
        ]);

        $this->category = Category::query()->create([
            'name_ar' => 'عناية',
            'slug' => 'care-supplier-test',
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::query()->create([
            'code' => 'SUP-A',
            'name' => 'مورد أ',
            'phone' => '770000001',
            'is_active' => true,
        ]);

        $this->supplierB = Supplier::query()->create([
            'code' => 'SUP-B',
            'name' => 'مورد ب',
            'phone' => '770000002',
            'is_active' => true,
        ]);
    }

    public function test_migration_columns_exist_and_models_expose_relations(): void
    {
        self::assertTrue(Schema::hasColumn('products', 'supplier_id'));
        self::assertTrue(Schema::hasColumn('products', 'supplier_item_number'));
        self::assertTrue(Schema::hasColumn('product_variants', 'supplier_item_number'));
        self::assertTrue(Schema::hasColumn('order_items', 'supplier_item_number_snapshot'));

        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name_ar' => 'منتج علاقات',
            'slug' => 'relation-product',
            'status' => ProductStatus::Draft,
            'supplier_id' => $this->supplierA->id,
        ]);

        self::assertTrue($product->supplier->is($this->supplierA));
        self::assertTrue($this->supplierA->products()->whereKey($product->id)->exists());
    }

    public function test_admin_can_set_product_supplier_id(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/products', [
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج بمورد',
            'name_en' => 'With supplier',
            'slug' => 'with-supplier',
            'status' => 'draft',
            'has_variants' => '1',
            'supplier_id' => $this->supplierA->id,
        ])->assertRedirect();

        $product = Product::query()->where('slug', 'with-supplier')->firstOrFail();
        self::assertSame($this->supplierA->id, $product->supplier_id);
    }

    public function test_admin_can_set_product_supplier_item_number(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/products', [
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج برقم مورد',
            'name_en' => 'With supplier item',
            'slug' => 'with-product-item-no',
            'status' => 'draft',
            'has_variants' => '1',
            'supplier_id' => $this->supplierA->id,
            'supplier_item_number' => '  PROD-REF-9  ',
        ])->assertRedirect();

        $product = Product::query()->where('slug', 'with-product-item-no')->firstOrFail();
        self::assertSame('PROD-REF-9', $product->supplier_item_number);
    }

    public function test_admin_can_set_variant_supplier_item_number(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name_ar' => 'منتج متغير',
            'slug' => 'variant-item-no',
            'status' => ProductStatus::Active,
            'has_variants' => true,
            'supplier_id' => $this->supplierA->id,
        ]);

        $currency = Currency::query()->where('is_default', true)->firstOrFail();

        $this->actingAs($this->admin)->post("/admin/products/{$product->id}/variants", [
            '_modal' => 'addVariantModal',
            'status' => ProductVariantStatus::Active->value,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked->value,
            'price' => '100',
            'supplier_item_number' => '  ABC-123  ',
        ])->assertRedirect();

        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
        self::assertSame('ABC-123', $variant->supplier_item_number);
    }

    public function test_supplier_change_without_confirm_is_blocked_when_item_numbers_exist(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name_ar' => 'منتج تغيير مورد',
            'slug' => 'supplier-change',
            'status' => ProductStatus::Draft,
            'supplier_id' => $this->supplierA->id,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-SUP-CHANGE',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
            'supplier_item_number' => 'OLD-REF-1',
        ]);

        $this->actingAs($this->admin)->put("/admin/products/{$product->id}", [
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج تغيير مورد',
            'supplier_id' => $this->supplierB->id,
        ])->assertSessionHasErrors('supplier_id');

        self::assertSame($this->supplierA->id, $product->fresh()->supplier_id);
    }

    public function test_confirmed_supplier_change_clears_variant_supplier_item_numbers(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name_ar' => 'منتج تأكيد',
            'slug' => 'supplier-change-confirm',
            'status' => ProductStatus::Draft,
            'supplier_id' => $this->supplierA->id,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-CONFIRM',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
            'supplier_item_number' => 'OLD-REF-2',
        ]);

        $this->actingAs($this->admin)->put("/admin/products/{$product->id}", [
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج تأكيد',
            'supplier_id' => $this->supplierB->id,
            'confirm_supplier_change' => '1',
        ])->assertRedirect();

        self::assertSame($this->supplierB->id, $product->fresh()->supplier_id);
        self::assertNull($variant->fresh()->supplier_item_number);
    }

    public function test_customer_product_detail_json_does_not_expose_supplier_fields(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name_ar' => 'منتج خصوصية',
            'slug' => 'privacy-product',
            'status' => ProductStatus::Active,
            'supplier_id' => $this->supplierA->id,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-PRIV',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
            'fulfillment_mode' => ProductVariantFulfillmentMode::OnDemand,
            'supplier_id' => $this->supplierA->id,
            'supplier_item_number' => 'SECRET-SUP-REF',
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::query()->where('is_default', true)->value('id'),
            'price' => '1000',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");
        $response->assertOk();
        $json = $response->getContent();
        self::assertStringNotContainsString('supplier_item_number', $json);
        self::assertStringNotContainsString('SECRET-SUP-REF', $json);
        self::assertStringNotContainsString('supplier_id', $json);
        self::assertStringNotContainsString('مورد أ', $json);
    }

    public function test_on_demand_sellable_with_product_supplier_only(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name_ar' => 'منتج توفير',
            'slug' => 'ondemand-product-supplier',
            'status' => ProductStatus::Active,
            'supplier_id' => $this->supplierA->id,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-OD-PROD-SUP',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
            'fulfillment_mode' => ProductVariantFulfillmentMode::OnDemand,
            'supplier_id' => null,
        ]);

        $result = app(ProductAvailabilityService::class)->evaluate($variant);
        self::assertTrue($result->isSellable);
    }

    public function test_sku_remains_immutable_after_supplier_item_number_update(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id,
            'name_ar' => 'ثبات SKU',
            'slug' => 'sku-immutable-supplier',
            'status' => ProductStatus::Active,
            'has_variants' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-LOCK-001',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
            'supplier_item_number' => 'REF-1',
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'currency_id' => Currency::query()->where('is_default', true)->value('id'),
            'price' => '50',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->put("/admin/products/{$product->id}/variants/{$variant->id}", [
            '_modal' => 'editVariantModal'.$variant->id,
            'status' => ProductVariantStatus::Active->value,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked->value,
            'price' => '50',
            'supplier_item_number' => 'REF-2',
        ])->assertRedirect();

        self::assertSame('SKU-LOCK-001', $variant->fresh()->sku);
        self::assertSame('REF-2', $variant->fresh()->supplier_item_number);
    }
}
