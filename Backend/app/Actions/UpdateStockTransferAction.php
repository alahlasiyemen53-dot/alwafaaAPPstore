<?php

namespace App\Actions;

use App\Data\StockTransferData;
use App\Enums\StockTransferStatus;
use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateStockTransferAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(StockTransfer $transfer, StockTransferData $data): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $data): StockTransfer {
            /** @var StockTransfer $lockedTransfer */
            $lockedTransfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($lockedTransfer->status !== StockTransferStatus::Draft) {
                throw new DomainException('لا يمكن تعديل أمر تحويل تم ترحيله مسبقاً.');
            }

            if ($data->fromWarehouseId === $data->toWarehouseId) {
                throw new DomainException('لا يمكن التحويل إلى نفس المستودع المصدر.');
            }

            $fromWarehouse = Warehouse::query()->findOrFail($data->fromWarehouseId);
            $toWarehouse = Warehouse::query()->findOrFail($data->toWarehouseId);

            if (! $fromWarehouse->is_active || ! $toWarehouse->is_active) {
                throw new DomainException('أحد المستودعات المحددة في أمر التحويل معطل.');
            }

            $lockedTransfer->update([
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'notes' => $data->notes,
                'updated_by' => auth()->id(),
            ]);

            $lockedTransfer->items()->delete();

            foreach ($data->items as $itemData) {
                if ($itemData->quantity <= 0) {
                    continue;
                }

                ProductVariant::query()->findOrFail($itemData->productVariantId);

                StockTransferItem::query()->create([
                    'stock_transfer_id' => $lockedTransfer->id,
                    'product_variant_id' => $itemData->productVariantId,
                    'quantity' => $itemData->quantity,
                ]);
            }

            if ($lockedTransfer->items()->count() === 0) {
                throw new DomainException('يجب تحديد كمية أكبر من الصفر لصنف واحد على الأقل.');
            }

            $this->auditLogger->log(
                action: 'stock_transfer.updated',
                actor: auth()->user(),
                target: $lockedTransfer,
                payload: ['transfer_number' => $lockedTransfer->transfer_number]
            );

            return $lockedTransfer->fresh(['items.productVariant', 'fromWarehouse', 'toWarehouse']);
        });
    }
}
