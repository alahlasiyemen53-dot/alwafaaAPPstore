<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CouponData;
use App\Models\Coupon;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CreateCouponAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CouponData $data, ?int $userId = null): Coupon
    {
        return DB::transaction(function () use ($data, $userId): Coupon {
            /** @var Coupon $coupon */
            $coupon = Coupon::query()->create([
                'code' => $data->code,
                'name' => $data->name,
                'discount_type' => $data->discountType,
                'discount_value' => $data->discountValue,
                'scope' => $data->scope,
                'minimum_order_amount' => $data->minimumOrderAmount,
                'maximum_discount_amount' => $data->maximumDiscountAmount,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'is_active' => $data->isActive,
                'usage_limit' => $data->usageLimit,
                'per_customer_limit' => $data->perCustomerLimit,
                'is_compatible_with_promotions' => $data->isCompatibleWithPromotions,
                'created_by' => $userId ?? auth()->id(),
                'updated_by' => $userId ?? auth()->id(),
            ]);

            foreach ($data->targets as $target) {
                $coupon->targets()->create([
                    'target_type' => $target['target_type'],
                    'target_id' => $target['target_id'],
                ]);
            }

            $targetsAfter = $coupon->targets()->get(['target_type', 'target_id'])->map(fn ($t) => [
                'target_type' => (string) $t->target_type,
                'target_id' => (int) $t->target_id,
            ])->values()->all();

            $this->auditLogger->record('admin.coupon.created', $coupon, metadata: [
                'actor_id' => $userId ?? auth()->id(),
                'code' => $coupon->code,
                'old_is_active' => null,
                'new_is_active' => (bool) $coupon->is_active,
                'pricing_type' => $coupon->discount_type->value,
                'discount_type' => $coupon->discount_type->value,
                'value' => (float) $coupon->discount_value,
                'base_currency_value' => (float) $coupon->discount_value,
                'discount_value' => (float) $coupon->discount_value,
                'scope' => $coupon->scope,
                'targets_before' => [],
                'targets_after' => $targetsAfter,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $coupon->load('targets');
        });
    }
}
