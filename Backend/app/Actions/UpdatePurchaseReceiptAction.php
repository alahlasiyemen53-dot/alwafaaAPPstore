<?php

namespace App\Actions;

use App\Data\PurchaseReceiptData;
use App\Enums\PurchaseReceiptStatus;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdatePurchaseReceiptAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseReceipt $receipt, PurchaseReceiptData $data): PurchaseReceipt
    {
        return DB::transaction(function () use ($receipt, $data): PurchaseReceipt {
            $lockedReceipt = PurchaseReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($lockedReceipt->status !== PurchaseReceiptStatus::Draft) {
                throw new DomainException('لا يمكن تعديل سند استلام تم ترحيله مسبقاً.');
            }

            $warehouse = Warehouse::query()->findOrFail($data->warehouseId);
            if (! $warehouse->is_active) {
                throw new DomainException('المستودع المحدد معطل ولا يمكن الاستلام فيه.');
            }

            $invoice = $lockedReceipt->purchaseInvoice()->with('items')->firstOrFail();
            $invoiceItemsById = $invoice->items->keyBy('id');

            $lockedReceipt->update([
                'warehouse_id' => $warehouse->id,
                'notes' => $data->notes,
                'updated_by' => auth()->id(),
            ]);

            // Delete old draft items and re-insert
            $lockedReceipt->items()->delete();

            foreach ($data->items as $itemData) {
                if ($itemData->receivedBaseQuantity <= 0) {
                    continue;
                }

                /** @var PurchaseInvoiceItem|null $invoiceItem */
                $invoiceItem = $invoiceItemsById->get($itemData->purchaseInvoiceItemId);
                if (! $invoiceItem) {
                    throw new DomainException('بند الفاتورة المحدد غير موجود.');
                }

                $remaining = $invoiceItem->remaining_receivable_quantity;
                if ($itemData->receivedBaseQuantity > $remaining) {
                    throw new DomainException(sprintf(
                        'الكمية المراد استلامها (%s) تتجاوز الكمية المتبقية للاستلام (%s).',
                        $itemData->receivedBaseQuantity,
                        $remaining
                    ));
                }

                $exchangeRate = (float) $invoice->exchange_rate;
                $unitCost = (float) $invoiceItem->unit_cost;

                PurchaseReceiptItem::query()->create([
                    'purchase_receipt_id' => $lockedReceipt->id,
                    'purchase_invoice_item_id' => $invoiceItem->id,
                    'product_variant_id' => $invoiceItem->product_variant_id,
                    'received_base_quantity' => $itemData->receivedBaseQuantity,
                    'unit_cost_snapshot' => $unitCost,
                    'currency_id' => $invoice->currency_id,
                    'exchange_rate_snapshot' => $exchangeRate,
                    'base_currency_cost' => $unitCost * $exchangeRate,
                    'notes' => $itemData->notes,
                ]);
            }

            if ($lockedReceipt->items()->count() === 0) {
                throw new DomainException('يجب تحديد كمية استلام أكبر من الصفر لبند واحد على الأقل.');
            }

            $this->auditLogger->log(
                action: 'purchase_receipt.updated',
                actor: auth()->user(),
                target: $lockedReceipt,
                payload: ['receipt_number' => $lockedReceipt->receipt_number]
            );

            return $lockedReceipt->fresh(['items.productVariant', 'warehouse', 'purchaseInvoice']);
        });
    }
}
