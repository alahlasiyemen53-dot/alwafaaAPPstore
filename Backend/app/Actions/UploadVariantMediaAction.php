<?php

namespace App\Actions;

use App\Models\ProductVariant;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UploadVariantMediaAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ProductVariant $variant, UploadedFile $file, bool $isPrimary = false, ?string $name = null): Media
    {
        return DB::transaction(function () use ($variant, $file, $isPrimary, $name) {
            $existingMedia = $variant->getMedia('variant_images');
            $shouldBePrimary = $isPrimary || $existingMedia->isEmpty();

            if ($shouldBePrimary && $existingMedia->isNotEmpty()) {
                foreach ($existingMedia as $existing) {
                    if ($existing->getCustomProperty('is_primary', false)) {
                        $existing->setCustomProperty('is_primary', false);
                        $existing->save();
                    }
                }
            }

            $adder = $variant->addMedia($file)
                ->withCustomProperties(['is_primary' => $shouldBePrimary]);

            if ($name !== null && trim($name) !== '') {
                $adder->usingName(trim($name));
            }

            /** @var Media $media */
            $media = $adder->toMediaCollection('variant_images');

            $this->auditLogger->record(
                action: 'catalog.variant.media.uploaded',
                subject: $variant,
                metadata: [
                    'media_id' => $media->id,
                    'file_name' => $media->file_name,
                    'size' => $media->size,
                    'is_primary' => $shouldBePrimary,
                    'product_id' => $variant->product_id,
                ],
            );

            return $media;
        });
    }
}
