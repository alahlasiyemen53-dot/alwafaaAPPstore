<?php

namespace App\Actions;

use App\Data\PurchaseReceiptData;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseReceiptAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseReceiptData $data): PurchaseReceipt
    {
        return DB::transaction(function () use ($data): PurchaseReceipt {
            $invoice = PurchaseInvoice::query()->with('items')->findOrFail($data->purchaseInvoiceId);

            if ($invoice->status !== PurchaseInvoiceStatus::Posted) {
                throw new DomainException('لا يمكن إنشاء سند استلام إلا لفاتورة شراء مرحّلة.');
            }

            $warehouse = Warehouse::query()->findOrFail($data->warehouseId);
            if (! $warehouse->is_active) {
                throw new DomainException('المستودع المحدد معطل ولا يمكن الاستلام فيه.');
            }

            if (empty($data->items)) {
                throw new DomainException('يجب إضافة بند واحد على الأقل في سند الاستلام.');
            }

            $receiptNumber = $data->receiptNumber;
            if ($receiptNumber === null || trim($receiptNumber) === '') {
                $datePrefix = date('Ymd');
                $count = PurchaseReceipt::query()
                    ->whereDate('created_at', today())
                    ->count();
                $receiptNumber = sprintf('REC-%s-%04d', $datePrefix, $count + 1);
            }

            $receipt = PurchaseReceipt::query()->create([
                'receipt_number' => $receiptNumber,
                'purchase_invoice_id' => $invoice->id,
                'warehouse_id' => $warehouse->id,
                'status' => PurchaseReceiptStatus::Draft,
                'notes' => $data->notes,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $invoiceItemsById = $invoice->items->keyBy('id');

            foreach ($data->items as $itemData) {
                if ($itemData->receivedBaseQuantity <= 0) {
                    continue; // Skip items with 0 quantity
                }

                /** @var PurchaseInvoiceItem|null $invoiceItem */
                $invoiceItem = $invoiceItemsById->get($itemData->purchaseInvoiceItemId);
                if (! $invoiceItem) {
                    throw new DomainException(sprintf(
                        'البند رقم %d غير موجود في فاتورة الشراء الأصلية.',
                        $itemData->purchaseInvoiceItemId
                    ));
                }

                if ($invoiceItem->product_variant_id !== $itemData->productVariantId) {
                    throw new DomainException('صنف البند لا يتطابق مع الصنف المحدد في فاتورة الشراء.');
                }

                // Check remaining quantity
                $remaining = $invoiceItem->remaining_receivable_quantity;
                if ($itemData->receivedBaseQuantity > $remaining) {
                    throw new DomainException(sprintf(
                        'الكمية المراد استلامها (%s) تتجاوز الكمية المتبقية للاستلام (%s) للصنف: %s.',
                        $itemData->receivedBaseQuantity,
                        $remaining,
                        $invoiceItem->productVariant->sku ?? ''
                    ));
                }

                $exchangeRate = (float) $invoice->exchange_rate;
                $unitCost = (float) $invoiceItem->unit_cost;
                $baseCurrencyCost = $unitCost * $exchangeRate;

                PurchaseReceiptItem::query()->create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_invoice_item_id' => $invoiceItem->id,
                    'product_variant_id' => $invoiceItem->product_variant_id,
                    'received_base_quantity' => $itemData->receivedBaseQuantity,
                    'unit_cost_snapshot' => $unitCost,
                    'currency_id' => $invoice->currency_id,
                    'exchange_rate_snapshot' => $exchangeRate,
                    'base_currency_cost' => $baseCurrencyCost,
                    'notes' => $itemData->notes,
                ]);
            }

            if ($receipt->items()->count() === 0) {
                throw new DomainException('يجب تحديد كمية استلام أكبر من الصفر لبند واحد على الأقل.');
            }

            $this->auditLogger->log(
                action: 'purchase_receipt.created',
                actor: auth()->user(),
                target: $receipt,
                payload: ['receipt_number' => $receipt->receipt_number, 'invoice_id' => $invoice->id]
            );

            return $receipt->load(['items.productVariant', 'warehouse', 'purchaseInvoice']);
        });
    }
}
