<?php

namespace App\Console\Commands\Search;

use App\Models\Product;
use App\Services\Search\ProductIndexingService;
use Illuminate\Console\Command;

final class ReindexTextCatalogCommand extends Command
{
    protected $signature = 'search:reindex-text {--chunk=100 : عدد المنتجات في كل دفعة}';

    protected $description = 'إعادة فهرسة جميع المنتجات في محرك البحث Meilisearch بنظام الدفعات';

    public function handle(ProductIndexingService $indexingService): int
    {
        $chunkSize = max(10, (int) $this->option('chunk'));
        $totalProducts = Product::count();

        $this->info("بدء إعادة فهرسة كتالوج المنتجات ({$totalProducts} منتج) بحجم دفعة {$chunkSize}...");

        if ($totalProducts === 0) {
            $this->warn('لا توجد منتجات لفهرستها.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

        $lastProcessed = 0;
        $totalIndexed = $indexingService->indexAll($chunkSize, function (int $processed, int $total) use ($bar, &$lastProcessed) {
            $advance = $processed - $lastProcessed;
            if ($advance > 0) {
                $bar->advance($advance);
                $lastProcessed = $processed;
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("تمت فهرسة {$totalIndexed} منتج بنجاح في Meilisearch.");

        return self::SUCCESS;
    }
}
