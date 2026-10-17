<?php

namespace Tests\Feature\Search;

use App\Contracts\Search\MeilisearchClientInterface;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\Search\MeilisearchIndexManager;
use App\Services\Search\ProductIndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductIndexingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_document_and_index_product(): void
    {
        $brand = Brand::create(['name_ar' => 'شاومي', 'name_en' => 'Xiaomi', 'slug' => 'xiaomi', 'is_active' => true]);

        $parentCat = Category::create(['name_ar' => 'إلكترونيات', 'name_en' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);
        $subCat = Category::create(['parent_id' => $parentCat->id, 'name_ar' => 'هواتف ذكية', 'name_en' => 'Smartphones', 'slug' => 'smartphones', 'is_active' => true]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $subCat->id,
            'name_ar' => 'ريدمي نوت 13',
            'name_en' => 'Redmi Note 13',
            'slug' => 'redmi-note-13',
            'description_ar' => 'هاتف ذكي ممتاز',
            'description_en' => 'Great smartphone',
            'status' => ProductStatus::Active,
            'has_variants' => true,
        ]);

        $variant1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'REDMI-13-128GB',
            'barcode' => '6901234567890',
            'status' => 'active',
            'is_default' => true,
        ]);

        $currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant1->id,
            'currency_id' => $currency->id,
            'price' => 150000.00,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant1->id,
            'quantity' => 25.0,
            'reserved_quantity' => 5.0,
        ]);

        $mockClient = new class implements MeilisearchClientInterface
        {
            public array $addedDocuments = [];

            public array $deletedDocuments = [];

            public function isHealthy(): bool
            {
                return true;
            }

            public function getStats(string $indexUid): array
            {
                return ['numberOfDocuments' => 1];
            }

            public function createIndexIfNotExists(string $indexUid, string $primaryKey = 'id'): array
            {
                return [];
            }

            public function updateSettings(string $indexUid, array $settings): array
            {
                return [];
            }

            public function addDocuments(string $indexUid, array $documents, ?string $primaryKey = 'id'): array
            {
                $this->addedDocuments = array_merge($this->addedDocuments, $documents);

                return ['taskUid' => 1];
            }

            public function deleteDocument(string $indexUid, string|int $documentId): array
            {
                $this->deletedDocuments[] = $documentId;

                return ['taskUid' => 2];
            }

            public function deleteDocuments(string $indexUid, array $documentIds): array
            {
                return [];
            }

            public function search(string $indexUid, string $query, array $options = []): array
            {
                return [];
            }

            public function deleteAllDocuments(string $indexUid): array
            {
                return [];
            }
        };

        $indexManager = new MeilisearchIndexManager($mockClient, 'test_');
        $indexingService = new ProductIndexingService($mockClient, $indexManager);

        // 1. Build document inspection
        $doc = $indexingService->buildDocument($product);

        $this->assertSame($product->id, $doc->id);
        $this->assertSame('ريدمي نوت 13', $doc->nameAr);
        $this->assertSame('شاومي', $doc->brandNameAr);
        $this->assertSame('هواتف ذكية', $doc->categoryNameAr);
        $this->assertTrue(in_array($subCat->id, $doc->categoryIds));
        $this->assertTrue(in_array($parentCat->id, $doc->categoryIds));
        $this->assertTrue($doc->hasStock);
        $this->assertSame(20, $doc->totalStock); // 25 - 5
        $this->assertSame(15000000, $doc->minPriceCents);
        $this->assertSame(['REDMI-13-128GB'], $doc->skus);
        $this->assertSame(['6901234567890'], $doc->barcodes);
        $this->assertSame(['YER'], $doc->currencies);

        // 2. Index product
        $indexed = $indexingService->indexProduct($product);
        $this->assertTrue($indexed);
        $this->assertCount(1, $mockClient->addedDocuments);
        $this->assertSame($product->id, $mockClient->addedDocuments[0]['id']);

        // 3. Unindex product
        $unindexed = $indexingService->unindexProduct($product);
        $this->assertTrue($unindexed);
        $this->assertSame([$product->id], $mockClient->deletedDocuments);
    }
}
