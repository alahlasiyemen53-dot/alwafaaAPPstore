<?php

namespace App\Actions;

use App\Data\StockAdjustmentData;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockAdjustmentStatus;
use App\Enums\StockMovementDirection;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateStockAdjustmentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(StockAdjustment $adjustment, StockAdjustmentData $data): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $data): StockAdjustment {
            /** @var StockAdjustment $lockedAdjustment */
            $lockedAdjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if ($lockedAdjustment->status !== StockAdjustmentStatus::Draft) {
                throw new DomainException('لا يمكن تعديل أمر تسوية تم ترحيله مسبقاً.');
            }

            $warehouse = Warehouse::query()->findOrFail($data->warehouseId);
            if (! $warehouse->is_active) {
                throw new DomainException('المستودع المحدد معطل.');
            }

            $reason = StockAdjustmentReason::tryFrom($data->reasonCode);
            if (! $reason) {
                throw new DomainException('سبب التسوية المحدد غير صالح.');
            }

            $lockedAdjustment->update([
                'warehouse_id' => $warehouse->id,
                'reason_code' => $reason,
                'notes' => $data->notes,
                'updated_by' => auth()->id(),
            ]);

            $lockedAdjustment->items()->delete();

            foreach ($data->items as $itemData) {
                if ($itemData->quantity <= 0) {
                    continue;
                }

                ProductVariant::query()->findOrFail($itemData->productVariantId);

                $direction = StockMovementDirection::tryFrom($itemData->direction);
                if (! $direction) {
                    throw new DomainException('اتجاه الحركة في بند التسوية غير صالح.');
                }

                if ($reason === StockAdjustmentReason::Damaged && $direction !== StockMovementDirection::Out) {
                    throw new DomainException('تسوية البضاعة التالفة يجب أن تكون منصرفة (عجز/إخراج) حصراً.');
                }

                StockAdjustmentItem::query()->create([
                    'stock_adjustment_id' => $lockedAdjustment->id,
                    'product_variant_id' => $itemData->productVariantId,
                    'direction' => $direction,
                    'quantity' => $itemData->quantity,
                    'notes' => $itemData->notes,
                ]);
            }

            if ($lockedAdjustment->items()->count() === 0) {
                throw new DomainException('يجب تحديد كمية أكبر من الصفر لصنف واحد على الأقل.');
            }

            $this->auditLogger->log(
                action: 'stock_adjustment.updated',
                actor: auth()->user(),
                target: $lockedAdjustment,
                payload: ['adjustment_number' => $lockedAdjustment->adjustment_number]
            );

            return $lockedAdjustment->fresh(['items.productVariant', 'warehouse']);
        });
    }
}
