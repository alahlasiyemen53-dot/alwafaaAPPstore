<?php

namespace App\Http\Controllers;

use App\Actions\CreateAccountAction;
use App\Actions\ToggleAccountStatusAction;
use App\Actions\UpdateAccountAction;
use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminChartOfAccountsWebController extends Controller
{
    public function __construct(
        private readonly CreateAccountAction $createAccountAction,
        private readonly UpdateAccountAction $updateAccountAction,
        private readonly ToggleAccountStatusAction $toggleStatusAction,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('admin.accounts.view');

        $query = Account::query()->with(['parent', 'children'])->orderBy('code');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $accounts = $query->paginate(20)->withQueryString();

        $metrics = [
            'total' => Account::count(),
            'active' => Account::where('is_active', true)->count(),
            'postable' => Account::where('is_postable', true)->count(),
            'assets' => Account::where('type', AccountType::Asset)->count(),
            'liabilities' => Account::where('type', AccountType::Liability)->count(),
            'equity' => Account::where('type', AccountType::Equity)->count(),
            'revenue' => Account::where('type', AccountType::Revenue)->count(),
            'expenses' => Account::where('type', AccountType::Expense)->count(),
        ];

        return view('admin.finance.accounts.index', [
            'accounts' => $accounts,
            'metrics' => $metrics,
            'filters' => $request->only(['search', 'type', 'status']),
            'accountTypes' => AccountType::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('admin.accounts.create');

        $parentAccounts = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('admin.finance.accounts.create', [
            'parentAccounts' => $parentAccounts,
            'accountTypes' => AccountType::cases(),
            'normalBalances' => AccountNormalBalance::cases(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $this->createAccountAction->execute($request->toDto());

        return redirect()
            ->route('admin.finance.accounts.index')
            ->with('success', "تم إنشاء الحساب '{$account->name_ar}' بنجاح برمز {$account->code}.");
    }

    public function edit(Account $account): View
    {
        $this->authorizePermission('admin.accounts.update');

        $parentAccounts = Account::query()
            ->where('id', '!=', $account->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('admin.finance.accounts.edit', [
            'account' => $account,
            'parentAccounts' => $parentAccounts,
            'accountTypes' => AccountType::cases(),
            'normalBalances' => AccountNormalBalance::cases(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $updated = $this->updateAccountAction->execute($account, $request->toDto());

        return redirect()
            ->route('admin.finance.accounts.index')
            ->with('success', "تم تحديث الحساب '{$updated->name_ar}' بنجاح.");
    }

    public function toggle(Account $account): RedirectResponse
    {
        $this->authorizePermission('admin.accounts.toggle');

        $toggled = $this->toggleStatusAction->execute($account);
        $statusMsg = $toggled->is_active ? 'تفعيل' : 'تعطيل';

        return back()->with('success', "تم {$statusMsg} الحساب '{$toggled->name_ar}' بنجاح.");
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية المحاسبية.');
        }
    }
}
