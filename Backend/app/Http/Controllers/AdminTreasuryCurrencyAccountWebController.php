<?php

namespace App\Http\Controllers;

use App\Actions\CreateTreasuryCurrencyAccountAction;
use App\Actions\ToggleTreasuryCurrencyAccountStatusAction;
use App\Enums\JournalEntryStatus;
use App\Http\Requests\StoreTreasuryCurrencyAccountRequest;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use App\Services\TreasuryBalanceService;
use App\Support\DateFilterNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AdminTreasuryCurrencyAccountWebController extends Controller
{
    public function __construct(
        private readonly TreasuryBalanceService $balanceService,
    ) {}

    public function store(
        StoreTreasuryCurrencyAccountRequest $request,
        CreateTreasuryCurrencyAccountAction $action
    ): RedirectResponse {
        $currencyAccount = $action->execute($request->toDto(), (int) auth()->id());

        return back()->with('success', "تم فتح حساب عملة ({$currencyAccount->currency->code}) لـ {$currencyAccount->displayName} بنجاح.");
    }

    public function toggleStatus(
        TreasuryCurrencyAccount $currencyAccount,
        ToggleTreasuryCurrencyAccountStatusAction $action
    ): RedirectResponse {
        $this->authorizeCurrencyAccountMutation($currencyAccount);

        $action->execute($currencyAccount, (int) auth()->id());

        $statusStr = $currencyAccount->is_active ? 'تفعيل' : 'تعطيل';

        return back()->with('success', "تم {$statusStr} حساب العملة ({$currencyAccount->currency->code}) بنجاح.");
    }

    public function statement(Request $request, TreasuryCurrencyAccount $currencyAccount): View
    {
        if (! auth()->user()?->hasPermission('admin.treasury_reports.ledger')
            && ! auth()->user()?->hasPermission('admin.reports.treasury.view')) {
            abort(403, 'غير مصرح لك بالوصول إلى هذه الصفحة.');
        }

        $currencyAccount->load(['accountable', 'currency', 'glAccount']);
        $glAccountId = $currencyAccount->gl_account_id;

        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('treasury_transactions', 'treasury_transactions.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $glAccountId)
            ->where('journal_entry_lines.transaction_currency_id', $currencyAccount->currency_id)
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value]);

        $tcaId = (int) $currencyAccount->id;
        $hasStamped = DB::table('journal_entry_lines')
            ->where('treasury_currency_account_id', $tcaId)
            ->exists();
        if ($hasStamped) {
            $query->where('journal_entry_lines.treasury_currency_account_id', $tcaId);
        } else {
            $query->whereNull('journal_entry_lines.treasury_currency_account_id');
        }

        if ($request->filled('date_from')) {
            $normDateFrom = DateFilterNormalizer::normalize($request->input('date_from'));
            if ($normDateFrom) {
                $query->where('journal_entries.entry_date', '>=', $normDateFrom);
            }
        }

        if ($request->filled('date_to')) {
            $normDateTo = DateFilterNormalizer::normalize($request->input('date_to'));
            if ($normDateTo) {
                $query->where('journal_entries.entry_date', '<=', $normDateTo);
            }
        }

        $records = $query->select([
            'journal_entries.id as journal_id',
            'journal_entries.entry_number',
            'journal_entries.entry_date',
            'journal_entries.description as journal_description',
            'journal_entry_lines.description as line_description',
            'journal_entry_lines.debit',
            'journal_entry_lines.credit',
            'journal_entry_lines.transaction_amount',
            'treasury_transactions.transaction_number',
            'treasury_transactions.transaction_type',
            'treasury_transactions.status as trx_status',
        ])
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->get();

        // Calculate single-currency running balance (Sprint 21.3 § 26)
        $running = '0.0000';
        $movements = $records->map(function ($row) use (&$running) {
            $debit = (string) $row->debit;
            $credit = (string) $row->credit;
            $change = bcsub($debit, $credit, 4);
            $running = bcadd($running, $change, 4);

            $row->incoming = $debit;
            $row->outgoing = $credit;
            $row->running_balance = $running;

            return $row;
        });

        $currentBalance = $this->balanceService->getAuthoritativeBalance($currencyAccount);

        return view('admin.finance.treasury.reports.currency_account_statement', [
            'currencyAccount' => $currencyAccount,
            'movements' => $movements,
            'currentBalance' => $currentBalance,
            'dateFrom' => $request->input('date_from'),
            'dateTo' => $request->input('date_to'),
        ]);
    }

    private function authorizeCurrencyAccountMutation(TreasuryCurrencyAccount $currencyAccount): void
    {
        $permission = match ($currencyAccount->accountable_type) {
            Cashbox::class => 'admin.cashboxes.update',
            BankAccount::class => 'admin.bank_accounts.update',
            ElectronicWalletAccount::class => 'admin.electronic_wallets.update',
            default => null,
        };

        if ($permission === null || ! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بتعديل حساب العملة هذا.');
        }
    }
}
