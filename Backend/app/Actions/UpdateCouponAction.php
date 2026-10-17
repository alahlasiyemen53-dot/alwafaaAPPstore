<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CouponData;
use App\Models\Coupon;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class UpdateCouponAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Coupon $coupon, CouponData $data, ?int $userId = null): Coupon
    {
        return DB::transaction(function () use ($coupon, $data, $userId): Coupon {
            /** @var Coupon $locked */
            $locked = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);

            $oldData = $locked->toArray();

            $targetsBefore = $locked->targets()->get(['target_type', 'target_id'])->map(fn ($t) => [
                'target_type' => (string) $t->target_type,
                'target_id' => (int) $t->target_id,
            ])->values()->all();

            $locked->update([
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

            $this->auditLogger->record('admin.coupon.updated', $locked, oldValues: $oldData, metadata: [
                'actor_id' => $userId ?? auth()->id(),
                'code' => $locked->code,
                'old_is_active' => (bool) ($oldData['is_active'] ?? false),
                'new_is_active' => (bool) $locked->is_active,
                'pricing_type' => $locked->discount_type->value,
                'discount_type' => $locked->discount_type->value,
                'value' => (float) $locked->discount_value,
                'base_currency_value' => (float) $locked->discount_value,
                'discount_value' => (float) $locked->discount_value,
                'scope' => $locked->scope,
                'targets_before' => $targetsBefore,
                'targets_after' => $targetsAfter,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $locked->load('targets');
        });
    }
}
