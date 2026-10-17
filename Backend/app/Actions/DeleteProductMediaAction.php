<?php

namespace App\Actions;

use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class DeleteProductMediaAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Product $product, int $mediaId): bool
    {
        return DB::transaction(function () use ($product, $mediaId) {
            /** @var Media $media */
            $media = $product->media()
                ->where('collection_name', 'product_images')
                ->where('id', $mediaId)
                ->firstOrFail();

            $wasPrimary = (bool) $media->getCustomProperty('is_primary', false);
            $fileName = $media->file_name;

            $media->delete();

            if ($wasPrimary) {
                // Promote first remaining image to primary
                /** @var Media|null $firstRemaining */
                $firstRemaining = $product->getMedia('product_images')->sortBy('order_column')->first();
                if ($firstRemaining) {
                    $firstRemaining->setCustomProperty('is_primary', true);
                    $firstRemaining->save();
                }
            }

            $this->auditLogger->record(
                action: 'catalog.product.media.deleted',
                subject: $product,
                metadata: [
                    'media_id' => $mediaId,
                    'file_name' => $fileName,
                    'was_primary' => $wasPrimary,
                ],
            );

            return true;
        });
    }
}
