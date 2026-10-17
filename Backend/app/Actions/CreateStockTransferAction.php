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

final class CreateStockTransferAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(StockTransferData $data): StockTransfer
    {
        return DB::transaction(function () use ($data): StockTransfer {
            if ($data->fromWarehouseId === $data->toWarehouseId) {
                throw new DomainException('لا يمكن التحويل إلى نفس المستودع المصدر.');
            }

            $fromWarehouse = Warehouse::query()->findOrFail($data->fromWarehouseId);
            $toWarehouse = Warehouse::query()->findOrFail($data->toWarehouseId);

            if (! $fromWarehouse->is_active || ! $toWarehouse->is_active) {
                throw new DomainException('أحد المستودعات المحددة في عملية التحويل معطل.');
            }

            if (empty($data->items)) {
                throw new DomainException('يجب إضافة صنف واحد على الأقل في أمر التحويل.');
            }

            $transferNumber = $data->transferNumber;
            if ($transferNumber === null || trim($transferNumber) === '') {
                $datePrefix = date('Ymd');
                $count = StockTransfer::query()
                    ->whereDate('created_at', today())
                    ->count();
                $transferNumber = sprintf('TRN-%s-%04d', $datePrefix, $count + 1);
            }

            $transfer = StockTransfer::query()->create([
                'transfer_number' => $transferNumber,
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'status' => StockTransferStatus::Draft,
                'notes' => $data->notes,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($data->items as $itemData) {
                if ($itemData->quantity <= 0) {
                    continue;
                }

                ProductVariant::query()->findOrFail($itemData->productVariantId);

                StockTransferItem::query()->create([
                    'stock_transfer_id' => $transfer->id,
                    'product_variant_id' => $itemData->productVariantId,
                    'quantity' => $itemData->quantity,
                ]);
            }

            if ($transfer->items()->count() === 0) {
                throw new DomainException('يجب تحديد كمية أكبر من الصفر لصنف واحد على الأقل.');
            }

            $this->auditLogger->log(
                action: 'stock_transfer.created',
                actor: auth()->user(),
                target: $transfer,
                payload: ['transfer_number' => $transfer->transfer_number]
            );

            return $transfer->load(['items.productVariant', 'fromWarehouse', 'toWarehouse']);
        });
    }
}
