<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Enums\SupplierLedgerDirection;
use App\Models\AccountingMapping;
use App\Models\SupplierLedgerEntry;
use Illuminate\Support\Facades\DB;

final class SupplierLedgerReconciliationService
{
    /**
     * @return array{
     *     supplier_subledger_total_base: string,
     *     gl_accounts_payable_total_base: string,
     *     difference: string,
     *     is_matched: bool
     * }
     */
    public function reconcile(): array
    {
        // 1. Supplier subledger net credit balance in base currency
        $subledgerCredit = (string) SupplierLedgerEntry::where('direction', SupplierLedgerDirection::Credit)->sum('base_currency_amount');
        $subledgerDebit = (string) SupplierLedgerEntry::where('direction', SupplierLedgerDirection::Debit)->sum('base_currency_amount');
        $subledgerNet = bcsub($subledgerCredit, $subledgerDebit, 4);

        // 2. GL Accounts Payable balance from mapping
        $mapping = AccountingMapping::where('event_key', 'PURCHASE_INVOICE_POSTED')->first();
        $glNet = '0';

        if ($mapping && $mapping->credit_account_id) {
            $glTotals = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entry_lines.account_id', $mapping->credit_account_id)
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_cr, COALESCE(SUM(journal_entry_lines.debit), 0) as total_deb')
                ->first();

            $cr = (string) ($glTotals->total_cr ?? '0');
            $deb = (string) ($glTotals->total_deb ?? '0');
            $glNet = bcsub($cr, $deb, 4);
        }

        $difference = bcsub($subledgerNet, $glNet, 4);
        $isMatched = bccomp($difference, '0', 4) === 0;

        return [
            'supplier_subledger_total_base' => $subledgerNet,
            'gl_accounts_payable_total_base' => $glNet,
            'difference' => $difference,
            'is_matched' => $isMatched,
        ];
    }
}
