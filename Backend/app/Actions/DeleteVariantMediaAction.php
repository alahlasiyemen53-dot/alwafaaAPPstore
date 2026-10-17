<?php

namespace App\Actions;

use App\Models\ProductVariant;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class DeleteVariantMediaAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ProductVariant $variant, int $mediaId): bool
    {
        return DB::transaction(function () use ($variant, $mediaId) {
            /** @var Media $media */
            $media = $variant->media()
                ->where('collection_name', 'variant_images')
                ->where('id', $mediaId)
                ->firstOrFail();

            $wasPrimary = (bool) $media->getCustomProperty('is_primary', false);
            $fileName = $media->file_name;

            $media->delete();

            if ($wasPrimary) {
                // Promote first remaining image to primary
                /** @var Media|null $firstRemaining */
                $firstRemaining = $variant->getMedia('variant_images')->sortBy('order_column')->first();
                if ($firstRemaining) {
                    $firstRemaining->setCustomProperty('is_primary', true);
                    $firstRemaining->save();
                }
            }

            $this->auditLogger->record(
                action: 'catalog.variant.media.deleted',
                subject: $variant,
                metadata: [
                    'media_id' => $mediaId,
                    'file_name' => $fileName,
                    'was_primary' => $wasPrimary,
                    'product_id' => $variant->product_id,
                ],
            );

            return true;
        });
    }
}
