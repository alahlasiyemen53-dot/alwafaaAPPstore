<?php

namespace App\Actions;

use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UploadCategoryImageAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Category $category, UploadedFile $file): Media
    {
        return DB::transaction(function () use ($category, $file) {
            /** @var Media $media */
            $media = $category->addMedia($file)->toMediaCollection('category_image');

            $this->auditLogger->record(
                action: 'catalog.category.image.uploaded',
                subject: $category,
                metadata: [
                    'media_id' => $media->id,
                    'file_name' => $media->file_name,
                    'size' => $media->size,
                ],
            );

            return $media;
        });
    }
}
