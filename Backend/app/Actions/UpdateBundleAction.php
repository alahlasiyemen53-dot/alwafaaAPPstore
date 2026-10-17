<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\BundleData;
use App\Models\Bundle;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class UpdateBundleAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Bundle $bundle, BundleData $data, ?int $userId = null): Bundle
    {
        return DB::transaction(function () use ($bundle, $data, $userId): Bundle {
            /** @var Bundle $locked */
            $locked = Bundle::query()->lockForUpdate()->findOrFail($bundle->id);

            $oldData = $locked->toArray();

            $componentsBefore = $locked->items()->get(['product_variant_id', 'quantity'])->map(fn ($i) => [
                'product_variant_id' => (int) $i->product_variant_id,
                'quantity' => (float) $i->quantity,
            ])->values()->all();

            $locked->update([
                'code' => $data->code,
                'name' => $data->name,
                'description' => $data->description,
                'pricing_type' => $data->pricingType,
                'value' => $data->value,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'is_active' => $data->isActive,
                'priority' => $data->priority,
                'updated_by' => $userId ?? auth()->id(),
            ]);

            // Sync items
            $locked->items()->delete();
            foreach ($data->items as $item) {
                $locked->items()->create([
                    'product_variant_id' => $item->productVariantId,
                    'quantity' => $item->quantity,
                ]);
            }

            $componentsAfter = $locked->items()->get(['product_variant_id', 'quantity'])->map(fn ($i) => [
                'product_variant_id' => (int) $i->product_variant_id,
                'quantity' => (float) $i->quantity,
            ])->values()->all();

            $this->auditLogger->record('admin.bundle.updated', $locked, oldValues: $oldData, metadata: [
                'actor_id' => $userId ?? auth()->id(),
                'code' => $locked->code,
                'old_is_active' => (bool) ($oldData['is_active'] ?? false),
                'new_is_active' => (bool) $locked->is_active,
                'pricing_type' => $locked->pricing_type->value,
                'value' => (float) $locked->value,
                'base_currency_value' => (float) $locked->value,
                'components_before' => $componentsBefore,
                'components_after' => $componentsAfter,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $locked->load(['items.productVariant']);
        });
    }
}
