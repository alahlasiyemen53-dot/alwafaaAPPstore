<?php

namespace App\Actions;

use App\Enums\ReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Services\AuditLogger;

final class ReleaseOrderInventoryAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Order $order): void
    {
        // Fetch all active reservations for this order
        $activeReservations = InventoryReservation::query()
            ->where('order_id', $order->id)
            ->where('status', ReservationStatus::Active)
            ->lockForUpdate()
            ->get()
            ->sortBy([
                fn ($res) => $res->warehouse_id,
                fn ($res) => $res->product_variant_id,
            ]);

        foreach ($activeReservations as $reservation) {
            /** @var Inventory|null $inventory */
            $inventory = Inventory::query()
                ->where('warehouse_id', $reservation->warehouse_id)
                ->where('product_variant_id', $reservation->product_variant_id)
                ->lockForUpdate()
                ->first();

            $releaseQty = (float) $reservation->quantity;

            if ($inventory !== null) {
                $newReservedQuantity = max(0.0, round((float) $inventory->reserved_quantity - $releaseQty, 4));
                $inventory->update([
                    'reserved_quantity' => $newReservedQuantity,
                ]);
            }

            $reservation->update([
                'status' => ReservationStatus::Released,
                'released_at' => now(),
            ]);

            $this->auditLogger->record('admin.inventory.released', $reservation, metadata: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'warehouse_id' => $reservation->warehouse_id,
                'product_variant_id' => $reservation->product_variant_id,
                'released_quantity' => $releaseQty,
                'remaining_reserved' => $inventory ? $inventory->reserved_quantity : 0,
            ]);
        }
    }
}
