<?php

namespace App\Queries;

use App\Enums\AccountNormalBalance;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntryLine;
use App\Support\DateFilterNormalizer;
use Illuminate\Support\Facades\DB;

final class GeneralLedgerQuery
{
    /**
     * @return array{
     *     account: Account,
     *     opening_balance: string,
     *     rows: array<int, array<string, mixed>>,
     *     total_debit: string,
     *     total_credit: string,
     *     closing_balance: string,
     *     start_date: ?string,
     *     end_date: ?string
     * }
     */
    public function execute(int $accountId, ?int $periodId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $account = Account::findOrFail($accountId);

        $startDate = DateFilterNormalizer::normalize($startDate);
        $endDate = DateFilterNormalizer::normalize($endDate);

        if ($periodId !== null) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $startDate = $startDate ?? $period->starts_at->toDateString();
                $endDate = $endDate ?? $period->ends_at->toDateString();
            }
        }

        // 1. Calculate opening balance before start date (POSTED only)
        $openingBalance = '0';
        if ($startDate !== null) {
            $openingTotals = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entry_lines.account_id', $account->id)
                ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value])
                ->where('journal_entries.entry_date', '<', $startDate)
                ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_deb, COALESCE(SUM(journal_entry_lines.credit), 0) as total_cr')
                ->first();

            $deb = (string) ($openingTotals->total_deb ?? '0');
            $cr = (string) ($openingTotals->total_cr ?? '0');

            if ($account->normal_balance === AccountNormalBalance::Debit) {
                $openingBalance = bcsub($deb, $cr, 4);
            } else {
                $openingBalance = bcsub($cr, $deb, 4);
            }
        }

        // 2. Fetch period journal lines (POSTED only)
        $linesQuery = JournalEntryLine::query()
            ->with(['journalEntry.currency', 'transactionCurrency', 'party'])
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate): void {
                $q->whereIn('status', [JournalEntryStatus::Posted, JournalEntryStatus::Reversed]);
                if ($startDate !== null) {
                    $q->where('entry_date', '>=', $startDate);
                }
                if ($endDate !== null) {
                    $q->where('entry_date', '<=', $endDate);
                }
            })
            ->where('account_id', $account->id)
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_entry_lines.line_order')
            ->select('journal_entry_lines.*');

        $lines = $linesQuery->get();

        $rows = [];
        $runningBalance = $openingBalance;
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($lines as $line) {
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;

            $totalDebit = bcadd($totalDebit, $debit, 4);
            $totalCredit = bcadd($totalCredit, $credit, 4);

            if ($account->normal_balance === AccountNormalBalance::Debit) {
                $runningBalance = bcadd($runningBalance, bcsub($debit, $credit, 4), 4);
            } else {
                $runningBalance = bcadd($runningBalance, bcsub($credit, $debit, 4), 4);
            }

            $rows[] = [
                'line' => $line,
                'entry' => $line->journalEntry,
                'entry_date' => $line->journalEntry?->entry_date?->toDateString(),
                'entry_number' => $line->journalEntry?->entry_number,
                'description' => $line->description ?? $line->journalEntry?->description,
                'source_type' => $line->journalEntry?->source_type,
                'source_id' => $line->journalEntry?->source_id,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance,
            ];
        }

        return [
            'account' => $account,
            'opening_balance' => $openingBalance,
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => $runningBalance,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
