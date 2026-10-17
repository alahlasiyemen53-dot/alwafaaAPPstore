<?php

namespace App\Actions;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\AccountingMapping;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class BuildJournalFromPurchaseInvoiceAction
{
    public function __construct(
        private readonly GenerateJournalEntryNumberAction $numberGenerator,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseInvoice $invoice): ?JournalEntry
    {
        return DB::transaction(function () use ($invoice): ?JournalEntry {
            // 1. Check idempotency: if already created, return existing
            $existing = JournalEntry::query()
                ->where('source_type', PurchaseInvoice::class)
                ->where('source_id', $invoice->id)
                ->where('source_event', 'POSTED')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // 2. Check accounting mapping
            $mapping = AccountingMapping::query()
                ->with(['debitAccount', 'creditAccount'])
                ->where('event_key', 'PURCHASE_INVOICE_POSTED')
                ->where('is_active', true)
                ->first();

            if ($mapping === null || ! $mapping->debitAccount || ! $mapping->creditAccount) {
                return null;
            }

            // 3. Find suitable open accounting period
            $invoiceDate = Carbon::parse($invoice->invoice_date ?? now());
            $period = AccountingPeriod::query()
                ->where('status', AccountingPeriodStatus::Open)
                ->where('starts_at', '<=', $invoiceDate->toDateString())
                ->where('ends_at', '>=', $invoiceDate->toDateString())
                ->first();

            if ($period === null) {
                // Fallback to any open period
                $period = AccountingPeriod::query()
                    ->where('status', AccountingPeriodStatus::Open)
                    ->orderBy('starts_at')
                    ->first();
            }

            if ($period === null) {
                // No open period available, cannot post
                return null;
            }

            $baseCurrency = $this->currencyConverter->getBaseCurrency();
            $currency = $invoice->currency ?? Currency::findOrFail($invoice->currency_id);
            $exchangeRate = (string) ($invoice->exchange_rate ?? '1.00000000');
            $transactionAmount = (string) $invoice->total;

            if ($currency->id === $baseCurrency->id) {
                $baseAmount = number_format((float) $transactionAmount, 4, '.', '');
                $exchangeRate = '1.00000000';
            } else {
                $baseAmount = number_format((float) bcmul($transactionAmount, $exchangeRate, 4), 4, '.', '');
            }

            $entryNumber = $this->numberGenerator->execute($invoiceDate);
            $userId = auth()->id() > 0 ? auth()->id() : ($invoice->posted_by ?? null);

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'accounting_period_id' => $period->id,
                'entry_date' => $invoiceDate->toDateString(),
                'status' => JournalEntryStatus::Posted,
                'description' => 'قيد استحقاق فاتورة شراء رقم '.$invoice->invoice_number.' - مورد: '.$invoice->supplier->name,
                'source_type' => PurchaseInvoice::class,
                'source_id' => $invoice->id,
                'source_event' => 'POSTED',
                'currency_id' => $currency->id,
                'exchange_rate_snapshot' => $exchangeRate,
                'total_debit' => $baseAmount,
                'total_credit' => $baseAmount,
                'posted_at' => now(),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Line 1: Debit (Inventory / Purchases)
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $mapping->debit_account_id,
                'description' => 'مشتريات فاتورة '.$invoice->invoice_number,
                'debit' => $baseAmount,
                'credit' => '0.0000',
                'transaction_currency_id' => $currency->id,
                'transaction_amount' => $transactionAmount,
                'exchange_rate_snapshot' => $exchangeRate,
                'base_currency_amount' => $baseAmount,
                'party_type' => Supplier::class,
                'party_id' => $invoice->supplier_id,
                'line_order' => 1,
            ]);

            // Line 2: Credit (Supplier Payable)
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $mapping->credit_account_id,
                'description' => 'استحقاق المورد عن فاتورة '.$invoice->invoice_number,
                'debit' => '0.0000',
                'credit' => $baseAmount,
                'transaction_currency_id' => $currency->id,
                'transaction_amount' => $transactionAmount,
                'exchange_rate_snapshot' => $exchangeRate,
                'base_currency_amount' => $baseAmount,
                'party_type' => Supplier::class,
                'party_id' => $invoice->supplier_id,
                'line_order' => 2,
            ]);

            $this->auditLogger->record('admin.journal_entry.source_posted', $entry, metadata: [
                'source' => 'purchase_invoice',
                'invoice_number' => $invoice->invoice_number,
                'total' => $baseAmount,
            ]);

            return $entry->fresh(['lines.account', 'currency', 'accountingPeriod']);
        });
    }
}
