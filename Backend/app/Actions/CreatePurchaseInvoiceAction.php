<?php

namespace App\Actions;

use App\Data\PurchaseInvoiceData;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\Currency;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use App\Services\PurchaseCalculationService;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseInvoiceAction
{
    public function __construct(
        private readonly PurchaseCalculationService $calculationService,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseInvoiceData $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($data): PurchaseInvoice {
            $invoiceNumber = $data->invoiceNumber;
            if ($invoiceNumber === null || trim($invoiceNumber) === '') {
                $year = date('Y', strtotime($data->invoiceDate));
                $count = PurchaseInvoice::query()->whereYear('invoice_date', $year)->count();
                $invoiceNumber = sprintf('PI-%s-%05d', $year, $count + 1);
            }

            // Resolve exchange rate snapshot to base currency
            $invoiceCurrency = Currency::query()->findOrFail($data->currencyId);
            $baseCurrency = $this->currencyConverter->getBaseCurrency();

            $exchangeRate = 1.0;
            if ($invoiceCurrency->id !== $baseCurrency->id) {
                $rateInfo = $this->currencyConverter->resolveRate($invoiceCurrency, $baseCurrency);
                if ($rateInfo !== null) {
                    $exchangeRate = (float) $rateInfo['rate'];
                }
            }

            $calculation = $this->calculationService->calculateInvoice($data);

            $invoice = PurchaseInvoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'supplier_id' => $data->supplierId,
                'warehouse_id' => $data->warehouseId,
                'currency_id' => $data->currencyId,
                'supplier_invoice_number' => $data->supplierInvoiceNumber,
                'invoice_date' => $data->invoiceDate,
                'status' => PurchaseInvoiceStatus::Draft,
                'exchange_rate' => $exchangeRate,
                'subtotal' => $calculation['subtotal'],
                'discount_amount' => $calculation['discount_amount'],
                'other_costs' => $calculation['other_costs'],
                'total' => $calculation['total'],
                'notes' => $data->notes,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($calculation['calculated_items'] as $item) {
                PurchaseInvoiceItem::query()->create([
                    'purchase_invoice_id' => $invoice->id,
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

            $this->auditLogger->record('admin.purchase_invoice.created', $invoice);

            return $invoice->load(['supplier', 'warehouse', 'currency', 'items.productVariant.product', 'items.unit']);
        });
    }
}
