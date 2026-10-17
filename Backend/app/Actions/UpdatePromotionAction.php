<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\PromotionData;
use App\Models\Promotion;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class UpdatePromotionAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Promotion $promotion, PromotionData $data, ?int $userId = null): Promotion
    {
        return DB::transaction(function () use ($promotion, $data, $userId): Promotion {
            /** @var Promotion $locked */
            $locked = Promotion::query()->lockForUpdate()->findOrFail($promotion->id);

            $oldData = $locked->toArray();

            $targetsBefore = $locked->targets()->get(['target_type', 'target_id'])->map(fn ($t) => [
                'target_type' => (string) $t->target_type,
                'target_id' => (int) $t->target_id,
            ])->values()->all();

            $locked->update([
                'code' => $data->code,
                'name' => $data->name,
                'description' => $data->description,
                'type' => $data->type,
                'value' => $data->value,
                'scope' => $data->scope,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'is_active' => $data->isActive,
                'priority' => $data->priority,
                'stacking_policy' => $data->stackingPolicy,
                'updated_by' => $userId ?? auth()->id(),
            ]);

            // Sync targets
            $locked->targets()->delete();
            foreach ($data->targets as $target) {
                $locked->targets()->create([
                    'target_type' => $target['target_type'],
                    'target_id' => $target['target_id'],
                ]);
            }

            $targetsAfter = $locked->targets()->get(['target_type', 'target_id'])->map(fn ($t) => [
                'target_type' => (string) $t->target_type,
                'target_id' => (int) $t->target_id,
            ])->values()->all();

            $this->auditLogger->record('admin.promotion.updated', $locked, oldValues: $oldData, metadata: [
                'actor_id' => $userId ?? auth()->id(),
                'code' => $locked->code,
                'old_is_active' => (bool) ($oldData['is_active'] ?? false),
                'new_is_active' => (bool) $locked->is_active,
                'pricing_type' => $locked->type->value,
                'type' => $locked->type->value,
                'value' => (float) $locked->value,
                'base_currency_value' => (float) $locked->value,
                'scope' => $locked->scope->value,
                'targets_before' => $targetsBefore,
                'targets_after' => $targetsAfter,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $locked->load(['targets', 'updater']);
        });
    }
}
