<?php

namespace App\Queries;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Support\DateFilterNormalizer;
use Illuminate\Support\Facades\DB;

final class TrialBalanceQuery
{
    /**
     * @return array{
     *     accounts: array<int, array<string, mixed>>,
     *     total_opening_debit: string,
     *     total_opening_credit: string,
     *     total_period_debit: string,
     *     total_period_credit: string,
     *     total_closing_debit: string,
     *     total_closing_credit: string,
     *     is_balanced: bool,
     *     difference: string,
     *     filter_period: ?AccountingPeriod,
     *     start_date: ?string,
     *     end_date: ?string
     * }
     */
    public function execute(?int $periodId = null, ?string $startDate = null, ?string $endDate = null, ?string $accountType = null): array
    {
        $startDate = DateFilterNormalizer::normalize($startDate);
        $endDate = DateFilterNormalizer::normalize($endDate);

        $period = null;
        if ($periodId !== null) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $startDate = $startDate ?? $period->starts_at->toDateString();
                $endDate = $endDate ?? $period->ends_at->toDateString();
            }
        }

        $accountsQuery = Account::query()->orderBy('code');
        if (! empty($accountType)) {
            $accountsQuery->where('type', $accountType);
        }

        $accounts = $accountsQuery->get();

        // Batch both opening and period totals for ALL accounts in 2 grouped
        // queries total (instead of 2 queries per account) to avoid N+1.
        $openingByAccount = [];
        if ($startDate !== null) {
            $openingByAccount = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value])
                ->where('journal_entries.entry_date', '<', $startDate)
                ->groupBy('journal_entry_lines.account_id')
                ->selectRaw('journal_entry_lines.account_id as account_id, COALESCE(SUM(journal_entry_lines.debit), 0) as deb, COALESCE(SUM(journal_entry_lines.credit), 0) as cr')
                ->get()
                ->keyBy('account_id');
        }

        $periodQuery = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value]);

        if ($startDate !== null) {
            $periodQuery->where('journal_entries.entry_date', '>=', $startDate);
        }
        if ($endDate !== null) {
            $periodQuery->where('journal_entries.entry_date', '<=', $endDate);
        }

        $periodByAccount = $periodQuery
            ->groupBy('journal_entry_lines.account_id')
            ->selectRaw('journal_entry_lines.account_id as account_id, COALESCE(SUM(journal_entry_lines.debit), 0) as deb, COALESCE(SUM(journal_entry_lines.credit), 0) as cr')
            ->get()
            ->keyBy('account_id');

        $accountRows = [];
        $totalOpeningDebit = '0';
        $totalOpeningCredit = '0';
        $totalPeriodDebit = '0';
        $totalPeriodCredit = '0';
        $totalClosingDebit = '0';
        $totalClosingCredit = '0';

        foreach ($accounts as $account) {
            $openingTotals = $openingByAccount[$account->id] ?? null;
            $openingDebit = (string) ($openingTotals->deb ?? '0');
            $openingCredit = (string) ($openingTotals->cr ?? '0');

            $periodTotals = $periodByAccount[$account->id] ?? null;
            $periodDebit = (string) ($periodTotals->deb ?? '0');
            $periodCredit = (string) ($periodTotals->cr ?? '0');

            // 3. Net Closing for this account
            $allDebit = bcadd($openingDebit, $periodDebit, 4);
            $allCredit = bcadd($openingCredit, $periodCredit, 4);

            $closingDebit = '0';
            $closingCredit = '0';
            $netDiff = bcsub($allDebit, $allCredit, 4);

            if (bccomp($netDiff, '0', 4) > 0) {
                $closingDebit = $netDiff;
            } elseif (bccomp($netDiff, '0', 4) < 0) {
                $closingCredit = bcmul($netDiff, '-1', 4);
            }

            // Opening Net presentation
            $openingNet = bcsub($openingDebit, $openingCredit, 4);
            $netOpenDebit = '0';
            $netOpenCredit = '0';
            if (bccomp($openingNet, '0', 4) > 0) {
                $netOpenDebit = $openingNet;
            } elseif (bccomp($openingNet, '0', 4) < 0) {
                $netOpenCredit = bcmul($openingNet, '-1', 4);
            }

            // Only skip row if zero everywhere and not active
            $hasActivity = bccomp($allDebit, '0', 4) > 0 || bccomp($allCredit, '0', 4) > 0;

            if ($hasActivity || $account->is_postable) {
                $accountRows[] = [
                    'account' => $account,
                    'opening_debit' => $netOpenDebit,
                    'opening_credit' => $netOpenCredit,
                    'period_debit' => $periodDebit,
                    'period_credit' => $periodCredit,
                    'closing_debit' => $closingDebit,
                    'closing_credit' => $closingCredit,
                ];

                $totalOpeningDebit = bcadd($totalOpeningDebit, $netOpenDebit, 4);
                $totalOpeningCredit = bcadd($totalOpeningCredit, $netOpenCredit, 4);
                $totalPeriodDebit = bcadd($totalPeriodDebit, $periodDebit, 4);
                $totalPeriodCredit = bcadd($totalPeriodCredit, $periodCredit, 4);
                $totalClosingDebit = bcadd($totalClosingDebit, $closingDebit, 4);
                $totalClosingCredit = bcadd($totalClosingCredit, $closingCredit, 4);
            }
        }

        $difference = bcsub($totalClosingDebit, $totalClosingCredit, 4);
        $isBalanced = bccomp($difference, '0', 4) === 0;

        return [
            'accounts' => $accountRows,
            'total_opening_debit' => $totalOpeningDebit,
            'total_opening_credit' => $totalOpeningCredit,
            'total_period_debit' => $totalPeriodDebit,
            'total_period_credit' => $totalPeriodCredit,
            'total_closing_debit' => $totalClosingDebit,
            'total_closing_credit' => $totalClosingCredit,
            'is_balanced' => $isBalanced,
            'difference' => $difference,
            'filter_period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
