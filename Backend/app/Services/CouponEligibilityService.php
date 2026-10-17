<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CouponDiscountType;
use App\Enums\CouponUsageStatus;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Support\Decimal;
use Carbon\Carbon;

final class CouponEligibilityService
{
    /**
     * @param  array<int, array<string, mixed>>|string|float  $linesOrSubtotal
     * @param  callable(string|float): string|null  $currencyConverter
     * @return array{
     *     is_valid: bool,
     *     coupon: Coupon|null,
     *     discount_amount: float,
     *     error_message: string|null,
     *     eligible_line_indices?: array<int>
     * }
     */
    public function validate(
        string $code,
        array|string|float $linesOrSubtotal,
        ?int $customerId = null,
        bool $hasActivePromotions = false,
        ?Carbon $now = null,
        bool $lockForUpdate = false,
        ?callable $currencyConverter = null
    ): array {
        $canonicalCode = Coupon::normalizeCode($code);
        if ($canonicalCode === '') {
            return [
                'is_valid' => false,
                'coupon' => null,
                'discount_amount' => 0.0,
                'error_message' => 'يرجى إدخال كود الكوبون.',
            ];
        }

        $query = Coupon::query()->where('code', $canonicalCode)->with('targets');
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Coupon|null $coupon */
        $coupon = $query->first();

        if (! $coupon) {
            return [
                'is_valid' => false,
                'coupon' => null,
                'discount_amount' => 0.0,
                'error_message' => 'كود الكوبون غير صحيح.',
            ];
        }

        $evaluationTime = $now ?? now();

        if (! $coupon->is_active) {
            return [
                'is_valid' => false,
                'coupon' => $coupon,
                'discount_amount' => 0.0,
                'error_message' => 'هذا الكوبون غير مفعّل حالياً.',
            ];
        }

        if ($coupon->starts_at && $evaluationTime->isBefore($coupon->starts_at)) {
            return [
                'is_valid' => false,
                'coupon' => $coupon,
                'discount_amount' => 0.0,
                'error_message' => 'هذا الكوبون لم يبدأ بعد.',
            ];
        }

        if ($coupon->ends_at && $evaluationTime->isAfter($coupon->ends_at)) {
            return [
                'is_valid' => false,
                'coupon' => $coupon,
                'discount_amount' => 0.0,
                'error_message' => 'عذراً، انتهت صلاحية هذا الكوبون.',
            ];
        }

        $eligibleLineIndices = [];
        $eligibleSubtotalStr = '0.0000';

        if (is_array($linesOrSubtotal)) {
            // Derive promotion presence and matching lines from lines context
            $orderHasActivePromos = false;
            $targets = $coupon->targets;
            $targetIds = $targets->pluck('target_id')->all();
            $scope = $coupon->scope ?? 'all';

            foreach ($linesOrSubtotal as $idx => $line) {
                $itemDiscountStr = Decimal::normalize($line['item_discount_amount'] ?? 0.0);
                if (Decimal::compare($itemDiscountStr, '0.0000') > 0) {
                    $orderHasActivePromos = true;
                }

                $variant = $line['variant'] ?? null;
                $isMatching = match ($scope) {
                    'all' => true,
                    'variant' => $variant !== null && in_array($variant->id, $targetIds, true),
                    'product' => $variant !== null && in_array($variant->product_id, $targetIds, true),
                    'category' => $variant !== null && ($variant->product ?? null) !== null && in_array($variant->product->category_id, $targetIds, true),
                    'brand' => $variant !== null && ($variant->product ?? null) !== null && in_array($variant->product->brand_id, $targetIds, true),
                    default => true,
                };

                if ($isMatching) {
                    $eligibleLineIndices[] = $idx;
                    $linePostDiscount = Decimal::normalize($line['line_subtotal_after_item'] ?? $line['line_subtotal'] ?? 0.0);
                    $eligibleSubtotalStr = Decimal::add($eligibleSubtotalStr, $linePostDiscount);
                }
            }

            if (empty($eligibleLineIndices) || Decimal::compare($eligibleSubtotalStr, '0.0000') <= 0) {
                return [
                    'is_valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0.0,
                    'error_message' => 'لا توجد منتجات مؤهلة للاستفادة من هذا الكوبون في الطلب.',
                ];
            }

            if ($orderHasActivePromos && ! $coupon->is_compatible_with_promotions) {
                return [
                    'is_valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0.0,
                    'error_message' => 'هذا الكوبون غير متوافق مع العروض الترويجية الحالية في السلة.',
                ];
            }
        } else {
            // Scalar subtotal provided
            if ($hasActivePromotions && ! $coupon->is_compatible_with_promotions) {
                return [
                    'is_valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0.0,
                    'error_message' => 'هذا الكوبون غير متوافق مع العروض الترويجية الحالية في السلة.',
                ];
            }

            $eligibleSubtotalStr = Decimal::normalize($linesOrSubtotal);
        }

        if ($coupon->minimum_order_amount !== null) {
            $minOrderStr = $currencyConverter
                ? $currencyConverter($coupon->minimum_order_amount)
                : Decimal::normalize($coupon->minimum_order_amount);

            if (Decimal::compare($eligibleSubtotalStr, $minOrderStr) < 0) {
                return [
                    'is_valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0.0,
                    'error_message' => sprintf('الحد الأدنى للطلب للاستفادة من هذا الكوبون هو %s.', number_format((float) $minOrderStr, 2)),
                ];
            }
        }

        // Global usage limit check
        if ($coupon->usage_limit !== null) {
            $usagesCountQuery = CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('status', CouponUsageStatus::Active->value);

            if ($lockForUpdate) {
                $usagesCountQuery->lockForUpdate();
            }

            $currentUsages = $usagesCountQuery->count();
            if ($currentUsages >= $coupon->usage_limit) {
                return [
                    'is_valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0.0,
                    'error_message' => 'تم استنفاد الحد الأقصى لاستخدام هذا الكوبون.',
                ];
            }
        }

        // Per-customer limit check
        if ($customerId !== null && $coupon->per_customer_limit !== null) {
            $customerUsagesQuery = CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('customer_id', $customerId)
                ->where('status', CouponUsageStatus::Active->value);

            if ($lockForUpdate) {
                $customerUsagesQuery->lockForUpdate();
            }

            $customerUsages = $customerUsagesQuery->count();
            if ($customerUsages >= $coupon->per_customer_limit) {
                return [
                    'is_valid' => false,
                    'coupon' => $coupon,
                    'discount_amount' => 0.0,
                    'error_message' => 'لقد تجاوزت الحد المسموح به لاستخدام هذا الكوبون.',
                ];
            }
        }

        // Calculate discount amount
        $val = $coupon->discount_value;
        $discountStr = match ($coupon->discount_type) {
            CouponDiscountType::Percentage => Decimal::mul($eligibleSubtotalStr, Decimal::div(Decimal::normalize($val), '100')),
            CouponDiscountType::FixedAmount => (function () use ($val, $currencyConverter, $eligibleSubtotalStr): string {
                $convertedVal = $currencyConverter ? $currencyConverter($val) : Decimal::normalize($val);

                return Decimal::min($convertedVal, $eligibleSubtotalStr);
            })(),
        };

        if ($coupon->maximum_discount_amount !== null) {
            $maxDiscountStr = $currencyConverter
                ? $currencyConverter($coupon->maximum_discount_amount)
                : Decimal::normalize($coupon->maximum_discount_amount);

            if (Decimal::compare($discountStr, $maxDiscountStr) > 0) {
                $discountStr = $maxDiscountStr;
            }
        }

        $discountStr = Decimal::min($discountStr, $eligibleSubtotalStr);

        return [
            'is_valid' => true,
            'coupon' => $coupon,
            'discount_amount' => (float) $discountStr,
            'error_message' => null,
            'eligible_line_indices' => $eligibleLineIndices,
        ];
    }
}
