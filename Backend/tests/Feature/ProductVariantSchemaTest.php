<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProductVariantSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_tables_contain_only_the_approved_catalog_and_pricing_fields(): void
    {
        $expectedColumns = [
            'attributes' => ['id', 'name_ar', 'name_en', 'code', 'is_active', 'sort_order', 'created_at', 'updated_at'],
            'attribute_values' => ['id', 'attribute_id', 'value_ar', 'value_en', 'code', 'sort_order', 'is_active', 'created_at', 'updated_at'],
            'product_variants' => ['id', 'product_id', 'sku', 'barcode', 'status', 'fulfillment_mode', 'supplier_id', 'supplier_item_number', 'cost_price', 'is_default', 'created_at', 'updated_at'],
            'product_variant_values' => ['product_variant_id', 'attribute_id', 'attribute_value_id'],
            'variant_prices' => ['id', 'product_variant_id', 'currency_id', 'price', 'compare_at_price', 'is_active', 'created_at', 'updated_at'],
        ];

        foreach ($expectedColumns as $table => $columns) {
            self::assertTrue(Schema::hasTable($table));
            self::assertEqualsCanonicalizing($columns, Schema::getColumnListing($table));
        }

        $decimalType = DB::getDriverName() === 'sqlite' ? 'numeric' : 'decimal(18,4)';
        self::assertSame($decimalType, Schema::getColumnType('variant_prices', 'price', true));
        self::assertSame($decimalType, Schema::getColumnType('variant_prices', 'compare_at_price', true));
    }

    public function test_variant_schema_defines_required_foreign_keys_and_indexes(): void
    {
        $this->assertForeignKey('attribute_values', ['attribute_id'], ['attributes', ['id'], 'restrict']);
        $this->assertForeignKey('product_variants', ['product_id'], ['products', ['id'], 'restrict']);
        $this->assertForeignKey('product_variant_values', ['product_variant_id'], ['product_variants', ['id'], 'cascade']);
        $this->assertForeignKey('product_variant_values', ['attribute_id'], ['attributes', ['id'], 'restrict']);
        $this->assertForeignKey(
            'product_variant_values',
            ['attribute_value_id', 'attribute_id'],
            ['attribute_values', ['id', 'attribute_id'], 'restrict'],
        );
        $this->assertForeignKey('variant_prices', ['product_variant_id'], ['product_variants', ['id'], 'cascade']);
        $this->assertForeignKey('variant_prices', ['currency_id'], ['currencies', ['id'], 'restrict']);

        $this->assertIndex('attributes', ['code'], true);
        $this->assertIndex('attribute_values', ['attribute_id', 'code'], true);
        $this->assertIndex('attribute_values', ['id', 'attribute_id'], true);
        $this->assertIndex('product_variants', ['sku'], true);
        $this->assertIndex('product_variants', ['barcode'], true);
        $this->assertIndex('product_variants', ['product_id', 'is_default'], false);
        $this->assertIndex('product_variants', ['status'], false);
        $this->assertIndex('product_variant_values', ['product_variant_id', 'attribute_id'], true);
        $this->assertIndex('product_variant_values', ['product_variant_id', 'attribute_value_id'], true);
        $this->assertIndex('product_variant_values', ['attribute_id'], false);
        $this->assertIndex('product_variant_values', ['attribute_value_id', 'attribute_id'], false);
        $this->assertIndex('variant_prices', ['product_variant_id', 'currency_id'], true);
        $this->assertIndex('variant_prices', ['currency_id'], false);
    }

    public function test_attribute_code_is_database_unique(): void
    {
        $this->attribute('shade');

        $this->expectException(QueryException::class);
        $this->attribute('shade');
    }

    public function test_attribute_value_codes_are_unique_within_their_attribute(): void
    {
        $shade = $this->attribute('shade');
        $size = $this->attribute('size');
        $this->attributeValue($shade, 'light');
        $this->attributeValue($size, 'light');

        $this->expectException(QueryException::class);
        $this->attributeValue($shade, 'light');
    }

    public function test_variant_sku_is_database_unique(): void
    {
        $product = $this->product();
        $this->variant($product, 'SKU-001');

        $this->expectException(QueryException::class);
        $this->variant($product, 'SKU-001');
    }

    public function test_non_null_variant_barcode_is_database_unique(): void
    {
        $product = $this->product();
        $this->variant($product, 'SKU-001', '123456789');
        $this->variant($product, 'SKU-002');
        $this->variant($product, 'SKU-003');

        $this->expectException(QueryException::class);
        $this->variant($product, 'SKU-004', '123456789');
    }

    public function test_variant_accepts_only_one_value_for_an_attribute(): void
    {
        $variant = $this->variant($this->product(), 'SKU-001');
        $shade = $this->attribute('shade');
        $light = $this->attributeValue($shade, 'light');
        $dark = $this->attributeValue($shade, 'dark');
        $this->assign($variant, $shade, $light);

        $this->expectException(QueryException::class);
        $this->assign($variant, $shade, $dark);
    }

    public function test_attribute_value_must_belong_to_the_assigned_attribute(): void
    {
        $variant = $this->variant($this->product(), 'SKU-001');
        $shade = $this->attribute('shade');
        $finish = $this->attribute('finish');
        $matte = $this->attributeValue($finish, 'matte');

        $this->expectException(QueryException::class);
        $this->assign($variant, $shade, $matte);
    }

    public function test_variant_has_only_one_current_price_per_currency(): void
    {
        $variant = $this->variant($this->product(), 'SKU-001');
        $currency = Currency::query()->create([
            'code' => 'USD',
            'name_ar' => 'دولار أمريكي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'currency_id' => $currency->id,
            'price' => '10.0000',
            'compare_at_price' => '12.0000',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'currency_id' => $currency->id,
            'price' => '11.0000',
            'is_active' => true,
        ]);
    }

    public function test_models_expose_the_variant_catalog_graph_with_typed_casts(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'SKU-001');
        $shade = $this->attribute('shade');
        $light = $this->attributeValue($shade, 'light');
        $this->assign($variant, $shade, $light);
        $currency = Currency::query()->create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'decimal_places' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);
        $price = VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'currency_id' => $currency->id,
            'price' => '7500',
            'is_active' => true,
        ]);

        self::assertTrue($product->variants->firstOrFail()->is($variant));
        self::assertTrue($variant->product->is($product));
        self::assertTrue($shade->values->firstOrFail()->is($light));
        self::assertTrue($variant->attributeValues->firstOrFail()->is($light));
        self::assertTrue($variant->prices->firstOrFail()->is($price));
        self::assertTrue($currency->variantPrices->firstOrFail()->is($price));
        self::assertSame(ProductVariantStatus::Active, $variant->status);
        self::assertTrue($variant->is_default);
        self::assertSame('7500.0000', $price->price);
        self::assertNull($price->compare_at_price);
    }

    private function product(): Product
    {
        $category = Category::query()->create([
            'name_ar' => 'مكياج',
            'slug' => fake()->unique()->slug(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name_ar' => 'منتج تجريبي',
            'slug' => fake()->unique()->slug(),
            'status' => ProductStatus::Draft,
            'has_variants' => true,
        ]);
    }

    private function attribute(string $code): Attribute
    {
        return Attribute::query()->create([
            'name_ar' => $code,
            'code' => $code,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function attributeValue(Attribute $attribute, string $code): AttributeValue
    {
        return AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value_ar' => $code,
            'code' => $code,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function variant(Product $product, string $sku, ?string $barcode = null): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'barcode' => $barcode,
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);
    }

    private function assign(ProductVariant $variant, Attribute $attribute, AttributeValue $attributeValue): void
    {
        DB::table('product_variant_values')->insert([
            'product_variant_id' => $variant->id,
            'attribute_id' => $attribute->id,
            'attribute_value_id' => $attributeValue->id,
        ]);
    }

    private function assertForeignKey(string $table, array $columns, array $reference): void
    {
        $matchingKeys = array_filter(
            Schema::getForeignKeys($table),
            fn (array $key): bool => $key['columns'] === $columns
                && $key['foreign_table'] === $reference[0]
                && $key['foreign_columns'] === $reference[1]
                && $key['on_delete'] === $reference[2],
        );

        self::assertCount(1, $matchingKeys);
    }

    private function assertIndex(string $table, array $columns, bool $unique): void
    {
        $matchingIndexes = array_filter(
            Schema::getIndexes($table),
            fn (array $index): bool => $index['columns'] === $columns && $index['unique'] === $unique,
        );

        self::assertCount(1, $matchingIndexes);
    }
}
