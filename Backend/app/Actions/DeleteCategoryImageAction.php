<?php

namespace App\Actions;

use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class DeleteCategoryImageAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $media = $category->getFirstMedia('category_image');

            if ($media === null) {
                return;
            }

            $fileName = $media->file_name;
            $mediaId = $media->id;
            $media->delete();

            $this->auditLogger->record(
                action: 'catalog.category.image.deleted',
                subject: $category,
                metadata: [
                    'media_id' => $mediaId,
                    'file_name' => $fileName,
                ],
            );
        });
    }
}
