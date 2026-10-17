<?php

namespace App\Services;

use App\Data\PurchaseInvoiceData;

final class PurchaseCalculationService
{
    /**
     * @return array{
     *     calculated_items: array<int, array{
     *         product_variant_id: int,
     *         unit_id: int,
     *         quantity: float,
     *         base_quantity: float,
     *         free_quantity: float,
     *         unit_cost: float,
     *         line_subtotal: float,
     *         discount_amount: float,
     *         line_total: float,
     *     }>,
     *     subtotal: float,
     *     discount_amount: float,
     *     other_costs: float,
     *     total: float,
     * }
     */
    public function calculateInvoice(PurchaseInvoiceData $data): array
    {
        $calculatedItems = [];
        $subtotal = 0.0;
        $itemsDiscount = 0.0;

        foreach ($data->items as $item) {
            $lineSubtotal = round($item->quantity * $item->unitCost, 4);
            $lineDiscount = min($lineSubtotal, round($item->discountAmount, 4));
            $lineTotal = max(0.0, round($lineSubtotal - $lineDiscount, 4));

            $subtotal += $lineSubtotal;
            $itemsDiscount += $lineDiscount;

            $calculatedItems[] = [
                'product_variant_id' => $item->productVariantId,
                'unit_id' => $item->unitId,
                'quantity' => $item->quantity,
                'base_quantity' => $item->baseQuantity,
                'free_quantity' => $item->freeQuantity,
                'unit_cost' => $item->unitCost,
                'line_subtotal' => $lineSubtotal,
                'discount_amount' => $lineDiscount,
                'line_total' => $lineTotal,
            ];
        }

        // Global discount is added to line discounts or subtracted from subtotal
        $totalDiscount = round($itemsDiscount + $data->discountAmount, 4);
        $otherCosts = round($data->otherCosts, 4);
        $total = max(0.0, round($subtotal - $totalDiscount + $otherCosts, 4));

        return [
            'calculated_items' => $calculatedItems,
            'subtotal' => round($subtotal, 4),
            'discount_amount' => $totalDiscount,
            'other_costs' => $otherCosts,
            'total' => $total,
        ];
    }
}
