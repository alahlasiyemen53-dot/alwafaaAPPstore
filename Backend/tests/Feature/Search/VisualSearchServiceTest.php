<?php

namespace Tests\Feature\Search;

use App\Contracts\Search\VisualVectorStoreInterface;
use App\DTOs\Search\VisualCandidate;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Services\Search\DeterministicMockEmbeddingGenerator;
use App\Services\Search\SearchResultRehydrationService;
use App\Services\Search\VisualSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VisualSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_visual_search_deduplicates_candidates_and_rehydrates(): void
    {
        $brand = Brand::create(['name_ar' => 'سوني', 'name_en' => 'Sony', 'slug' => 'sony', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'كاميرات', 'name_en' => 'Cameras', 'slug' => 'cameras', 'is_active' => true]);

        $productA = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'كاميرا سوني A7',
            'name_en' => 'Sony A7 Camera',
            'slug' => 'sony-a7',
            'status' => ProductStatus::Active,
        ]);

        $productB = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'عدسة سوني 50mm',
            'name_en' => 'Sony 50mm Lens',
            'slug' => 'sony-50mm',
            'status' => ProductStatus::Active,
        ]);

        // Mock VisualVectorStore returning multiple candidates, including two media from Product A
        $vectorStoreMock = new class($productA->id, $productB->id) implements VisualVectorStoreInterface
        {
            public function __construct(private int $pA, private int $pB) {}

            public function upsertVector(string $vectorId, array $vector, array $metadata = []): bool
            {
                return true;
            }

            public function deleteVector(string $vectorId): bool
            {
                return true;
            }

            public function count(): int
            {
                return 3;
            }

            public function querySimilar(array $queryVector, int $limit = 50, float $minSimilarity = 0.60, ?string $modelVersion = null): array
            {
                return [
                    new VisualCandidate(productId: $this->pA, variantId: null, mediaId: 101, similarity: 0.95, vectorId: 'media_101'),
                    new VisualCandidate(productId: $this->pB, variantId: null, mediaId: 201, similarity: 0.88, vectorId: 'media_201'),
                    new VisualCandidate(productId: $this->pA, variantId: null, mediaId: 102, similarity: 0.82, vectorId: 'media_102'), // Duplicate of pA
                ];
            }
        };

        $generator = new DeterministicMockEmbeddingGenerator(dimension: 128);
        $rehydrationService = new SearchResultRehydrationService;

        $service = new VisualSearchService(
            embeddingGenerator: $generator,
            vectorStore: $vectorStoreMock,
            rehydrationService: $rehydrationService,
        );

        $fakeVector = array_fill(0, 128, 0.05);
        $result = $service->searchByVector($fakeVector, limit: 10, minSimilarity: 0.50);

        // Product A should appear only ONCE (deduplicated), followed by Product B
        $this->assertSame(2, $result->items->count());
        $this->assertSame($productA->id, $result->items->get(0)->id);
        $this->assertSame($productB->id, $result->items->get(1)->id);

        // Top candidate for Product A should have similarity 0.95
        $this->assertSame(0.95, $result->candidates[0]->similarity);
        $this->assertSame($productA->id, $result->candidates[0]->productId);
        $this->assertSame(101, $result->candidates[0]->mediaId);

        // Total raw candidates should be 3
        $this->assertSame(3, $result->totalCandidates);
    }
}
