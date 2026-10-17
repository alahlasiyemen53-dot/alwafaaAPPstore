<?php

namespace App\Console\Commands\Search;

use App\Services\Search\MeilisearchIndexManager;
use Illuminate\Console\Command;

final class SetupSearchIndicesCommand extends Command
{
    protected $signature = 'search:setup-indices';

    protected $description = 'تهيئة فهارس Meilisearch وضبط قواعد الترتيب والبحث والتسامح الإملائي';

    public function handle(MeilisearchIndexManager $indexManager): int
    {
        $this->info('بدء تهيئة فهرس كتالوج المنتجات في Meilisearch...');

        $result = $indexManager->setupCatalogIndex();

        $this->table(
            ['العنصر', 'النتيجة'],
            [
                ['اسم الفهرس', $result['index']],
                ['حالة الإنشاء', json_encode($result['create_result'], JSON_UNESCAPED_UNICODE)],
                ['حالة الإعدادات', json_encode($result['settings_result'], JSON_UNESCAPED_UNICODE)],
            ]
        );

        $this->info('تمت تهيئة الفهرس بنجاح.');

        return self::SUCCESS;
    }
}
