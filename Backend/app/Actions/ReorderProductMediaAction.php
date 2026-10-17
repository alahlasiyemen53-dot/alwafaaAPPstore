<?php

namespace App\Actions;

use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ReorderProductMediaAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<int>  $mediaIds
     */
    public function execute(Product $product, array $mediaIds): void
    {
        DB::transaction(function () use ($product, $mediaIds) {
            $existingIds = $product->getMedia('product_images')->pluck('id')->all();

            foreach ($mediaIds as $index => $id) {
                if (in_array($id, $existingIds, true)) {
                    Media::query()
                        ->where('id', $id)
                        ->where('model_type', $product->getMorphClass())
                        ->where('model_id', $product->getKey())
                        ->update(['order_column' => $index + 1]);
                }
            }

            $this->auditLogger->record(
                action: 'catalog.product.media.reordered',
                subject: $product,
                metadata: [
                    'ordered_ids' => $mediaIds,
                ],
            );
        });
    }
}
