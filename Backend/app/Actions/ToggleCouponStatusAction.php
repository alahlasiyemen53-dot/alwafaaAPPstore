<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Coupon;
use App\Services\AuditLogger;

final class ToggleCouponStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Coupon $coupon, ?int $userId = null): Coupon
    {
        $oldStatus = $coupon->is_active;
        $coupon->update([
            'is_active' => ! $oldStatus,
            'updated_by' => $userId ?? auth()->id(),
        ]);

        $this->auditLogger->record('admin.coupon.toggled', $coupon, metadata: [
            'actor_id' => $userId ?? auth()->id(),
            'code' => $coupon->code,
            'old_is_active' => (bool) $oldStatus,
            'new_is_active' => (bool) $coupon->is_active,
            'is_active' => (bool) $coupon->is_active,
            'pricing_type' => $coupon->discount_type->value,
            'base_currency_value' => (float) $coupon->discount_value,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $coupon;
    }
}
