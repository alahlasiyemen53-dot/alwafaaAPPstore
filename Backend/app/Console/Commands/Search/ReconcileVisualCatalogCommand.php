<?php

namespace App\Console\Commands\Search;

use App\Services\Search\SearchReconciliationService;
use Illuminate\Console\Command;

final class ReconcileVisualCatalogCommand extends Command
{
    protected $signature = 'search:reconcile-visual';

    protected $description = 'مطابقة وتنظيف متجهات البحث البصري وتوليد المتجهات المفقودة';

    public function handle(SearchReconciliationService $reconciliationService): int
    {
        $this->info('بدء مطابقة وفحص متجهات البحث البصري...');

        $report = $reconciliationService->reconcileVisualEmbeddings();

        $this->table(
            ['المؤشر', 'القيمة'],
            [
                ['المتجهات الجديدة المفهرسة', $report['indexed']],
                ['المتجهات اليتيمة المحذوفة', $report['cleaned']],
                ['الأخطاء المسجلة', $report['errors']],
            ]
        );

        if ($report['errors'] > 0) {
            $this->warn('تمت المطابقة البصرية مع وجود بعض الأخطاء.');

            return self::FAILURE;
        }

        $this->info('تمت مطابقة متجهات البحث البصري بنجاح.');

        return self::SUCCESS;
    }
}
