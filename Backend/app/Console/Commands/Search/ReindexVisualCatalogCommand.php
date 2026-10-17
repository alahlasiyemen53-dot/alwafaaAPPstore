<?php

namespace App\Console\Commands\Search;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Search\VisualIndexingService;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ReindexVisualCatalogCommand extends Command
{
    protected $signature = 'search:reindex-visual {--chunk=50 : عدد الصور في كل دفعة}';

    protected $description = 'إعادة توليد وتخزين متجهات التشابه البصري (Embeddings) لجميع صور المنتجات';

    public function handle(VisualIndexingService $visualIndexingService): int
    {
        $chunkSize = max(10, (int) $this->option('chunk'));
        $totalMedia = Media::query()->whereIn('model_type', [Product::class, ProductVariant::class])->count();

        $this->info("بدء توليد التضمينات البصرية لعدد ({$totalMedia} صورة) بحجم دفعة {$chunkSize}...");

        if ($totalMedia === 0) {
            $this->warn('لا توجد صور لمنتجات أو متغيرات لفهرستها.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalMedia);
        $bar->start();

        $lastProcessed = 0;
        $totalIndexed = $visualIndexingService->indexAll($chunkSize, function (int $processed, int $total) use ($bar, &$lastProcessed) {
            $advance = $processed - $lastProcessed;
            if ($advance > 0) {
                $bar->advance($advance);
                $lastProcessed = $processed;
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("تم بنجاح توليد وفهرسة {$totalIndexed} تضمين بصري.");

        return self::SUCCESS;
    }
}
