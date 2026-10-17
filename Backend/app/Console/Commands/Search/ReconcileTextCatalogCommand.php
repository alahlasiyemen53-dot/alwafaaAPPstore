<?php

namespace App\Console\Commands\Search;

use App\Services\Search\SearchReconciliationService;
use Illuminate\Console\Command;

final class ReconcileTextCatalogCommand extends Command
{
    protected $signature = 'search:reconcile-text';

    protected $description = 'مطابقة ومزامنة فهرس البحث النصي مع قاعدة بيانات المنتجات';

    public function handle(SearchReconciliationService $reconciliationService): int
    {
        $this->info('بدء مطابقة كتالوج المنتجات في محرك البحث...');

        $report = $reconciliationService->reconcileTextCatalog();

        $this->table(
            ['المؤشر', 'القيمة'],
            [
                ['المنتجات التي تمت مطابقتها وفهرستها', $report['indexed']],
                ['الأخطاء المسجلة', $report['errors']],
            ]
        );

        if ($report['errors'] > 0) {
            $this->warn('تمت المطابقة مع وجود بعض الأخطاء. تفقد سجلات النظام (laravel.log).');

            return self::FAILURE;
        }

        $this->info('تمت مطابقة الفهرس النصي بنجاح تام.');

        return self::SUCCESS;
    }
}
