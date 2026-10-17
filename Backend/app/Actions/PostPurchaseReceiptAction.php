<?php

namespace App\Actions;

use App\Enums\PurchaseReceiptStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\PurchaseReceipt;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostPurchaseReceiptAction
{
    public function __construct(
        private readonly RecordStockMovementAction $recordStockMovementAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseReceipt $receipt): PurchaseReceipt
    {
        return DB::transaction(function () use ($receipt): PurchaseReceipt {
            /** @var PurchaseReceipt $lockedReceipt */
            $lockedReceipt = PurchaseReceipt::query()
                ->with(['items.purchaseInvoiceItem', 'warehouse', 'purchaseInvoice'])
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($lockedReceipt->status === PurchaseReceiptStatus::Posted) {
                throw new DomainException('سند الاستلام مرحّل بالفعل.');
            }

            if ($lockedReceipt->items->isEmpty()) {
                throw new DomainException('لا يمكن ترحيل سند استلام فارغ بدون بنود.');
            }

            // Validate against over-receipt with other posted receipts
            foreach ($lockedReceipt->items as $item) {
                $invoiceItem = $item->purchaseInvoiceItem;
                if (! $invoiceItem) {
                    throw new DomainException('بند الفاتورة الأصلي غير موجود.');
                }

                $totalReceivable = $invoiceItem->total_receivable_quantity;

                // Sum quantities received in OTHER posted receipts
                $otherReceived = (float) $invoiceItem->purchaseReceiptItems()
                    ->where('purchase_receipt_id', '!=', $lockedReceipt->id)
                    ->whereHas('purchaseReceipt', fn ($q) => $q->where('status', PurchaseReceiptStatus::Posted))
                    ->sum('received_base_quantity');

                $newTotalReceived = $otherReceived + (float) $item->received_base_quantity;

                if ($newTotalReceived > $totalReceivable) {
                    throw new DomainException(sprintf(
                        'لا يمكن ترحيل السند: الكمية الإجمالية المستلمة (%s) ستتجاوز الكمية المطلوبة بالفاتورة (%s) للصنف: %s.',
                        $newTotalReceived,
                        $totalReceivable,
                        $item->productVariant->sku ?? ''
                    ));
                }
            }

            // Execute Stock Movements and update Inventory atomically
            foreach ($lockedReceipt->items as $item) {
                $this->recordStockMovementAction->execute(
                    warehouseId: $lockedReceipt->warehouse_id,
                    productVariantId: $item->product_variant_id,
                    movementType: StockMovementType::PurchaseReceipt,
                    direction: StockMovementDirection::In,
                    quantity: (float) $item->received_base_quantity,
                    reference: $lockedReceipt,
                    reasonCode: null,
                    notes: sprintf('استلام مشتريات سند رقم: %s', $lockedReceipt->receipt_number),
                    occurredAt: now(),
                    createdBy: auth()->id(),
                );
            }

            $lockedReceipt->update([
                'status' => PurchaseReceiptStatus::Posted,
                'received_at' => now(),
                'received_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->auditLogger->log(
                action: 'purchase_receipt.posted',
                actor: auth()->user(),
                target: $lockedReceipt,
                payload: [
                    'receipt_number' => $lockedReceipt->receipt_number,
                    'warehouse_id' => $lockedReceipt->warehouse_id,
                    'items_count' => $lockedReceipt->items->count(),
                ]
            );

            return $lockedReceipt->fresh(['items.productVariant', 'warehouse', 'purchaseInvoice']);
        });
    }
}
