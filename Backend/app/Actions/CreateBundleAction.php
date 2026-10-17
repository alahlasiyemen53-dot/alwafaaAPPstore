<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\BundleData;
use App\Models\Bundle;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CreateBundleAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(BundleData $data, ?int $userId = null): Bundle
    {
        return DB::transaction(function () use ($data, $userId): Bundle {
            /** @var Bundle $bundle */
            $bundle = Bundle::query()->create([
                'code' => $data->code,
                'name' => $data->name,
                'description' => $data->description,
                'pricing_type' => $data->pricingType,
                'value' => $data->value,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'is_active' => $data->isActive,
                'priority' => $data->priority,
                'created_by' => $userId ?? auth()->id(),
                'updated_by' => $userId ?? auth()->id(),
            ]);

            foreach ($data->items as $item) {
                $bundle->items()->create([
                    'product_variant_id' => $item->productVariantId,
                    'quantity' => $item->quantity,
                ]);
            }

            $componentsAfter = $bundle->items()->get(['product_variant_id', 'quantity'])->map(fn ($i) => [
                'product_variant_id' => (int) $i->product_variant_id,
                'quantity' => (float) $i->quantity,
            ])->values()->all();

            $this->auditLogger->record('admin.bundle.created', $bundle, metadata: [
                'actor_id' => $userId ?? auth()->id(),
                'code' => $bundle->code,
                'old_is_active' => null,
                'new_is_active' => (bool) $bundle->is_active,
                'pricing_type' => $bundle->pricing_type->value,
                'value' => (float) $bundle->value,
                'base_currency_value' => (float) $bundle->value,
                'components_before' => [],
                'components_after' => $componentsAfter,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $bundle->load(['items.productVariant']);
        });
    }
}
