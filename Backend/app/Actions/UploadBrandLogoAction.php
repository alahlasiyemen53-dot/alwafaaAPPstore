<?php

namespace App\Actions;

use App\Models\Brand;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UploadBrandLogoAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Brand $brand, UploadedFile $file): Media
    {
        return DB::transaction(function () use ($brand, $file) {
            /** @var Media $media */
            $media = $brand->addMedia($file)->toMediaCollection('brand_logo');

            $this->auditLogger->record(
                action: 'catalog.brand.logo.uploaded',
                subject: $brand,
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
