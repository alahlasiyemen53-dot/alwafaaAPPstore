<?php

namespace App\Actions;

use App\Enums\ReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Services\AuditLogger;
use DomainException;

final class ReserveOrderInventoryAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Order $order): void
    {
        // Deterministic Lock Ordering: Sort stocked items by warehouse_id then product_variant_id to prevent deadlocks
        $orderItems = $order->items()
            ->with('productVariant')
            ->get()
            ->filter(fn ($item) => $item->isStocked())
            ->sortBy([
                fn () => $order->warehouse_id,
                fn ($item) => $item->product_variant_id,
            ]);

        // If the order has no STOCKED items (e.g. exclusively ON_DEMAND), no warehouse reservation is created
        if ($orderItems->isEmpty()) {
            return;
        }

        foreach ($orderItems as $item) {
            /** @var Inventory|null $inventory */
            $inventory = Inventory::query()
                ->where('warehouse_id', $order->warehouse_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->lockForUpdate()
                ->first();

            $variantSku = $item->productVariant->sku ?? "ID: {$item->product_variant_id}";
            $requestedQty = (float) $item->base_quantity;

            if ($inventory === null) {
                throw new DomainException(sprintf('لا يوجد رصيد مخزني مسجل للمنتج (%s) في المستودع المحدد.', $variantSku));
            }

            $currentQuantity = (float) $inventory->quantity;
            $currentReserved = (float) $inventory->reserved_quantity;
            $availableQuantity = max(0.0, $currentQuantity - $currentReserved);

            if ($requestedQty > $availableQuantity) {
                throw new DomainException(sprintf(
                    'الرصيد المتاح من المنتج (%s) غير كافٍ. المتوفر حالياً: %s، المطلوب: %s.',
                    $variantSku,
                    rtrim(rtrim(number_format($availableQuantity, 4, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($requestedQty, 4, '.', ''), '0'), '.')
                ));
            }

            // Invariant check: cannot exceed total on hand
            $newReservedQuantity = round($currentReserved + $requestedQty, 4);
            if ($newReservedQuantity > $currentQuantity) {
                throw new DomainException(sprintf('حجز الكمية للمنتج (%s) سيتجاوز الرصيد الفعلي في المستودع (منع البيع الزائد).', $variantSku));
            }

            // Update inventory balance
            $inventory->update([
                'reserved_quantity' => $newReservedQuantity,
            ]);

            // Create or update active reservation record
            $reservation = InventoryReservation::query()->updateOrCreate(
                ['order_item_id' => $item->id],
                [
                    'warehouse_id' => $order->warehouse_id,
                    'product_variant_id' => $item->product_variant_id,
                    'order_id' => $order->id,
                    'quantity' => $requestedQty,
                    'status' => ReservationStatus::Active,
                    'reserved_at' => now(),
                    'released_at' => null,
                ]
            );

            $this->auditLogger->record('admin.inventory.reserved', $reservation, metadata: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'warehouse_id' => $order->warehouse_id,
                'product_variant_id' => $item->product_variant_id,
                'reserved_quantity' => $requestedQty,
                'total_reserved' => $newReservedQuantity,
            ]);
        }
    }
}
