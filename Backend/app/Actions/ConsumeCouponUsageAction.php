<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CouponUsageStatus;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Services\AuditLogger;
use DomainException;

final class ConsumeCouponUsageAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Atomically consume coupon usage for a confirmed order.
     * Must be called within an active DB transaction with appropriate locks.
     */
    public function execute(Order $order, Coupon $coupon, float $discountAmount): CouponUsage
    {
        /** @var Coupon $lockedCoupon */
        $lockedCoupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);

        if (! $lockedCoupon->is_active) {
            throw new DomainException('الكوبون غير مفعّل حالياً.');
        }

        // Concurrency check: Global usage limit
        if ($lockedCoupon->usage_limit !== null) {
            $currentGlobalUsages = CouponUsage::query()
                ->where('coupon_id', $lockedCoupon->id)
                ->where('status', CouponUsageStatus::Active->value)
                ->lockForUpdate()
                ->count();

            if ($currentGlobalUsages >= $lockedCoupon->usage_limit) {
                throw new DomainException('تم استنفاد الحد الأقصى المتاح لاستخدام هذا الكوبون.');
            }
        }

        // Concurrency check: Per-customer limit
        if ($lockedCoupon->per_customer_limit !== null) {
            $currentCustomerUsages = CouponUsage::query()
                ->where('coupon_id', $lockedCoupon->id)
                ->where('customer_id', $order->customer_id)
                ->where('status', CouponUsageStatus::Active->value)
                ->lockForUpdate()
                ->count();

            if ($currentCustomerUsages >= $lockedCoupon->per_customer_limit) {
                throw new DomainException('تم تجاوز الحد الأقصى المسموح به لاستخدام هذا الكوبون للعميل.');
            }
        }

        // Check if an active usage already exists for this order (idempotency)
        /** @var CouponUsage|null $existingUsage */
        $existingUsage = CouponUsage::query()
            ->where('order_id', $order->id)
            ->where('coupon_id', $lockedCoupon->id)
            ->where('status', CouponUsageStatus::Active->value)
            ->first();

        if ($existingUsage !== null) {
            return $existingUsage;
        }

        /** @var CouponUsage $usage */
        $usage = CouponUsage::query()->create([
            'coupon_id' => $lockedCoupon->id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'status' => CouponUsageStatus::Active,
            'discount_amount_snapshot' => $discountAmount,
            'currency_id' => $order->currency_id,
            'used_at' => now(),
        ]);

        $this->auditLogger->record('admin.coupon.usage_consumed', $usage, metadata: [
            'coupon_code' => $lockedCoupon->code,
            'order_number' => $order->order_number,
            'discount_amount' => $discountAmount,
        ]);

        return $usage;
    }
}
