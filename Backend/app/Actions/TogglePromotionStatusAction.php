<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Promotion;
use App\Services\AuditLogger;

final class TogglePromotionStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Promotion $promotion, ?int $userId = null): Promotion
    {
        $oldStatus = $promotion->is_active;
        $promotion->update([
            'is_active' => ! $oldStatus,
            'updated_by' => $userId ?? auth()->id(),
        ]);

        $this->auditLogger->record('admin.promotion.toggled', $promotion, metadata: [
            'actor_id' => $userId ?? auth()->id(),
            'code' => $promotion->code,
            'old_is_active' => (bool) $oldStatus,
            'new_is_active' => (bool) $promotion->is_active,
            'is_active' => (bool) $promotion->is_active,
            'pricing_type' => $promotion->type->value,
            'base_currency_value' => (float) $promotion->value,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $promotion;
    }
}
