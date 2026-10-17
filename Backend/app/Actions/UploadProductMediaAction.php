<?php

namespace App\Actions;

use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UploadProductMediaAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Product $product, UploadedFile $file, bool $isPrimary = false, ?string $name = null): Media
    {
        return DB::transaction(function () use ($product, $file, $isPrimary, $name) {
            $existingMedia = $product->getMedia('product_images');
            $shouldBePrimary = $isPrimary || $existingMedia->isEmpty();

            if ($shouldBePrimary && $existingMedia->isNotEmpty()) {
                foreach ($existingMedia as $existing) {
                    if ($existing->getCustomProperty('is_primary', false)) {
                        $existing->setCustomProperty('is_primary', false);
                        $existing->save();
                    }
                }
            }

            $adder = $product->addMedia($file)
                ->withCustomProperties(['is_primary' => $shouldBePrimary]);

            if ($name !== null && trim($name) !== '') {
                $adder->usingName(trim($name));
            }

            /** @var Media $media */
            $media = $adder->toMediaCollection('product_images');

            $this->auditLogger->record(
                action: 'catalog.product.media.uploaded',
                subject: $product,
                metadata: [
                    'media_id' => $media->id,
                    'file_name' => $media->file_name,
                    'size' => $media->size,
                    'is_primary' => $shouldBePrimary,
                ],
            );

            return $media;
        });
    }
}
