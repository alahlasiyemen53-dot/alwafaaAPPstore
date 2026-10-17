<?php

namespace App\Actions;

use App\Models\ProductVariant;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SetPrimaryVariantMediaAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ProductVariant $variant, int $mediaId): Media
    {
        return DB::transaction(function () use ($variant, $mediaId) {
            /** @var Media $targetMedia */
            $targetMedia = $variant->media()
                ->where('collection_name', 'variant_images')
                ->where('id', $mediaId)
                ->firstOrFail();

            foreach ($variant->getMedia('variant_images') as $media) {
                $isTarget = $media->id === $targetMedia->id;
                $currentPrimary = (bool) $media->getCustomProperty('is_primary', false);

                if ($isTarget && ! $currentPrimary) {
                    $media->setCustomProperty('is_primary', true);
                    $media->save();
                } elseif (! $isTarget && $currentPrimary) {
                    $media->setCustomProperty('is_primary', false);
                    $media->save();
                }
            }

            $this->auditLogger->record(
                action: 'catalog.variant.media.primary_set',
                subject: $variant,
                metadata: [
                    'media_id' => $mediaId,
                    'file_name' => $targetMedia->file_name,
                    'product_id' => $variant->product_id,
                ],
            );

            return $targetMedia;
        });
    }
}
