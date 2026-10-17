<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CouponUsageStatus;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Services\AuditLogger;

final class ReleaseCouponUsageAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Release any active coupon usage for a cancelled order.
     */
    public function execute(Order $order, string $reason = 'إلغاء الطلب'): void
    {
        $activeUsages = CouponUsage::query()
            ->where('order_id', $order->id)
            ->where('status', CouponUsageStatus::Active->value)
            ->lockForUpdate()
            ->get();

        foreach ($activeUsages as $usage) {
            $usage->update([
                'status' => CouponUsageStatus::Released,
                'released_at' => now(),
                'release_reason' => $reason,
            ]);

            $this->auditLogger->record('admin.coupon.usage_released', $usage, metadata: [
                'order_number' => $order->order_number,
                'coupon_id' => $usage->coupon_id,
                'release_reason' => $reason,
            ]);
        }
    }
}
