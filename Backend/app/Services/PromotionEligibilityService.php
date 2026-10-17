<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class PromotionEligibilityService
{
    /** @var Collection<int, Promotion>|null */
    private ?Collection $cachedActivePromotions = null;

    private ?int $cachedTimestamp = null;

    /**
     * Clear promotion cache (useful in tests or long-running processes).
     */
    public function clearCache(): void
    {
        $this->cachedActivePromotions = null;
        $this->cachedTimestamp = null;
    }

    /**
     * @return Collection<int, Promotion>
     */
    public function getActivePromotions(?Carbon $now = null): Collection
    {
        $evaluationTime = $now ?? now();
        $timestamp = $evaluationTime->timestamp;

        if ($this->cachedActivePromotions !== null && $this->cachedTimestamp === $timestamp) {
            return $this->cachedActivePromotions;
        }

        $query = Promotion::query()
            ->where('is_active', true)
            ->where(function ($q) use ($evaluationTime): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $evaluationTime);
            })
            ->where(function ($q) use ($evaluationTime): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $evaluationTime);
            })
            ->with(['targets']);

        $promotions = $query->orderByDesc('priority')->orderBy('id')->get();

        $this->cachedActivePromotions = $promotions;
        $this->cachedTimestamp = $timestamp;

        return $promotions;
    }

    /**
     * Find best eligible automatic promotion for a given variant and quantity at unitPrice.
     *
     * @param  callable(string|float): string|null  $currencyConverter
     * @return array{
     *     promotion: Promotion|null,
     *     discount_amount: float,
     *     discount_per_unit: float,
     *     promotion_name_snapshot: string|null,
     *     promotion_type_snapshot: string|null,
     *     promotion_value_base_snapshot: float|null
     * }
     */
    public function findBestPromotion(
        ProductVariant $variant,
        string|float $unitPrice,
        string|float $quantity,
        ?Carbon $now = null,
        ?callable $currencyConverter = null
    ): array {
        $unitPriceStr = Decimal::normalize($unitPrice);
        $quantityStr = Decimal::normalize($quantity);
        $lineSubtotalStr = Decimal::mul($unitPriceStr, $quantityStr);

        if (Decimal::compare($lineSubtotalStr, '0.0000') <= 0) {
            return [
                'promotion' => null,
                'discount_amount' => 0.0,
                'discount_per_unit' => 0.0,
                'promotion_name_snapshot' => null,
                'promotion_type_snapshot' => null,
                'promotion_value_base_snapshot' => null,
            ];
        }

        $activePromotions = $this->getActivePromotions($now);
        $bestPromotion = null;
        $bestDiscountStr = '0.0000';

        foreach ($activePromotions as $promotion) {
            if (! $this->matchesScope($promotion, $variant)) {
                continue;
            }

            $discountStr = $this->calculateDiscount($promotion, $unitPriceStr, $quantityStr, $lineSubtotalStr, $currencyConverter);

            if (Decimal::compare($discountStr, $bestDiscountStr) > 0) {
                $bestDiscountStr = $discountStr;
                $bestPromotion = $promotion;
            } elseif (Decimal::compare($discountStr, '0.0000') > 0 && Decimal::compare($discountStr, $bestDiscountStr) === 0 && $bestPromotion !== null) {
                // Priority tie-breaker
                if ($promotion->priority > $bestPromotion->priority) {
                    $bestPromotion = $promotion;
                }
            }
        }

        if ($bestPromotion !== null && Decimal::compare($bestDiscountStr, '0.0000') > 0) {
            // Cap discount at line subtotal
            $finalDiscountStr = Decimal::min($bestDiscountStr, $lineSubtotalStr);
            $perUnitStr = Decimal::compare($quantityStr, '0.0000') > 0
                ? Decimal::div($finalDiscountStr, $quantityStr)
                : '0.0000';

            return [
                'promotion' => $bestPromotion,
                'discount_amount' => (float) $finalDiscountStr,
                'discount_per_unit' => (float) $perUnitStr,
                'promotion_name_snapshot' => $bestPromotion->name,
                'promotion_type_snapshot' => $bestPromotion->type->value,
                'promotion_value_base_snapshot' => (float) $bestPromotion->value,
            ];
        }

        return [
            'promotion' => null,
            'discount_amount' => 0.0,
            'discount_per_unit' => 0.0,
            'promotion_name_snapshot' => null,
            'promotion_type_snapshot' => null,
            'promotion_value_base_snapshot' => null,
        ];
    }

    private function matchesScope(Promotion $promotion, ProductVariant $variant): bool
    {
        if ($promotion->scope === PromotionScope::All) {
            return true;
        }

        $targets = $promotion->targets;

        return match ($promotion->scope) {
            PromotionScope::Variant => $targets->contains(
                fn ($t) => $t->target_type === 'variant' && (int) $t->target_id === (int) $variant->id
            ),
            PromotionScope::Product => $targets->contains(
                fn ($t) => $t->target_type === 'product' && (int) $t->target_id === (int) $variant->product_id
            ),
            PromotionScope::Category => $targets->contains(
                fn ($t) => $t->target_type === 'category' && (int) $t->target_id === (int) $variant->product->category_id
            ),
            PromotionScope::Brand => $variant->product->brand_id !== null && $targets->contains(
                fn ($t) => $t->target_type === 'brand' && (int) $t->target_id === (int) $variant->product->brand_id
            ),
        };
    }

    /**
     * @param  callable(string|float): string|null  $currencyConverter
     */
    private function calculateDiscount(
        Promotion $promotion,
        string $unitPriceStr,
        string $quantityStr,
        string $lineSubtotalStr,
        ?callable $currencyConverter = null
    ): string {
        $val = $promotion->value;

        return match ($promotion->type) {
            PromotionType::Percentage => Decimal::mul($lineSubtotalStr, Decimal::div(Decimal::normalize($val), '100')),
            PromotionType::FixedAmount => (function () use ($val, $currencyConverter, $quantityStr, $lineSubtotalStr): string {
                $convertedVal = $currencyConverter ? $currencyConverter($val) : Decimal::normalize($val);

                return Decimal::min(Decimal::mul($convertedVal, $quantityStr), $lineSubtotalStr);
            })(),
            PromotionType::FixedPrice => (function () use ($val, $currencyConverter, $unitPriceStr, $quantityStr): string {
                $convertedVal = $currencyConverter ? $currencyConverter($val) : Decimal::normalize($val);
                $diff = Decimal::sub($unitPriceStr, $convertedVal);

                return Decimal::max('0.0000', Decimal::mul($diff, $quantityStr));
            })(),
        };
    }
}
