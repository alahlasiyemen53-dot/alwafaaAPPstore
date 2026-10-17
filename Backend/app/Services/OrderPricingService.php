<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OrderItemData;
use App\DTOs\OrderPricingResultData;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Currency;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Support\Decimal;
use Carbon\Carbon;
use DomainException;

final class OrderPricingService
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly PromotionEligibilityService $promotionEligibilityService,
        private readonly BundlePricingService $bundlePricingService,
        private readonly CouponEligibilityService $couponEligibilityService,
        private readonly DiscountAllocationService $discountAllocationService,
    ) {}

    /**
     * Calculate authoritative order pricing following the canonical backend pricing pipeline.
     *
     * @param  int  $currencyId  Order currency ID
     * @param  array<int, OrderItemData>  $itemsData  List of item inputs
     * @param  string|null  $couponCode  Optional coupon code to apply
     * @param  int|null  $customerId  Customer ID for coupon per-customer validation
     * @param  Carbon|null  $now  Evaluation timestamp (defaults to current time)
     * @param  bool  $lockForUpdate  Whether to lock coupon rows (used during confirmation)
     * @param  float|null  $deliveryFeeBase  Optional fixed delivery fee from governorate in Base Currency
     */
    public function calculate(
        int $currencyId,
        array $itemsData,
        ?string $couponCode = null,
        ?int $customerId = null,
        ?Carbon $now = null,
        bool $lockForUpdate = false,
        ?float $deliveryFeeBase = null,
    ): OrderPricingResultData {
        $orderCurrency = Currency::query()->findOrFail($currencyId);
        if (! $orderCurrency->is_active) {
            throw new DomainException('عملة الطلب المحددة غير نشطة.');
        }

        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $isBase = $orderCurrency->id === $baseCurrency->id;

        $exchangeRateSnapshot = '1.00000000';
        $rateInfo = null;
        if (! $isBase) {
            $rateInfo = $this->currencyConverter->resolveRate($orderCurrency, $baseCurrency);
            if ($rateInfo === null || bccomp($rateInfo['rate'], '0', 8) <= 0) {
                throw new DomainException(sprintf('لا يوجد سعر صرف سارٍ ومحدد لعملة الطلب (%s) مقابل العملة الأساسية (%s).', $orderCurrency->name_ar, $baseCurrency->name_ar));
            }
            $exchangeRateSnapshot = $rateInfo['rate'];
        }

        $baseToOrderConverter = function (string|int|float $baseAmount) use ($isBase, $exchangeRateSnapshot, $rateInfo): string {
            if ($isBase) {
                return Decimal::normalize($baseAmount);
            }
            // Authoritative: 1 Order = Rate Base -> Base to Order = Base / Rate
            if ($rateInfo['direction'] === 'target_to_base') {
                return Decimal::div($baseAmount, $exchangeRateSnapshot);
            }

            return Decimal::mul($baseAmount, $exchangeRateSnapshot);
        };

        $orderToBaseConverter = function (string|int|float $orderAmount) use ($isBase, $exchangeRateSnapshot, $rateInfo): string {
            if ($isBase) {
                return Decimal::normalize($orderAmount);
            }
            // Authoritative: 1 Order = Rate Base -> Order to Base = Order * Rate
            if ($rateInfo['direction'] === 'target_to_base') {
                return Decimal::mul($orderAmount, $exchangeRateSnapshot);
            }

            return Decimal::div($orderAmount, $exchangeRateSnapshot);
        };

        $evaluationTime = $now ?? now();

        // 1. Resolve Base Variant Prices & Convert to Order Currency
        $rawLines = [];
        $originalSubtotalStr = '0.0000';

        $variantIds = array_unique(array_map(fn ($item) => (int) $item->productVariantId, $itemsData));
        $variantsById = ProductVariant::query()
            ->with(['product', 'prices', 'attributeValues.attribute'])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $unitIds = array_filter(array_unique(array_map(fn ($item) => $item->unitId ? (int) $item->unitId : null, $itemsData)));
        $unitsById = ! empty($unitIds)
            ? Unit::query()->whereIn('id', $unitIds)->get()->keyBy('id')
            : collect();

        foreach ($itemsData as $idx => $item) {
            /** @var ProductVariant|null $variant */
            $variant = $variantsById->get($item->productVariantId);

            if (! $variant) {
                throw new DomainException(sprintf('المتغير رقم (%d) غير موجود.', $item->productVariantId));
            }

            if ($variant->status !== ProductVariantStatus::Active) {
                throw new DomainException(sprintf('المنتج/المتغير (%s) غير نشط ولا يمكن بيعه.', $variant->sku));
            }

            if ($variant->product->status !== ProductStatus::Active) {
                throw new DomainException(sprintf('المنتج الأساسي للمتغير (%s) غير نشط ولا يمكن بيعه.', $variant->sku));
            }

            $basePriceRecord = $variant->prices->firstWhere('currency_id', $baseCurrency->id);
            if ($basePriceRecord === null) {
                throw new DomainException(sprintf(
                    'لا يوجد سعر بالعملة الأساسية للمتغير (%s).',
                    $variant->sku,
                ));
            }
            $basePrice = Decimal::normalize($basePriceRecord->price);

            if (Decimal::compare($basePrice, '0.0000') < 0) {
                throw new DomainException(sprintf('سعر البيع للمتغير (%s) غير صالح.', $variant->sku));
            }

            $unitPrice = $isBase ? $basePrice : $baseToOrderConverter($basePrice);
            $qty = Decimal::normalize($item->quantity);
            $lineSubtotal = Decimal::mul($qty, $unitPrice);
            $originalSubtotalStr = Decimal::add($originalSubtotalStr, $lineSubtotal);

            $productName = $variant->product->name_ar;
            $attributePairs = $variant->attributeValues->map(function ($val) {
                $attrName = $val->attribute->name_ar;

                return $attrName ? "{$attrName}: {$val->value_ar}" : $val->value_ar;
            })->filter()->implode(' - ');
            $variantNameSnapshot = $attributePairs !== '' ? $attributePairs : null;

            $unit = $item->unitId ? $unitsById->get($item->unitId) : null;
            $unitNameSnapshot = $unit?->name_ar;

            $rawLines[$idx] = [
                'variant' => $variant,
                'product_variant_id' => $variant->id,
                'product_name_snapshot' => $productName,
                'variant_name_snapshot' => $variantNameSnapshot,
                'sku_snapshot' => $variant->sku,
                'unit_id' => $unit?->id,
                'unit_name_snapshot' => $unitNameSnapshot,
                'quantity' => $qty,
                'base_quantity' => $qty,
                'original_unit_price' => $unitPrice,
                'unit_price' => $unitPrice,
                'base_currency_unit_price' => $basePrice,
                'line_subtotal' => $lineSubtotal,
            ];
        }

        // 2. Evaluate Bundles
        $bundleEvaluation = $this->bundlePricingService->evaluateBundles($rawLines, $evaluationTime, $baseToOrderConverter);
        $bundleDiscountsByItem = $bundleEvaluation['bundle_discounts_by_item'];
        $bundleInfoByItem = $bundleEvaluation['bundle_info_by_item'];
        $bundleParticipatingQtyByItem = $bundleEvaluation['bundle_participating_qty_by_item'];
        $bundleDiscountTotalStr = Decimal::normalize($bundleEvaluation['total_bundle_discount']);

        // 3. Evaluate Item Promotions for non-bundle items or surplus quantities
        $promotionDiscountTotalStr = '0.0000';
        $itemsAfterItemDiscounts = [];

        foreach ($rawLines as $idx => $line) {
            $bundleDiscountStr = isset($bundleDiscountsByItem[$idx]) ? Decimal::normalize($bundleDiscountsByItem[$idx]) : '0.0000';
            $bundleInfo = $bundleInfoByItem[$idx] ?? null;

            $bundleParticipatingQtyStr = isset($bundleParticipatingQtyByItem[$idx])
                ? Decimal::normalize($bundleParticipatingQtyByItem[$idx])
                : '0.0000';

            $surplusQtyStr = Decimal::max('0.0000', Decimal::sub($line['quantity'], $bundleParticipatingQtyStr));

            $promotionDiscountStr = '0.0000';
            $promotionInfo = null;

            if (Decimal::compare($surplusQtyStr, '0.0000') > 0) {
                // Surplus quantity can evaluate automatic promotions
                $promoResult = $this->promotionEligibilityService->findBestPromotion(
                    $line['variant'],
                    $line['unit_price'],
                    $surplusQtyStr,
                    $evaluationTime,
                    $baseToOrderConverter
                );

                if ($promoResult['promotion'] !== null && $promoResult['discount_amount'] > 0.0) {
                    $promotionDiscountStr = Decimal::normalize($promoResult['discount_amount']);
                    $promotionInfo = [
                        'promotion_id' => $promoResult['promotion']->id,
                        'promotion_name' => $promoResult['promotion_name_snapshot'],
                        'promotion_type_snapshot' => $promoResult['promotion_type_snapshot'],
                        'promotion_value_base_snapshot' => $promoResult['promotion_value_base_snapshot'],
                    ];
                    $promotionDiscountTotalStr = Decimal::add($promotionDiscountTotalStr, $promotionDiscountStr);
                }
            }

            $itemDiscountStr = Decimal::add($bundleDiscountStr, $promotionDiscountStr);
            $lineAfterItemStr = Decimal::max('0.0000', Decimal::sub($line['line_subtotal'], $itemDiscountStr));

            $itemsAfterItemDiscounts[$idx] = array_merge($line, [
                'bundle_id' => $bundleInfo['bundle_id'] ?? null,
                'bundle_name_snapshot' => $bundleInfo['bundle_name'] ?? null,
                'bundle_code_snapshot' => $bundleInfo['bundle_code_snapshot'] ?? null,
                'bundle_pricing_type_snapshot' => $bundleInfo['bundle_pricing_type_snapshot'] ?? null,
                'bundle_value_base_snapshot' => $bundleInfo['bundle_value_base_snapshot'] ?? null,
                'promotion_id' => $promotionInfo['promotion_id'] ?? null,
                'promotion_name_snapshot' => $promotionInfo['promotion_name'] ?? null,
                'promotion_type_snapshot' => $promotionInfo['promotion_type_snapshot'] ?? null,
                'promotion_value_base_snapshot' => $promotionInfo['promotion_value_base_snapshot'] ?? null,
                'item_discount_amount' => $itemDiscountStr,
                'line_subtotal_after_item' => $lineAfterItemStr,
            ]);
        }

        $subtotalAfterItemDiscountsStr = '0.0000';
        foreach ($itemsAfterItemDiscounts as $item) {
            $subtotalAfterItemDiscountsStr = Decimal::add($subtotalAfterItemDiscountsStr, $item['line_subtotal_after_item']);
        }

        // 4. Evaluate Coupon
        $couponDiscountTotalStr = '0.0000';
        $appliedCoupon = null;
        $couponAllocations = [];

        if ($couponCode !== null && trim($couponCode) !== '') {
            $couponResult = $this->couponEligibilityService->validate(
                $couponCode,
                $itemsAfterItemDiscounts,
                $customerId,
                false,
                $evaluationTime,
                $lockForUpdate,
                $baseToOrderConverter
            );

            if (! $couponResult['is_valid']) {
                throw new DomainException($couponResult['error_message'] ?? 'الكوبون المحدد غير صالح للاستخدام.');
            }

            $appliedCoupon = $couponResult['coupon'];
            $couponDiscountTotalStr = Decimal::normalize($couponResult['discount_amount']);

            // Proportionally allocate coupon discount across eligible lines based on post-item-discount line amounts
            $eligibleIndices = $couponResult['eligible_line_indices'] ?? array_keys($itemsAfterItemDiscounts);
            $eligibleLinesForCoupon = [];
            foreach ($itemsAfterItemDiscounts as $idx => $item) {
                if (in_array($idx, $eligibleIndices, true)) {
                    $eligibleLinesForCoupon[$idx] = $item['line_subtotal_after_item'];
                }
            }

            $couponAllocations = $this->discountAllocationService->allocate($eligibleLinesForCoupon, $couponDiscountTotalStr);
        }

        // 5. Finalize Line Totals & Snapshots
        $finalCalculatedItems = [];
        foreach ($itemsAfterItemDiscounts as $idx => $item) {
            $allocatedOrderDiscountStr = isset($couponAllocations[$idx]) ? Decimal::normalize($couponAllocations[$idx]) : '0.0000';
            $lineDiscountStr = Decimal::add($item['item_discount_amount'], $allocatedOrderDiscountStr);
            $lineTotalStr = Decimal::max('0.0000', Decimal::sub($item['line_subtotal'], $lineDiscountStr));

            $baseLineSubtotalStr = Decimal::mul($item['base_quantity'], $item['base_currency_unit_price']);
            $baseDiscountStr = Decimal::compare($item['unit_price'], '0.0000') > 0
                ? Decimal::mul(Decimal::div($lineDiscountStr, $item['unit_price']), $item['base_currency_unit_price'])
                : '0.0000';
            $baseLineTotalStr = Decimal::max('0.0000', Decimal::sub($baseLineSubtotalStr, $baseDiscountStr));

            $finalCalculatedItems[] = [
                'product_variant_id' => $item['product_variant_id'],
                'product_name_snapshot' => $item['product_name_snapshot'],
                'variant_name_snapshot' => $item['variant_name_snapshot'],
                'sku_snapshot' => $item['sku_snapshot'],
                'unit_id' => $item['unit_id'],
                'unit_name_snapshot' => $item['unit_name_snapshot'],
                'promotion_id' => $item['promotion_id'],
                'promotion_name_snapshot' => $item['promotion_name_snapshot'],
                'promotion_type_snapshot' => $item['promotion_type_snapshot'] ?? null,
                'promotion_value_base_snapshot' => $item['promotion_value_base_snapshot'] ?? null,
                'bundle_id' => $item['bundle_id'],
                'bundle_name_snapshot' => $item['bundle_name_snapshot'],
                'bundle_code_snapshot' => $item['bundle_code_snapshot'] ?? null,
                'bundle_pricing_type_snapshot' => $item['bundle_pricing_type_snapshot'] ?? null,
                'bundle_value_base_snapshot' => $item['bundle_value_base_snapshot'] ?? null,
                'quantity' => (float) $item['quantity'],
                'base_quantity' => (float) $item['base_quantity'],
                'original_unit_price' => (float) $item['original_unit_price'],
                'unit_price' => (float) $item['unit_price'],
                'line_subtotal' => (float) $item['line_subtotal'],
                'item_discount_amount' => (float) $item['item_discount_amount'],
                'allocated_order_discount' => (float) $allocatedOrderDiscountStr,
                'discount_amount' => (float) $lineDiscountStr,
                'line_total' => (float) $lineTotalStr,
                'base_currency_unit_price' => (float) $item['base_currency_unit_price'],
                'base_currency_line_total' => (float) $baseLineTotalStr,
            ];
        }

        $totalDiscountStr = Decimal::add(Decimal::add($promotionDiscountTotalStr, $bundleDiscountTotalStr), $couponDiscountTotalStr);
        $merchandiseTotalStr = Decimal::max('0.0000', Decimal::sub($originalSubtotalStr, $totalDiscountStr));

        // Delivery Fee calculation (stored in Base Currency, converted to Order Currency if needed)
        $baseDeliveryFeeStr = '0.0000';
        $orderDeliveryFeeStr = '0.0000';
        if ($deliveryFeeBase !== null) {
            if ($deliveryFeeBase < 0) {
                throw new DomainException('رسوم التوصيل لا يمكن أن تكون سالبة.');
            }
            $baseDeliveryFeeStr = Decimal::normalize($deliveryFeeBase);
            $orderDeliveryFeeStr = $isBase ? $baseDeliveryFeeStr : $baseToOrderConverter($baseDeliveryFeeStr);
        }

        $finalTotalStr = Decimal::add($merchandiseTotalStr, $orderDeliveryFeeStr);

        $sumLineTotalsStr = '0.0000';
        $sumLineDiscountsStr = '0.0000';
        foreach ($finalCalculatedItems as $item) {
            $sumLineTotalsStr = Decimal::add($sumLineTotalsStr, (string) $item['line_total']);
            $sumLineDiscountsStr = Decimal::add($sumLineDiscountsStr, (string) $item['discount_amount']);
        }

        if (Decimal::compare($finalTotalStr, '0.0000') < 0) {
            throw new DomainException('إجمالي الطلب غير صالح (أقل من الصفر).');
        }

        if (Decimal::compare($totalDiscountStr, '0.0000') < 0) {
            throw new DomainException('إجمالي الخصم غير صالح (أقل من الصفر).');
        }

        if (Decimal::compare($sumLineTotalsStr, $merchandiseTotalStr) !== 0) {
            throw new DomainException('مجموع سطور الطلب لا يطابق إجمالي المنتجات بعد الخصم.');
        }

        if (Decimal::compare($sumLineDiscountsStr, $totalDiscountStr) !== 0) {
            throw new DomainException('مجموع خصومات السطور لا يطابق إجمالي الخصم.');
        }

        $baseCurrencyTotalStr = $isBase
            ? $finalTotalStr
            : $orderToBaseConverter($finalTotalStr);

        return new OrderPricingResultData(
            originalSubtotal: (float) $originalSubtotalStr,
            promotionDiscountTotal: (float) $promotionDiscountTotalStr,
            bundleDiscountTotal: (float) $bundleDiscountTotalStr,
            couponDiscountTotal: (float) $couponDiscountTotalStr,
            totalDiscount: (float) $totalDiscountStr,
            subtotalAfterItemDiscounts: (float) $subtotalAfterItemDiscountsStr,
            finalTotal: (float) $finalTotalStr,
            exchangeRateSnapshot: $exchangeRateSnapshot,
            baseCurrencyTotal: (float) $baseCurrencyTotalStr,
            deliveryFee: (float) $orderDeliveryFeeStr,
            baseCurrencyDeliveryFee: (float) $baseDeliveryFeeStr,
            couponId: $appliedCoupon?->id,
            couponCodeSnapshot: $appliedCoupon?->code,
            couponNameSnapshot: $appliedCoupon?->name,
            couponDiscountTypeSnapshot: $appliedCoupon?->discount_type->value,
            couponDiscountValueBaseSnapshot: $appliedCoupon ? (float) $appliedCoupon->discount_value : null,
            items: $finalCalculatedItems,
        );
    }
}
