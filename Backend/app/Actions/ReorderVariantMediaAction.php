<?php

namespace App\Actions;

use App\Models\ProductVariant;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ReorderVariantMediaAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<int>  $mediaIds
     */
    public function execute(ProductVariant $variant, array $mediaIds): void
    {
        DB::transaction(function () use ($variant, $mediaIds) {
            $existingIds = $variant->getMedia('variant_images')->pluck('id')->all();

            foreach ($mediaIds as $index => $id) {
                if (in_array($id, $existingIds, true)) {
                    Media::query()
                        ->where('id', $id)
                        ->where('model_type', $variant->getMorphClass())
                        ->where('model_id', $variant->getKey())
                        ->update(['order_column' => $index + 1]);
                }
            }

            $this->auditLogger->record(
                action: 'catalog.variant.media.reordered',
                subject: $variant,
                metadata: [
                    'ordered_ids' => $mediaIds,
                    'product_id' => $variant->product_id,
                ],
            );
        });
    }
}
