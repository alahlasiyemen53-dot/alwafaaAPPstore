<?php

namespace App\Http\Controllers;

use App\Actions\CreateBankAccountAction;
use App\Actions\ToggleBankAccountStatusAction;
use App\Actions\UpdateBankAccountAction;
use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Services\TreasuryBalanceService;
use App\Support\TreasuryGlAccountAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminBankAccountWebController extends Controller
{
    public function __construct(
        private readonly CreateBankAccountAction $createAction,
        private readonly UpdateBankAccountAction $updateAction,
        private readonly ToggleBankAccountStatusAction $toggleStatusAction,
        private readonly TreasuryBalanceService $balanceService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('admin.bank_accounts.view');

        $query = BankAccount::query()->with([
            'currency',
            'glAccount',
            'currencyAccounts.currency',
            'currencyAccounts.glAccount',
        ])->orderBy('code');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('currency_id')) {
            $currencyId = (int) $request->input('currency_id');
            $query->where(function ($q) use ($currencyId): void {
                $q->where('currency_id', $currencyId)
                    ->orWhereHas('currencyAccounts', fn ($ca) => $ca->where('currency_id', $currencyId));
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $bankAccounts = $query->paginate(15)->withQueryString();

        $bankAccounts->getCollection()->transform(function (BankAccount $account) {
            $account->derived_balance = $this->balanceService->getAuthoritativeBalance($account);
            foreach ($account->currencyAccounts as $currAcc) {
                $currAcc->derived_balance = $this->balanceService->getAuthoritativeBalance($currAcc);
            }

            return $account;
        });

        $currencies = Currency::where('is_active', true)->get();

        $availableAccounts = TreasuryGlAccountAvailability::availableAssetAccounts();

        $metrics = [
            'total' => BankAccount::count(),
            'active' => BankAccount::where('is_active', true)->count(),
            'inactive' => BankAccount::where('is_active', false)->count(),
        ];

        return view('admin.finance.treasury.bank_accounts.index', [
            'bankAccounts' => $bankAccounts,
            'currencies' => $currencies,
            'availableAccounts' => $availableAccounts,
            'metrics' => $metrics,
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('admin.bank_accounts.create');

        $currencies = Currency::where('is_active', true)->orderByDesc('is_default')->get();
        $accounts = TreasuryGlAccountAvailability::availableAssetAccounts();

        return view('admin.finance.treasury.bank_accounts.create', [
            'currencies' => $currencies,
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $account = $this->createAction->execute($request->toDto());

        return redirect()
            ->route('admin.finance.treasury.bank-accounts.index')
            ->with('success', "تم إنشاء الحساب البنكي {$account->account_name} بنجاح.");
    }

    public function edit(BankAccount $bank_account): View
    {
        $this->authorizePermission('admin.bank_accounts.update');

        $bank_account->load(['currencyAccounts']);
        $currencies = Currency::where('is_active', true)->orderByDesc('is_default')->get();
        $accounts = TreasuryGlAccountAvailability::availableAssetAccounts();

        return view('admin.finance.treasury.bank_accounts.edit', [
            'bankAccount' => $bank_account,
            'currencies' => $currencies,
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bank_account): RedirectResponse
    {
        $this->updateAction->execute($bank_account, $request->toDto());

        return redirect()
            ->route('admin.finance.treasury.bank-accounts.index')
            ->with('success', "تم تحديث بيانات الحساب البنكي '{$bank_account->account_name}' بنجاح.");
    }

    public function toggle(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorizePermission('admin.bank_accounts.toggle');

        $this->toggleStatusAction->execute($bankAccount);

        $statusStr = $bankAccount->is_active ? 'تفعيل' : 'تعطيل';

        return back()->with('success', "تم {$statusStr} الحساب البنكي بنجاح.");
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }
    }
}
