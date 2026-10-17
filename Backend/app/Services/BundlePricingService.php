<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BundlePricingType;
use App\Models\Bundle;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class BundlePricingService
{
    public function __construct(
        private readonly DiscountAllocationService $discountAllocationService,
    ) {}

    /** @var Collection<int, Bundle>|null */
    private ?Collection $cachedActiveBundles = null;

    public function clearCache(): void
    {
        $this->cachedActiveBundles = null;
    }

    /**
     * @return Collection<int, Bundle>
     */
    public function getActiveBundles(?Carbon $now = null): Collection
    {
        if ($this->cachedActiveBundles !== null && $now === null) {
            return $this->cachedActiveBundles;
        }

        $evaluationTime = $now ?? now();

        $bundles = Bundle::query()
            ->where('is_active', true)
            ->where(function ($q) use ($evaluationTime): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $evaluationTime);
            })
            ->where(function ($q) use ($evaluationTime): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $evaluationTime);
            })
            ->with(['items.productVariant'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($now === null) {
            $this->cachedActiveBundles = $bundles;
        }

        return $bundles;
    }

    /**
     * Detect matching bundles across items and calculate bundle discounts allocated to items.
     *
     * @param array<int|string, array{
     *     product_variant_id: int,
     *     unit_price: float|string,
     *     quantity: float|string,
     *     line_subtotal: float|string
     * }> $items
     * @param  callable(string|float): string|null  $currencyConverter
     * @return array{
     *     bundle_discounts_by_item: array<int|string, float>,
     *     bundle_info_by_item: array<int|string, array{
     *         bundle_id: int,
     *         bundle_name: string,
     *         bundle_code_snapshot: string|null,
     *         bundle_pricing_type_snapshot: string,
     *         bundle_value_base_snapshot: float
     *     }>,
     *     bundle_participating_qty_by_item: array<int|string, string>,
     *     total_bundle_discount: float
     * }
     */
    public function evaluateBundles(array $items, ?Carbon $now = null, ?callable $currencyConverter = null): array
    {
        $activeBundles = $this->getActiveBundles($now);
        if ($activeBundles->isEmpty() || empty($items)) {
            return [
                'bundle_discounts_by_item' => [],
                'bundle_info_by_item' => [],
                'bundle_participating_qty_by_item' => [],
                'total_bundle_discount' => 0.0,
            ];
        }

        $bundleDiscountsByItem = [];
        $bundleInfoByItem = [];
        $bundleParticipatingQtyByItem = [];
        $totalBundleDiscountStr = '0.0000';

        // Track remaining quantities by item and by variant
        $remainingQtyByItem = [];
        $remainingQtyByVariant = [];
        $itemsByVariant = [];

        foreach ($items as $itemKey => $item) {
            $variantId = (int) $item['product_variant_id'];
            $qty = Decimal::normalize($item['quantity']);
            $remainingQtyByItem[$itemKey] = $qty;
            $remainingQtyByVariant[$variantId] = Decimal::add(
                $remainingQtyByVariant[$variantId] ?? '0.0000',
                $qty
            );
            $itemsByVariant[$variantId][] = $itemKey;
        }

        foreach ($activeBundles as $bundle) {
            $bundleComponents = $bundle->items;
            if ($bundleComponents->isEmpty()) {
                continue;
            }

            // Calculate occurrences: floor(available / required) across all components
            $occurrences = null;
            foreach ($bundleComponents as $component) {
                $varId = (int) $component->product_variant_id;
                $reqQty = (float) $component->quantity;
                if ($reqQty <= 0) {
                    continue;
                }
                $availQty = (float) ($remainingQtyByVariant[$varId] ?? 0.0);
                $occ = (int) floor($availQty / $reqQty);
                $occurrences = $occurrences === null ? $occ : min($occurrences, $occ);
            }

            if ($occurrences === null || $occurrences <= 0) {
                continue;
            }

            $bundleEligibleAmountsByItem = [];
            $originalBundleTotalStr = '0.0000';

            foreach ($bundleComponents as $component) {
                $varId = (int) $component->product_variant_id;
                $neededQty = Decimal::mul(
                    Decimal::normalize($component->quantity),
                    (string) $occurrences
                );

                foreach ($itemsByVariant[$varId] as $itemKey) {
                    if (Decimal::compare($neededQty, '0.0000') <= 0) {
                        break;
                    }

                    $availOnItem = $remainingQtyByItem[$itemKey];
                    if (Decimal::compare($availOnItem, '0.0000') <= 0) {
                        continue;
                    }

                    $takeQty = Decimal::min($availOnItem, $neededQty);
                    $remainingQtyByItem[$itemKey] = Decimal::sub($availOnItem, $takeQty);
                    $remainingQtyByVariant[$varId] = Decimal::sub($remainingQtyByVariant[$varId], $takeQty);
                    $neededQty = Decimal::sub($neededQty, $takeQty);

                    $unitPriceStr = Decimal::normalize($items[$itemKey]['unit_price']);
                    $eligibleAmount = Decimal::mul($takeQty, $unitPriceStr);

                    $bundleEligibleAmountsByItem[$itemKey] = Decimal::add(
                        $bundleEligibleAmountsByItem[$itemKey] ?? '0.0000',
                        $eligibleAmount
                    );
                    $bundleParticipatingQtyByItem[$itemKey] = Decimal::add(
                        $bundleParticipatingQtyByItem[$itemKey] ?? '0.0000',
                        $takeQty
                    );
                    $originalBundleTotalStr = Decimal::add($originalBundleTotalStr, $eligibleAmount);
                }
            }

            // Calculate total bundle discount for these occurrences
            $val = $bundle->value;
            $bundleDiscountStr = match ($bundle->pricing_type) {
                BundlePricingType::FixedBundlePrice => (function () use ($val, $currencyConverter, $originalBundleTotalStr, $occurrences): string {
                    $convertedVal = $currencyConverter ? $currencyConverter($val) : Decimal::normalize($val);
                    $totalBundlePrice = Decimal::mul($convertedVal, (string) $occurrences);

                    return Decimal::max('0.0000', Decimal::sub($originalBundleTotalStr, $totalBundlePrice));
                })(),
                BundlePricingType::PercentageDiscount => Decimal::mul($originalBundleTotalStr, Decimal::div(Decimal::normalize($val), '100')),
                BundlePricingType::FixedAmountDiscount => (function () use ($val, $currencyConverter, $originalBundleTotalStr, $occurrences): string {
                    $convertedVal = $currencyConverter ? $currencyConverter($val) : Decimal::normalize($val);
                    $totalFixedDiscount = Decimal::mul($convertedVal, (string) $occurrences);

                    return Decimal::min($totalFixedDiscount, $originalBundleTotalStr);
                })(),
            };

            if (Decimal::compare($bundleDiscountStr, '0.0000') > 0) {
                $allocated = $this->discountAllocationService->allocate($bundleEligibleAmountsByItem, $bundleDiscountStr);

                foreach ($allocated as $itemKey => $discountAmount) {
                    $bundleDiscountsByItem[$itemKey] = ($bundleDiscountsByItem[$itemKey] ?? 0.0) + $discountAmount;
                    $bundleInfoByItem[$itemKey] = [
                        'bundle_id' => $bundle->id,
                        'bundle_name' => $bundle->name,
                        'bundle_code_snapshot' => $bundle->code,
                        'bundle_pricing_type_snapshot' => $bundle->pricing_type->value,
                        'bundle_value_base_snapshot' => (float) $bundle->value,
                    ];
                }

                $totalBundleDiscountStr = Decimal::add($totalBundleDiscountStr, $bundleDiscountStr);
            }
        }

        return [
            'bundle_discounts_by_item' => $bundleDiscountsByItem,
            'bundle_info_by_item' => $bundleInfoByItem,
            'bundle_participating_qty_by_item' => $bundleParticipatingQtyByItem,
            'total_bundle_discount' => (float) $totalBundleDiscountStr,
        ];
    }
}
