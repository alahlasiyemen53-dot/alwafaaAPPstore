<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\PromotionData;
use App\Models\Promotion;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CreatePromotionAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PromotionData $data, ?int $userId = null): Promotion
    {
        return DB::transaction(function () use ($data, $userId): Promotion {
            /** @var Promotion $promotion */
            $promotion = Promotion::query()->create([
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
                'created_by' => $userId ?? auth()->id(),
                'updated_by' => $userId ?? auth()->id(),
            ]);

            foreach ($data->targets as $target) {
                $promotion->targets()->create([
                    'target_type' => $target['target_type'],
                    'target_id' => $target['target_id'],
                ]);
            }

            $targetsAfter = $promotion->targets()->get(['target_type', 'target_id'])->map(fn ($t) => [
                'target_type' => (string) $t->target_type,
                'target_id' => (int) $t->target_id,
            ])->values()->all();

            $this->auditLogger->record('admin.promotion.created', $promotion, metadata: [
                'actor_id' => $userId ?? auth()->id(),
                'code' => $promotion->code,
                'old_is_active' => null,
                'new_is_active' => (bool) $promotion->is_active,
                'pricing_type' => $promotion->type->value,
                'type' => $promotion->type->value,
                'value' => (float) $promotion->value,
                'base_currency_value' => (float) $promotion->value,
                'scope' => $promotion->scope->value,
                'targets_before' => [],
                'targets_after' => $targetsAfter,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $promotion->load(['targets', 'creator']);
        });
    }
}
