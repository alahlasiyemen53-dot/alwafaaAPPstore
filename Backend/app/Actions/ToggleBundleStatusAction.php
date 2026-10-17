<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Bundle;
use App\Services\AuditLogger;

final class ToggleBundleStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Bundle $bundle, ?int $userId = null): Bundle
    {
        $oldStatus = $bundle->is_active;
        $bundle->update([
            'is_active' => ! $oldStatus,
            'updated_by' => $userId ?? auth()->id(),
        ]);

        $this->auditLogger->record('admin.bundle.toggled', $bundle, metadata: [
            'actor_id' => $userId ?? auth()->id(),
            'code' => $bundle->code,
            'old_is_active' => (bool) $oldStatus,
            'new_is_active' => (bool) $bundle->is_active,
            'is_active' => (bool) $bundle->is_active,
            'pricing_type' => $bundle->pricing_type->value,
            'base_currency_value' => (float) $bundle->value,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $bundle;
    }
}
