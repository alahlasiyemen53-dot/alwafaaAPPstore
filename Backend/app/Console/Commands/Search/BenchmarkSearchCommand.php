<?php

namespace App\Console\Commands\Search;

use App\Contracts\Search\ProductSearchInterface;
use App\Contracts\Search\VisualProductSearchInterface;
use App\DTOs\Search\ProductSearchQuery;
use App\Models\MediaEmbedding;
use App\Models\Product;
use Illuminate\Console\Command;

final class BenchmarkSearchCommand extends Command
{
    protected $signature = 'search:benchmark {--samples=10 : عدد العينات للاختبار}';

    protected $description = 'قياس دقة وزمن استجابة محرك البحث النصي والبصري (Recall@5, Recall@10, MRR, Latency)';

    public function handle(
        ProductSearchInterface $textSearch,
        VisualProductSearchInterface $visualSearch,
    ): int {
        $samplesCount = max(5, (int) $this->option('samples'));

        $this->info('==================================================');
        $this->info('  تقييم أداء محرك البحث (Text & Visual Benchmark)  ');
        $this->info('==================================================');
        $this->newLine();

        // ----------------------------------------------------
        // 1. Text Search Benchmark
        // ----------------------------------------------------
        $this->info('1. تشغيل قياسات البحث النصي (Arabic, English, SKU)...');

        $products = Product::query()
            ->with(['brand', 'category', 'variants'])
            ->where('status', 'active')
            ->inRandomOrder()
            ->take($samplesCount)
            ->get();

        if ($products->isEmpty()) {
            $this->warn('لا توجد منتجات نشطة كافية لإجراء الاختبار.');

            return self::SUCCESS;
        }

        $textLatencies = [];
        $reciprocalRanks = [];
        $recallAt5 = 0;
        $recallAt10 = 0;
        $totalQueries = 0;

        foreach ($products as $product) {
            $queriesToTest = [
                (string) $product->name_ar,
                (string) $product->name_en,
            ];

            $variant = $product->variants->first();
            if ($variant && ! empty($variant->sku)) {
                $queriesToTest[] = (string) $variant->sku;
            }

            foreach ($queriesToTest as $queryString) {
                if (trim($queryString) === '') {
                    continue;
                }

                $totalQueries++;
                $searchQuery = new ProductSearchQuery(
                    query: $queryString,
                    page: 1,
                    perPage: 10,
                );

                $start = microtime(true);
                $result = $textSearch->search($searchQuery);
                $durationMs = (microtime(true) - $start) * 1000;
                $textLatencies[] = $durationMs;

                // Evaluate ranking of target product
                $targetId = $product->id;
                $rank = null;
                $index = 1;

                foreach ($result->items as $item) {
                    if ($item->id === $targetId) {
                        $rank = $index;
                        break;
                    }
                    $index++;
                }

                if ($rank !== null) {
                    $reciprocalRanks[] = 1.0 / $rank;
                    if ($rank <= 5) {
                        $recallAt5++;
                    }
                    if ($rank <= 10) {
                        $recallAt10++;
                    }
                } else {
                    $reciprocalRanks[] = 0.0;
                }
            }
        }

        sort($textLatencies);
        $count = count($textLatencies);
        $p50 = $count > 0 ? $textLatencies[(int) floor($count * 0.50)] : 0;
        $p95 = $count > 0 ? $textLatencies[(int) floor($count * 0.95)] : 0;
        $mrr = $totalQueries > 0 ? (array_sum($reciprocalRanks) / $totalQueries) : 0;
        $r5Rate = $totalQueries > 0 ? ($recallAt5 / $totalQueries) * 100 : 0;
        $r10Rate = $totalQueries > 0 ? ($recallAt10 / $totalQueries) * 100 : 0;

        $this->table(
            ['مقياس البحث النصي', 'النتيجة', 'المعيار المستهدف', 'الحالة'],
            [
                ['إجمالي الاستعلامات', $totalQueries, '-', 'OK'],
                ['Mean Reciprocal Rank (MRR)', number_format($mrr, 4), '>= 0.70', $mrr >= 0.70 ? 'PASS' : 'REVIEW'],
                ['Recall@5', number_format($r5Rate, 1).'%', '>= 80%', $r5Rate >= 80 ? 'PASS' : 'REVIEW'],
                ['Recall@10', number_format($r10Rate, 1).'%', '>= 90%', $r10Rate >= 90 ? 'PASS' : 'REVIEW'],
                ['Latency P50', number_format($p50, 2).' ms', '< 50 ms', $p50 < 50 ? 'PASS' : 'WARN'],
                ['Latency P95', number_format($p95, 2).' ms', '< 150 ms', $p95 < 150 ? 'PASS' : 'WARN'],
            ]
        );

        $this->newLine();

        // ----------------------------------------------------
        // 2. Visual Similarity Search Benchmark
        // ----------------------------------------------------
        $this->info('2. تشغيل قياسات البحث البصري بالمتجهات (Visual Vector Search)...');

        $embeddings = MediaEmbedding::query()
            ->where('status', MediaEmbedding::STATUS_INDEXED)
            ->whereNotNull('embedding_vector')
            ->take($samplesCount)
            ->get();

        if ($embeddings->isEmpty()) {
            $this->warn('لا توجد متجهات صور مفهرسة لاختبار البحث البصري. قم بتشغيل search:reindex-visual أولاً.');

            return self::SUCCESS;
        }

        $visualLatencies = [];
        $top1Hits = 0;
        $vRecallAt5 = 0;
        $vTotal = 0;

        foreach ($embeddings as $embedding) {
            /** @var array<int, float> $queryVector */
            $queryVector = $embedding->embedding_vector;
            if (empty($queryVector)) {
                continue;
            }

            $vTotal++;
            $start = microtime(true);
            $vResult = $visualSearch->searchByVector($queryVector, limit: 5, minSimilarity: 0.50);
            $durationMs = (microtime(true) - $start) * 1000;
            $visualLatencies[] = $durationMs;

            $foundRank = null;
            $rank = 1;
            foreach ($vResult->items as $product) {
                if ($product->id === $embedding->product_id) {
                    $foundRank = $rank;
                    break;
                }
                $rank++;
            }

            if ($foundRank === 1) {
                $top1Hits++;
            }
            if ($foundRank !== null && $foundRank <= 5) {
                $vRecallAt5++;
            }
        }

        sort($visualLatencies);
        $vCount = count($visualLatencies);
        $vp50 = $vCount > 0 ? $visualLatencies[(int) floor($vCount * 0.50)] : 0;
        $vp95 = $vCount > 0 ? $visualLatencies[(int) floor($vCount * 0.95)] : 0;
        $top1Rate = $vTotal > 0 ? ($top1Hits / $vTotal) * 100 : 0;
        $vRecallRate = $vTotal > 0 ? ($vRecallAt5 / $vTotal) * 100 : 0;

        $this->table(
            ['مقياس البحث البصري', 'النتيجة', 'المعيار المستهدف', 'الحالة'],
            [
                ['عينات الصور المختبرة', $vTotal, '-', 'OK'],
                ['Top-1 Accuracy', number_format($top1Rate, 1).'%', '>= 90%', $top1Rate >= 90 ? 'PASS' : 'REVIEW'],
                ['Recall@5', number_format($vRecallRate, 1).'%', '>= 95%', $vRecallRate >= 95 ? 'PASS' : 'REVIEW'],
                ['Latency P50', number_format($vp50, 2).' ms', '< 80 ms', $vp50 < 80 ? 'PASS' : 'WARN'],
                ['Latency P95', number_format($vp95, 2).' ms', '< 200 ms', $vp95 < 200 ? 'PASS' : 'WARN'],
            ]
        );

        $this->newLine();
        $this->info('اكتملت جميع اختبارات الأداء بنجاح.');

        return self::SUCCESS;
    }
}
