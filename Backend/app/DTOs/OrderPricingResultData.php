<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class OrderPricingResultData
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public float $originalSubtotal,
        public float $promotionDiscountTotal,
        public float $bundleDiscountTotal,
        public float $couponDiscountTotal,
        public float $totalDiscount,
        public float $subtotalAfterItemDiscounts,
        public float $finalTotal,
        public string $exchangeRateSnapshot,
        public float $baseCurrencyTotal,
        public float $deliveryFee = 0.0,
        public float $baseCurrencyDeliveryFee = 0.0,
        public ?int $couponId = null,
        public ?string $couponCodeSnapshot = null,
        public ?string $couponNameSnapshot = null,
        public ?string $couponDiscountTypeSnapshot = null,
        public ?float $couponDiscountValueBaseSnapshot = null,
        public array $items = [],
    ) {}

    public function toArray(): array
    {
        return [
            'original_subtotal' => $this->originalSubtotal,
            'subtotal' => $this->originalSubtotal,
            'subtotal_after_item_discounts' => $this->subtotalAfterItemDiscounts,
            'promotion_discount_total' => $this->promotionDiscountTotal,
            'bundle_discount_total' => $this->bundleDiscountTotal,
            'coupon_discount_total' => $this->couponDiscountTotal,
            'discount_amount' => $this->totalDiscount,
            'delivery_fee' => $this->deliveryFee,
            'base_currency_delivery_fee' => $this->baseCurrencyDeliveryFee,
            'total' => $this->finalTotal,
            'exchange_rate_snapshot' => $this->exchangeRateSnapshot,
            'base_currency_total' => $this->baseCurrencyTotal,
            'coupon_id' => $this->couponId,
            'coupon_code_snapshot' => $this->couponCodeSnapshot,
            'coupon_name_snapshot' => $this->couponNameSnapshot,
            'coupon_discount_type_snapshot' => $this->couponDiscountTypeSnapshot,
            'coupon_discount_value_base_snapshot' => $this->couponDiscountValueBaseSnapshot,
            'items' => $this->items,
        ];
    }
}
