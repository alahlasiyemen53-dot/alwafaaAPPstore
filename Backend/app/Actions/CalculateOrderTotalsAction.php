<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\OrderItemData;
use App\Services\OrderPricingService;
use Carbon\Carbon;

final class CalculateOrderTotalsAction
{
    public function __construct(
        private readonly OrderPricingService $orderPricingService,
    ) {}

    /**
     * @param  array<int, OrderItemData>  $itemsData
     * @return array{
     *     original_subtotal: float,
     *     subtotal: float,
     *     subtotal_after_item_discounts: float,
     *     promotion_discount_total: float,
     *     bundle_discount_total: float,
     *     coupon_discount_total: float,
     *     discount_amount: float,
     *     delivery_fee: float,
     *     base_currency_delivery_fee: float,
     *     total: float,
     *     exchange_rate_snapshot: string,
     *     base_currency_total: float,
     *     coupon_id: int|null,
     *     coupon_code_snapshot: string|null,
     *     coupon_name_snapshot: string|null,
     *     coupon_discount_type_snapshot: string|null,
     *     coupon_discount_value_base_snapshot: float|null,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function execute(
        int $currencyId,
        array $itemsData,
        ?string $couponCode = null,
        ?int $customerId = null,
        ?Carbon $now = null,
        bool $lockForUpdate = false,
        ?float $deliveryFeeBase = null,
    ): array {
        $result = $this->orderPricingService->calculate(
            currencyId: $currencyId,
            itemsData: $itemsData,
            couponCode: $couponCode,
            customerId: $customerId,
            now: $now,
            lockForUpdate: $lockForUpdate,
            deliveryFeeBase: $deliveryFeeBase,
        );

        return $result->toArray();
    }
}
