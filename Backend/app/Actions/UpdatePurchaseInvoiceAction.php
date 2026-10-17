<?php

namespace App\Actions;

use App\Data\PurchaseInvoiceData;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierLedgerDirection;
use App\Models\Currency;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SupplierLedgerEntry;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use App\Services\PurchaseCalculationService;
use Illuminate\Support\Facades\DB;

final class UpdatePurchaseInvoiceAction
{
    public function __construct(
        private readonly PurchaseCalculationService $calculationService,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseInvoice $invoice, PurchaseInvoiceData $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $data): PurchaseInvoice {
            /** @var PurchaseInvoice $lockedInvoice */
            $lockedInvoice = PurchaseInvoice::query()
                ->lockForUpdate()
                ->with(['items', 'ledgerEntry'])
                ->findOrFail($invoice->id);

            $oldTotal = $lockedInvoice->total;
            $oldStatus = $lockedInvoice->status;

            // Resolve exchange rate snapshot if currency changed
            $exchangeRate = (float) $lockedInvoice->exchange_rate;
            if ($data->currencyId !== $lockedInvoice->currency_id) {
                $invoiceCurrency = Currency::query()->findOrFail($data->currencyId);
                $baseCurrency = $this->currencyConverter->getBaseCurrency();
                if ($invoiceCurrency->id !== $baseCurrency->id) {
                    $rateInfo = $this->currencyConverter->resolveRate($invoiceCurrency, $baseCurrency);
                    $exchangeRate = $rateInfo !== null ? (float) $rateInfo['rate'] : 1.0;
                } else {
                    $exchangeRate = 1.0;
                }
            }

            $calculation = $this->calculationService->calculateInvoice($data);

            // Re-sync invoice items
            $lockedInvoice->items()->delete();
            foreach ($calculation['calculated_items'] as $item) {
                PurchaseInvoiceItem::query()->create([
                    'purchase_invoice_id' => $lockedInvoice->id,
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'base_quantity' => $item['base_quantity'],
                    'free_quantity' => $item['free_quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'line_subtotal' => $item['line_subtotal'],
                    'discount_amount' => $item['discount_amount'],
                    'line_total' => $item['line_total'],
                ]);
            }

            $lockedInvoice->update([
                'invoice_number' => $data->invoiceNumber ?? $lockedInvoice->invoice_number,
                'supplier_id' => $data->supplierId,
                'warehouse_id' => $data->warehouseId,
                'currency_id' => $data->currencyId,
                'supplier_invoice_number' => $data->supplierInvoiceNumber,
                'invoice_date' => $data->invoiceDate,
                'exchange_rate' => $exchangeRate,
                'subtotal' => $calculation['subtotal'],
                'discount_amount' => $calculation['discount_amount'],
                'other_costs' => $calculation['other_costs'],
                'total' => $calculation['total'],
                'notes' => $data->notes,
                'updated_by' => auth()->id(),
            ]);

            // Synchronize Supplier Ledger Entry if invoice is already posted
            if ($lockedInvoice->status === PurchaseInvoiceStatus::Posted) {
                $baseCurrencyAmount = round($calculation['total'] * $exchangeRate, 4);

                SupplierLedgerEntry::updateOrCreate(
                    [
                        'reference_type' => PurchaseInvoice::class,
                        'reference_id' => $lockedInvoice->id,
                        'entry_type' => 'PURCHASE_INVOICE',
                    ],
                    [
                        'supplier_id' => $data->supplierId,
                        'currency_id' => $data->currencyId,
                        'amount' => $calculation['total'],
                        'direction' => SupplierLedgerDirection::Credit,
                        'exchange_rate_snapshot' => $exchangeRate,
                        'base_currency_amount' => $baseCurrencyAmount,
                        'description' => 'تعديل فاتورة شراء رقم '.$lockedInvoice->invoice_number,
                        'posted_at' => $lockedInvoice->posted_at ?? now(),
                        'created_by' => $lockedInvoice->posted_by ?? auth()->id(),
                    ]
                );
            }

            $this->auditLogger->record('admin.purchase_invoice.updated', $lockedInvoice, metadata: [
                'old_total' => $oldTotal,
                'new_total' => $calculation['total'],
                'was_posted' => $oldStatus === PurchaseInvoiceStatus::Posted,
            ]);

            return $lockedInvoice->fresh(['supplier', 'warehouse', 'currency', 'items.productVariant.product', 'items.unit', 'ledgerEntry']);
        });
    }
}
