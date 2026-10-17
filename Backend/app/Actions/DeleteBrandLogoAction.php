<?php

namespace App\Actions;

use App\Models\Brand;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class DeleteBrandLogoAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Brand $brand): void
    {
        DB::transaction(function () use ($brand): void {
            $media = $brand->getFirstMedia('brand_logo');

            if ($media === null) {
                return;
            }

            $fileName = $media->file_name;
            $mediaId = $media->id;
            $media->delete();

            $this->auditLogger->record(
                action: 'catalog.brand.logo.deleted',
                subject: $brand,
                metadata: [
                    'media_id' => $mediaId,
                    'file_name' => $fileName,
                ],
            );
        });
    }
}
