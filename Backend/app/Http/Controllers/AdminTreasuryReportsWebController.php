<?php

namespace App\Http\Controllers;

use App\Enums\JournalEntryStatus;
use App\Enums\TreasuryTransactionStatus;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryBalanceService;
use App\Support\DateFilterNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AdminTreasuryReportsWebController extends Controller
{
    public function __construct(
        private readonly TreasuryBalanceService $balanceService,
    ) {}

    public function treasuryLedger(Request $request): View
    {
        $this->authorizeAnyPermission([
            'admin.treasury_reports.ledger',
            'admin.reports.treasury.view',
        ]);

        $accountType = $request->input('account_type', Cashbox::class);
        $accountId = $request->input('account_id');

        $cashboxes = Cashbox::where('is_active', true)->with('currencyAccounts.currency')->get();
        $bankAccounts = BankAccount::where('is_active', true)->with('currencyAccounts.currency')->get();
        $wallets = ElectronicWalletAccount::where('is_active', true)->with('currencyAccounts.currency')->get();

        $selectedAccount = null;
        $selectedCurrencyAccount = null;
        $availableCurrencies = collect();
        $movements = collect();
        $currentBalance = '0.0000';

        if ($accountType && $accountId && class_exists($accountType)) {
            $selectedAccount = $accountType::with([
                'currency',
                'glAccount',
                'currencyAccounts.currency',
                'currencyAccounts.glAccount',
            ])->find($accountId);

            if ($selectedAccount) {
                $availableCurrencies = $selectedAccount->currencyAccounts
                    ->map(fn ($ca) => $ca->currency)
                    ->filter()
                    ->unique('id');

                $requestedCurrencyId = $request->filled('currency_id') ? (int) $request->input('currency_id') : null;

                $selectedCurrencyAccount = $selectedAccount->currencyAccounts
                    ->first(fn ($ca) => $requestedCurrencyId ? (int) $ca->currency_id === $requestedCurrencyId : true);

                if ($selectedCurrencyAccount) {
                    $glAccountId = $selectedCurrencyAccount->gl_account_id;
                    $currencyId = $selectedCurrencyAccount->currency_id;
                } else {
                    $glAccountId = $selectedAccount->gl_account_id;
                    $currencyId = $selectedAccount->currency_id;
                }

                if ($glAccountId && $currencyId) {
                    $query = DB::table('journal_entry_lines')
                        ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                        ->leftJoin('treasury_transactions', 'treasury_transactions.journal_entry_id', '=', 'journal_entries.id')
                        ->where('journal_entry_lines.account_id', $glAccountId)
                        ->where('journal_entry_lines.transaction_currency_id', $currencyId)
                        ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value]);

                    if ($selectedCurrencyAccount) {
                        $tcaId = (int) $selectedCurrencyAccount->id;
                        $hasStamped = DB::table('journal_entry_lines')
                            ->where('treasury_currency_account_id', $tcaId)
                            ->exists();
                        if ($hasStamped) {
                            $query->where('journal_entry_lines.treasury_currency_account_id', $tcaId);
                        } else {
                            $query->whereNull('journal_entry_lines.treasury_currency_account_id');
                        }
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

                    // Calculate running balance for this single currency account
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

                    $currentBalance = $selectedCurrencyAccount
                        ? $this->balanceService->getAuthoritativeBalance($selectedCurrencyAccount)
                        : $this->balanceService->getAuthoritativeBalance($selectedAccount);
                }
            }
        }

        return view('admin.finance.treasury.reports.treasury_ledger', [
            'cashboxes' => $cashboxes,
            'bankAccounts' => $bankAccounts,
            'wallets' => $wallets,
            'selectedAccount' => $selectedAccount,
            'selectedCurrencyAccount' => $selectedCurrencyAccount,
            'availableCurrencies' => $availableCurrencies,
            'selectedCurrencyId' => $selectedCurrencyAccount ? $selectedCurrencyAccount->currency_id : ($selectedAccount?->currency_id),
            'movements' => $movements,
            'currentBalance' => $currentBalance,
        ]);
    }

    public function treasuryReconciliation(Request $request): View
    {
        $this->authorizeAnyPermission([
            'admin.treasury_reports.reconciliation',
            'admin.reports.treasury.view',
        ]);

        $accountTypeFilter = $request->input('account_type', 'all');
        $allowedTypes = [
            'all' => null,
            'cashbox' => Cashbox::class,
            'bank_account' => BankAccount::class,
            'electronic_wallet' => ElectronicWalletAccount::class,
        ];
        $filterClass = $allowedTypes[$accountTypeFilter] ?? null;

        $currencyAccounts = TreasuryCurrencyAccount::with([
            'accountable',
            'currency',
            'glAccount',
        ])->when($filterClass !== null, function ($q) use ($filterClass): void {
            $q->where('accountable_type', $filterClass);
        })->get();

        $rows = [];

        foreach ($currencyAccounts as $acc) {
            $parent = $acc->accountable;
            if (! $parent instanceof Cashbox && ! $parent instanceof BankAccount && ! $parent instanceof ElectronicWalletAccount) {
                continue;
            }

            $glBalance = $this->balanceService->getAuthoritativeBalance($acc);
            $baseBalance = $this->balanceService->getAuthoritativeBaseBalance($acc);
            $glAccount = $acc->glAccount;

            $postedTrxCount = TreasuryTransaction::where(function ($q) use ($acc, $parent): void {
                $q->where('treasury_currency_account_id', $acc->id)
                    ->orWhere(function ($q2) use ($acc, $parent): void {
                        $q2->where('account_type', get_class($parent))
                            ->where('account_id', $parent->id)
                            ->where('currency_id', $acc->currency_id);
                    });
            })
                ->where('status', TreasuryTransactionStatus::Posted)
                ->count();

            $orphanCount = TreasuryTransaction::where(function ($q) use ($acc, $parent): void {
                $q->where('treasury_currency_account_id', $acc->id)
                    ->orWhere(function ($q2) use ($acc, $parent): void {
                        $q2->where('account_type', get_class($parent))
                            ->where('account_id', $parent->id)
                            ->where('currency_id', $acc->currency_id);
                    });
            })
                ->where('status', TreasuryTransactionStatus::Posted)
                ->whereNull('journal_entry_id')
                ->count();

            $isConsistent = ($glAccount !== null && $orphanCount === 0);

            $rows[] = [
                'type_name' => match (get_class($parent)) {
                    Cashbox::class => 'صندوق نقدي',
                    BankAccount::class => 'حساب بنكي',
                    ElectronicWalletAccount::class => 'محفظة إلكترونية',
                    default => 'خزينة',
                },
                'account_type' => get_class($parent),
                'code' => $parent->code,
                'name' => $acc->displayName,
                'currency' => $acc->currency->code ?? '-',
                'gl_code' => $glAccount->code ?? 'غير مربوط',
                'gl_name' => $glAccount->name_ar ?? 'لا يوجد',
                'gl_balance' => $glBalance,
                'base_balance' => $baseBalance,
                'posted_trx_count' => $postedTrxCount,
                'orphan_count' => $orphanCount,
                'is_consistent' => $isConsistent,
                'is_active' => $acc->is_active && $parent->is_active,
            ];
        }

        return view('admin.finance.treasury.reports.treasury_reconciliation', [
            'reconciliationRows' => $rows,
            'accountTypeFilter' => $accountTypeFilter,
        ]);
    }

    /** @param  list<string>  $permissions */
    private function authorizeAnyPermission(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (auth()->user()?->hasPermission($permission)) {
                return;
            }
        }

        abort(403, 'غير مصرح لك بعرض هذا التقرير.');
    }
}
