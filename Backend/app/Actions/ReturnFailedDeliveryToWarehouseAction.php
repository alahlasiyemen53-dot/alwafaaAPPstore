<?php

namespace App\Actions;

use App\DTOs\ReturnDeliveryToWarehouseData;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReturnFailedDeliveryToWarehouseAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Delivery $delivery, ReturnDeliveryToWarehouseData $data, int $userId): Delivery
    {
        return DB::transaction(function () use ($delivery, $data, $userId): Delivery {
            /** @var Delivery $lockedDelivery */
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);

            // Idempotency: if already returned
            if ($lockedDelivery->status === DeliveryStatus::ReturnedToWarehouse) {
                return $lockedDelivery->load(['order', 'driver']);
            }

            if (! $lockedDelivery->canBeReturnedToWarehouse()) {
                throw new DomainException(sprintf(
                    'لا يمكن إرجاع الشحنة للمستودع إلا بعد تسجيل تعذر تسليمها (FAILED). حالتها الحالية: (%s).',
                    $lockedDelivery->status->label()
                ));
            }

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->with('items')->lockForUpdate()->findOrFail($lockedDelivery->order_id);

            $warehouseId = $data->receivingWarehouseId ?: $lockedOrder->warehouse_id;
            $now = now();

            // 1. Lock receiving inventory rows deterministically by (warehouse_id, product_variant_id)
            $sortedItems = $lockedOrder->items->sortBy([
                fn () => $warehouseId,
                fn ($item) => $item->product_variant_id,
            ]);

            foreach ($sortedItems as $item) {
                $inventory = Inventory::query()
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    $inventory = Inventory::create([
                        'warehouse_id' => $warehouseId,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => 0.0,
                        'reserved_quantity' => 0.0,
                    ]);
                }

                $returnQty = (float) $item->base_quantity;

                $beforeQty = (float) $inventory->quantity;
                $afterQty = round($beforeQty + $returnQty, 4);

                // Increase inventories.quantity (Physical return to warehouse)
                $inventory->quantity = $afterQty;
                $inventory->save();

                // Create DELIVERY_RETURN / IN Stock Movement (Distinct from customer SALE_RETURN)
                StockMovement::create([
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $item->product_variant_id,
                    'movement_type' => StockMovementType::DeliveryReturn,
                    'direction' => StockMovementDirection::In,
                    'quantity' => $returnQty,
                    'before_quantity' => $beforeQty,
                    'after_quantity' => $afterQty,
                    'reference_type' => 'Delivery',
                    'reference_id' => $lockedDelivery->id,
                    'reason_code' => 'delivery_failed_returned',
                    'notes' => sprintf('إرجاع بضاعة لم تسلم للمستودع للطلب %s عبر المندوب %s', $lockedOrder->order_number, $lockedDelivery->driver?->name),
                    'occurred_at' => $now,
                    'created_by' => $userId > 0 ? $userId : null,
                ]);
            }

            // 2. Update Delivery
            $lockedDelivery->update([
                'status' => DeliveryStatus::ReturnedToWarehouse,
                'returned_at' => $now,
                'returned_by' => $userId > 0 ? $userId : null,
                'receiving_warehouse_id' => $warehouseId,
                'notes' => $data->notes ?: $lockedDelivery->notes,
            ]);

            // 3. Update Order Status
            $lockedOrder->update([
                'status' => OrderStatus::ReturnedToWarehouse,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            // 4. Audit
            $this->auditLogger->record('admin.delivery.returned_to_warehouse', $lockedDelivery, metadata: [
                'delivery_number' => $lockedDelivery->delivery_number,
                'order_number' => $lockedOrder->order_number,
                'receiving_warehouse_id' => $warehouseId,
            ]);

            return $lockedDelivery->load(['order.customer', 'driver', 'receivingWarehouse']);
        });
    }
}
