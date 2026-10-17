<?php

namespace App\Actions;

use App\DTOs\SalesReturnItemData;
use App\Models\Order;
use App\Models\OrderItem;
use DomainException;

final class CalculateSalesReturnTotalsAction
{
    public function __construct(
        private readonly CalculateReturnableQuantityAction $calculateReturnable,
    ) {}

    /**
     * @param  array<int, SalesReturnItemData>  $itemsData
     * @return array{
     *     order: Order,
     *     subtotal: float,
     *     total: float,
     *     base_currency_total: float,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function execute(int $orderId, array $itemsData, ?int $excludeSalesReturnId = null): array
    {
        $order = Order::query()->with(['items', 'currency'])->findOrFail($orderId);

        if (empty($itemsData)) {
            throw new DomainException('يجب إضافة بند واحد على الأقل في المرتجع.');
        }

        $calculatedItems = [];
        $subtotal = 0.0;
        $baseCurrencyTotal = 0.0;

        foreach ($itemsData as $item) {
            $orderItem = OrderItem::query()
                ->where('order_id', $order->id)
                ->where('id', $item->orderItemId)
                ->first();

            if (! $orderItem) {
                throw new DomainException(sprintf('بند الطلب رقم (%d) غير تابع للطلب المحدد رقم (%s).', $item->orderItemId, $order->order_number));
            }

            if ($orderItem->product_variant_id !== $item->productVariantId) {
                throw new DomainException(sprintf('المتغير المحدد لا يتطابق مع بند الطلب رقم (%d).', $item->orderItemId));
            }

            if ($item->returnQuantity <= 0) {
                throw new DomainException('كمية الإرجاع يجب أن تكون أكبر من الصفر.');
            }

            // Check returnable quantity
            $returnableData = $this->calculateReturnable->execute($orderItem, $excludeSalesReturnId);
            $returnable = $returnableData['returnable_quantity'];

            if ($item->returnQuantity > $returnable) {
                throw new DomainException(sprintf(
                    'الكمية المطلوب إرجاعها (%.4f) للصنف (%s) تتجاوز الكمية المتبقية القابلة للإرجاع (%.4f).',
                    $item->returnQuantity,
                    $orderItem->sku_snapshot,
                    $returnable
                ));
            }

            // Historical pricing calculation using effective net unit value (accounting for promotions and coupons)
            $unitRatio = $orderItem->quantity > 0 ? ($orderItem->base_quantity / $orderItem->quantity) : 1.0;
            $returnBaseQty = round($item->returnQuantity * $unitRatio, 4);

            $unitPriceSnapshot = $orderItem->quantity > 0
                ? round((float) $orderItem->line_total / (float) $orderItem->quantity, 4)
                : (float) $orderItem->unit_price;

            $baseExchangeRate = (float) $order->exchange_rate_snapshot ?: 1.0;
            $baseUnitPriceSnapshot = round($unitPriceSnapshot / $baseExchangeRate, 4);

            $lineTotal = round($item->returnQuantity * $unitPriceSnapshot, 4);
            $baseLineTotal = round($item->returnQuantity * $baseUnitPriceSnapshot, 4);

            $subtotal += $lineTotal;
            $baseCurrencyTotal += $baseLineTotal;

            $calculatedItems[] = [
                'order_item_id' => $orderItem->id,
                'product_variant_id' => $orderItem->product_variant_id,
                'product_name_snapshot' => $orderItem->product_name_snapshot,
                'variant_name_snapshot' => $orderItem->variant_name_snapshot,
                'sku_snapshot' => $orderItem->sku_snapshot,
                'unit_id' => $orderItem->unit_id,
                'unit_name_snapshot' => $orderItem->unit_name_snapshot,
                'return_quantity' => $item->returnQuantity,
                'return_base_quantity' => $returnBaseQty,
                'unit_price_snapshot' => $unitPriceSnapshot,
                'line_total' => $lineTotal,
                'base_currency_unit_price_snapshot' => $baseUnitPriceSnapshot,
                'base_currency_line_total' => $baseLineTotal,
                'reason_code' => $item->reasonCode,
                'notes' => $item->notes,
            ];
        }

        return [
            'order' => $order,
            'subtotal' => round($subtotal, 4),
            'total' => round($subtotal, 4),
            'base_currency_total' => round($baseCurrencyTotal, 4),
            'items' => $calculatedItems,
        ];
    }
}
