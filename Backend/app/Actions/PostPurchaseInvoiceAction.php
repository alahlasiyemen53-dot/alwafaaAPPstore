<?php

namespace App\Actions;

use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierLedgerDirection;
use App\Models\Currency;
use App\Models\PurchaseInvoice;
use App\Models\SupplierLedgerEntry;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PostPurchaseInvoiceAction
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
        private readonly ?BuildJournalFromPurchaseInvoiceAction $buildJournalAction = null,
    ) {}

    public function execute(PurchaseInvoice $invoice): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice): PurchaseInvoice {
            /** @var PurchaseInvoice $lockedInvoice */
            $lockedInvoice = PurchaseInvoice::query()
                ->lockForUpdate()
                ->with(['items', 'supplier', 'currency'])
                ->findOrFail($invoice->id);

            if ($lockedInvoice->status !== PurchaseInvoiceStatus::Draft) {
                throw new ConflictHttpException('لا يمكن ترحيل فاتورة ليست بحالة مسودة.');
            }

            if ($lockedInvoice->items->isEmpty()) {
                throw new UnprocessableEntityHttpException('لا يمكن ترحيل فاتورة شراء بدون بنود أو أصناف.');
            }

            if (! $lockedInvoice->supplier?->is_active) {
                throw new UnprocessableEntityHttpException('لا يمكن ترحيل فاتورة لمورد غير نشط أو معطل.');
            }

            // Snapshot fresh exchange rate if non-base currency
            $baseCurrency = $this->currencyConverter->getBaseCurrency();
            $exchangeRate = (float) $lockedInvoice->exchange_rate;
            if ($lockedInvoice->currency_id !== $baseCurrency->id) {
                $rateInfo = $this->currencyConverter->resolveRate($lockedInvoice->currency, $baseCurrency);
                if ($rateInfo !== null) {
                    $exchangeRate = (float) $rateInfo['rate'];
                }
            }

            $baseCurrencyAmount = round($lockedInvoice->total * $exchangeRate, 4);

            $postedAt = now();
            $postedBy = auth()->id();

            $lockedInvoice->update([
                'status' => PurchaseInvoiceStatus::Posted,
                'exchange_rate' => $exchangeRate,
                'posted_at' => $postedAt,
                'posted_by' => $postedBy,
                'updated_by' => $postedBy,
            ]);

            // Create or update Supplier Ledger Entry atomically
            SupplierLedgerEntry::updateOrCreate(
                [
                    'reference_type' => PurchaseInvoice::class,
                    'reference_id' => $lockedInvoice->id,
                    'entry_type' => 'PURCHASE_INVOICE',
                ],
                [
                    'supplier_id' => $lockedInvoice->supplier_id,
                    'currency_id' => $lockedInvoice->currency_id,
                    'amount' => $lockedInvoice->total,
                    'direction' => SupplierLedgerDirection::Credit,
                    'exchange_rate_snapshot' => $exchangeRate,
                    'base_currency_amount' => $baseCurrencyAmount,
                    'description' => 'فاتورة شراء رقم '.$lockedInvoice->invoice_number,
                    'posted_at' => $postedAt,
                    'created_by' => $postedBy,
                ]
            );

            // Automatically build and post financial journal entry if mapping and open period exist
            $action = $this->buildJournalAction ?? app(BuildJournalFromPurchaseInvoiceAction::class);
            $action->execute($lockedInvoice);

            $this->auditLogger->record('admin.purchase_invoice.posted', $lockedInvoice, metadata: [
                'total' => $lockedInvoice->total,
                'currency' => $lockedInvoice->currency?->code,
                'exchange_rate' => $exchangeRate,
            ]);

            return $lockedInvoice->fresh(['supplier', 'warehouse', 'currency', 'items.productVariant.product', 'items.unit', 'ledgerEntry']);
        });
    }
}
