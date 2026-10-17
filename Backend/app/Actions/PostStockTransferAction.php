<?php

namespace App\Actions;

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostStockTransferAction
{
    public function __construct(
        private readonly RecordStockMovementAction $recordStockMovementAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer): StockTransfer {
            /** @var StockTransfer $lockedTransfer */
            $lockedTransfer = StockTransfer::query()
                ->with(['items.productVariant', 'fromWarehouse', 'toWarehouse'])
                ->lockForUpdate()
                ->findOrFail($transfer->id);

            if ($lockedTransfer->status === StockTransferStatus::Posted) {
                throw new DomainException('أمر التحويل مرحّل بالفعل.');
            }

            if ($lockedTransfer->items->isEmpty()) {
                throw new DomainException('لا يمكن ترحيل أمر تحويل فارغ بدون بنود.');
            }

            if ($lockedTransfer->from_warehouse_id === $lockedTransfer->to_warehouse_id) {
                throw new DomainException('لا يمكن التحويل إلى نفس المستودع المصدر.');
            }

            // Perform TRANSFER_OUT from source warehouse and TRANSFER_IN to destination warehouse
            foreach ($lockedTransfer->items as $item) {
                $quantity = (float) $item->quantity;

                // 1. Source: OUT
                $this->recordStockMovementAction->execute(
                    warehouseId: $lockedTransfer->from_warehouse_id,
                    productVariantId: $item->product_variant_id,
                    movementType: StockMovementType::TransferOut,
                    direction: StockMovementDirection::Out,
                    quantity: $quantity,
                    reference: $lockedTransfer,
                    reasonCode: null,
                    notes: sprintf('تحويل منصرف إلى مستودع: %s (أمر رقم: %s)', $lockedTransfer->toWarehouse->name_ar, $lockedTransfer->transfer_number),
                    occurredAt: now(),
                    createdBy: auth()->id(),
                );

                // 2. Destination: IN
                $this->recordStockMovementAction->execute(
                    warehouseId: $lockedTransfer->to_warehouse_id,
                    productVariantId: $item->product_variant_id,
                    movementType: StockMovementType::TransferIn,
                    direction: StockMovementDirection::In,
                    quantity: $quantity,
                    reference: $lockedTransfer,
                    reasonCode: null,
                    notes: sprintf('تحويل وارد من مستودع: %s (أمر رقم: %s)', $lockedTransfer->fromWarehouse->name_ar, $lockedTransfer->transfer_number),
                    occurredAt: now(),
                    createdBy: auth()->id(),
                );
            }

            $lockedTransfer->update([
                'status' => StockTransferStatus::Posted,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->auditLogger->log(
                action: 'stock_transfer.posted',
                actor: auth()->user(),
                target: $lockedTransfer,
                payload: [
                    'transfer_number' => $lockedTransfer->transfer_number,
                    'from_warehouse' => $lockedTransfer->from_warehouse_id,
                    'to_warehouse' => $lockedTransfer->to_warehouse_id,
                    'items_count' => $lockedTransfer->items->count(),
                ]
            );

            return $lockedTransfer->fresh(['items.productVariant', 'fromWarehouse', 'toWarehouse']);
        });
    }
}
