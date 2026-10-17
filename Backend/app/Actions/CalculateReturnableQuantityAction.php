<?php

namespace App\Actions;

use App\Enums\SalesReturnStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\OrderItem;
use App\Models\SalesReturnItem;
use App\Models\StockMovement;

final class CalculateReturnableQuantityAction
{
    /**
     * @return array{
     *     issued_quantity: float,
     *     already_returned_quantity: float,
     *     returnable_quantity: float
     * }
     */
    public function execute(OrderItem $orderItem, ?int $excludeSalesReturnId = null): array
    {
        // 1. Physically issued quantity through valid SALE / OUT movement(s) linked to this order or order item
        $issuedMovementsQuery = StockMovement::query()
            ->where('movement_type', StockMovementType::Sale)
            ->where('direction', StockMovementDirection::Out)
            ->where('product_variant_id', $orderItem->product_variant_id)
            ->where(function ($q) use ($orderItem): void {
                $q->where(function ($sub) use ($orderItem): void {
                    $sub->where('reference_type', 'Order')
                        ->where('reference_id', $orderItem->order_id);
                })->orWhere(function ($sub) use ($orderItem): void {
                    $sub->where('reference_type', 'OrderItem')
                        ->where('reference_id', $orderItem->id);
                });
            });

        $issuedQuantity = (float) $issuedMovementsQuery->sum('quantity');

        // 2. Already returned quantity through POSTED sales returns for this order item
        $returnedQuery = SalesReturnItem::query()
            ->where('order_item_id', $orderItem->id)
            ->whereHas('salesReturn', function ($q) use ($excludeSalesReturnId): void {
                $q->where('status', SalesReturnStatus::Posted);
                if ($excludeSalesReturnId !== null) {
                    $q->where('id', '!=', $excludeSalesReturnId);
                }
            });

        $alreadyReturned = (float) $returnedQuery->sum('return_quantity');

        $returnable = max(0.0, round($issuedQuantity - $alreadyReturned, 4));

        return [
            'issued_quantity' => round($issuedQuantity, 4),
            'already_returned_quantity' => round($alreadyReturned, 4),
            'returnable_quantity' => $returnable,
        ];
    }
}
