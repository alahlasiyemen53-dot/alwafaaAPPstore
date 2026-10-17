<?php

namespace App\Http\Controllers;

use App\Actions\CreateDraftTreasuryTransactionAction;
use App\Actions\PostTreasuryTransactionAction;
use App\Actions\ReverseTreasuryTransactionAction;
use App\Actions\UpdateDraftTreasuryTransactionAction;
use App\Enums\TreasuryTransactionStatus;
use App\Http\Requests\ReverseTreasuryTransactionRequest;
use App\Http\Requests\StoreTreasuryTransactionRequest;
use App\Http\Requests\UpdateTreasuryTransactionRequest;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminTreasuryTransactionWebController extends Controller
{
    public function __construct(
        private readonly CreateDraftTreasuryTransactionAction $createDraftAction,
        private readonly UpdateDraftTreasuryTransactionAction $updateDraftAction,
        private readonly PostTreasuryTransactionAction $postAction,
        private readonly ReverseTreasuryTransactionAction $reverseAction,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('admin.treasury_transactions.view');

        $query = TreasuryTransaction::query()
            ->with(['account', 'currency', 'offsetAccount', 'journalEntry'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->input('currency_id'));
        }

        $transactions = $query->paginate(15)->withQueryString();
        $currencies = Currency::where('is_active', true)->get();

        $metrics = [
            'total' => TreasuryTransaction::count(),
            'draft' => TreasuryTransaction::where('status', TreasuryTransactionStatus::Draft)->count(),
            'posted' => TreasuryTransaction::where('status', TreasuryTransactionStatus::Posted)->count(),
            'reversed' => TreasuryTransaction::where('status', TreasuryTransactionStatus::Reversed)->count(),
        ];

        return view('admin.finance.treasury.transactions.index', [
            'transactions' => $transactions,
            'currencies' => $currencies,
            'metrics' => $metrics,
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('admin.treasury_transactions.create');

        $cashboxes = Cashbox::where('is_active', true)
            ->with(['currency', 'currencyAccounts.currency'])
            ->get();
        $bankAccounts = BankAccount::where('is_active', true)
            ->with(['currency', 'currencyAccounts.currency'])
            ->get();
        $wallets = ElectronicWalletAccount::where('is_active', true)
            ->with(['currency', 'currencyAccounts.currency'])
            ->get();
        $offsetAccounts = Account::where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        return view('admin.finance.treasury.transactions.create', [
            'cashboxes' => $cashboxes,
            'bankAccounts' => $bankAccounts,
            'wallets' => $wallets,
            'offsetAccounts' => $offsetAccounts,
        ]);
    }

    public function store(StoreTreasuryTransactionRequest $request): RedirectResponse
    {
        $transaction = $this->createDraftAction->execute($request->toDto());

        return redirect()
            ->route('admin.finance.treasury.transactions.show', $transaction)
            ->with('success', "تم حفظ مسودة حركة الخزينة رقم '{$transaction->transaction_number}' بنجاح.");
    }

    public function show(TreasuryTransaction $treasuryTransaction): View
    {
        $this->authorizePermission('admin.treasury_transactions.view');

        $treasuryTransaction->load([
            'account.currency',
            'currency',
            'journalEntry.lines.account',
            'reversalJournalEntry.lines.account',
            'createdBy',
            'postedBy',
            'reversedBy',
        ]);

        return view('admin.finance.treasury.transactions.show', [
            'transaction' => $treasuryTransaction,
        ]);
    }

    public function edit(TreasuryTransaction $treasuryTransaction): View
    {
        $this->authorizePermission('admin.treasury_transactions.update');

        if (! $treasuryTransaction->isDraft()) {
            abort(403, 'لا يمكن تعديل حركة خزينة تم ترحيلها أو إلغاؤها.');
        }

        $cashboxes = Cashbox::where('is_active', true)
            ->with(['currency', 'currencyAccounts.currency'])
            ->get();
        $bankAccounts = BankAccount::where('is_active', true)
            ->with(['currency', 'currencyAccounts.currency'])
            ->get();
        $wallets = ElectronicWalletAccount::where('is_active', true)
            ->with(['currency', 'currencyAccounts.currency'])
            ->get();
        $offsetAccounts = Account::where('is_active', true)->where('is_postable', true)->orderBy('code')->get();

        return view('admin.finance.treasury.transactions.edit', [
            'transaction' => $treasuryTransaction,
            'cashboxes' => $cashboxes,
            'bankAccounts' => $bankAccounts,
            'wallets' => $wallets,
            'offsetAccounts' => $offsetAccounts,
        ]);
    }

    public function update(UpdateTreasuryTransactionRequest $request, TreasuryTransaction $treasuryTransaction): RedirectResponse
    {
        $this->updateDraftAction->execute($treasuryTransaction, $request->toDto());

        return redirect()
            ->route('admin.finance.treasury.transactions.show', $treasuryTransaction)
            ->with('success', "تم تحديث مسودة حركة الخزينة رقم '{$treasuryTransaction->transaction_number}' بنجاح.");
    }

    public function post(TreasuryTransaction $treasuryTransaction): RedirectResponse
    {
        $this->authorizePermission('admin.treasury_transactions.post');

        $posted = $this->postAction->execute($treasuryTransaction);

        return redirect()
            ->route('admin.finance.treasury.transactions.show', $posted)
            ->with('success', "تم ترحيل حركة الخزينة رقم '{$posted->transaction_number}' ماليًا بنجاح وإنشاء القيد المحاسبي.");
    }

    public function reverse(ReverseTreasuryTransactionRequest $request, TreasuryTransaction $treasuryTransaction): RedirectResponse
    {
        $this->authorizePermission('admin.treasury_transactions.reverse');

        $reversed = $this->reverseAction->execute($treasuryTransaction, $request->input('reason'));

        return redirect()
            ->route('admin.finance.treasury.transactions.show', $reversed)
            ->with('success', "تم عكس حركة الخزينة رقم '{$reversed->transaction_number}' والقيد المحاسبي المرتبط بها بنجاح.");
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }
    }
}
