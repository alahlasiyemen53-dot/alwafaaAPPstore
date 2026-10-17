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

final class CreateStockAdjustmentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(StockAdjustmentData $data): StockAdjustment
    {
        return DB::transaction(function () use ($data): StockAdjustment {
            $warehouse = Warehouse::query()->findOrFail($data->warehouseId);
            if (! $warehouse->is_active) {
                throw new DomainException('المستودع المحدد معطل.');
            }

            $reason = StockAdjustmentReason::tryFrom($data->reasonCode);
            if (! $reason) {
                throw new DomainException('سبب التسوية المحدد غير صالح.');
            }

            if (empty($data->items)) {
                throw new DomainException('يجب إضافة صنف واحد على الأقل في أمر التسوية.');
            }

            $adjustmentNumber = $data->adjustmentNumber;
            if ($adjustmentNumber === null || trim($adjustmentNumber) === '') {
                $datePrefix = date('Ymd');
                $count = StockAdjustment::query()
                    ->whereDate('created_at', today())
                    ->count();
                $adjustmentNumber = sprintf('ADJ-%s-%04d', $datePrefix, $count + 1);
            }

            $adjustment = StockAdjustment::query()->create([
                'adjustment_number' => $adjustmentNumber,
                'warehouse_id' => $warehouse->id,
                'status' => StockAdjustmentStatus::Draft,
                'reason_code' => $reason,
                'notes' => $data->notes,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

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
                    'stock_adjustment_id' => $adjustment->id,
                    'product_variant_id' => $itemData->productVariantId,
                    'direction' => $direction,
                    'quantity' => $itemData->quantity,
                    'notes' => $itemData->notes,
                ]);
            }

            if ($adjustment->items()->count() === 0) {
                throw new DomainException('يجب تحديد كمية أكبر من الصفر لصنف واحد على الأقل.');
            }

            $this->auditLogger->log(
                action: 'stock_adjustment.created',
                actor: auth()->user(),
                target: $adjustment,
                payload: ['adjustment_number' => $adjustment->adjustment_number]
            );

            return $adjustment->load(['items.productVariant', 'warehouse']);
        });
    }
}
