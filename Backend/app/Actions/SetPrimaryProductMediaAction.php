<?php

namespace App\Actions;

use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SetPrimaryProductMediaAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Product $product, int $mediaId): Media
    {
        return DB::transaction(function () use ($product, $mediaId) {
            /** @var Media $targetMedia */
            $targetMedia = $product->media()
                ->where('collection_name', 'product_images')
                ->where('id', $mediaId)
                ->firstOrFail();

            foreach ($product->getMedia('product_images') as $media) {
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
                action: 'catalog.product.media.primary_set',
                subject: $product,
                metadata: [
                    'media_id' => $mediaId,
                    'file_name' => $targetMedia->file_name,
                ],
            );

            return $targetMedia;
        });
    }
}
