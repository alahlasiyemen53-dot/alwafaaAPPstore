<?php

namespace App\Actions;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockAdjustmentStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\StockAdjustment;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostStockAdjustmentAction
{
    public function __construct(
        private readonly RecordStockMovementAction $recordStockMovementAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(StockAdjustment $adjustment): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment): StockAdjustment {
            /** @var StockAdjustment $lockedAdjustment */
            $lockedAdjustment = StockAdjustment::query()
                ->with(['items.productVariant', 'warehouse'])
                ->lockForUpdate()
                ->findOrFail($adjustment->id);

            if ($lockedAdjustment->status === StockAdjustmentStatus::Posted) {
                throw new DomainException('أمر التسوية مرحّل بالفعل.');
            }

            if ($lockedAdjustment->items->isEmpty()) {
                throw new DomainException('لا يمكن ترحيل أمر تسوية فارغ بدون بنود.');
            }

            $reason = $lockedAdjustment->reason_code;

            foreach ($lockedAdjustment->items as $item) {
                $direction = $item->direction;
                $quantity = (float) $item->quantity;

                $movementType = match (true) {
                    $reason === StockAdjustmentReason::Damaged => StockMovementType::Damaged,
                    $direction === StockMovementDirection::In => StockMovementType::AdjustmentIn,
                    default => StockMovementType::AdjustmentOut,
                };

                $this->recordStockMovementAction->execute(
                    warehouseId: $lockedAdjustment->warehouse_id,
                    productVariantId: $item->product_variant_id,
                    movementType: $movementType,
                    direction: $direction,
                    quantity: $quantity,
                    reference: $lockedAdjustment,
                    reasonCode: $reason->value,
                    notes: sprintf('تسوية مخزنية (%s) - أمر رقم: %s', $reason->label(), $lockedAdjustment->adjustment_number),
                    occurredAt: now(),
                    createdBy: auth()->id(),
                );
            }

            $lockedAdjustment->update([
                'status' => StockAdjustmentStatus::Posted,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->auditLogger->log(
                action: 'stock_adjustment.posted',
                actor: auth()->user(),
                target: $lockedAdjustment,
                payload: [
                    'adjustment_number' => $lockedAdjustment->adjustment_number,
                    'warehouse_id' => $lockedAdjustment->warehouse_id,
                    'reason' => $reason->value,
                    'items_count' => $lockedAdjustment->items->count(),
                ]
            );

            return $lockedAdjustment->fresh(['items.productVariant', 'warehouse']);
        });
    }
}
