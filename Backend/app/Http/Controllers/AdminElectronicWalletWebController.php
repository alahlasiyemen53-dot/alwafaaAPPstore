<?php

namespace App\Http\Controllers;

use App\Actions\CreateElectronicWalletAccountAction;
use App\Actions\ToggleElectronicWalletAccountStatusAction;
use App\Actions\UpdateElectronicWalletAccountAction;
use App\Http\Requests\StoreElectronicWalletAccountRequest;
use App\Http\Requests\UpdateElectronicWalletAccountRequest;
use App\Models\Currency;
use App\Models\ElectronicWalletAccount;
use App\Services\TreasuryBalanceService;
use App\Support\TreasuryGlAccountAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminElectronicWalletWebController extends Controller
{
    public function __construct(
        private readonly CreateElectronicWalletAccountAction $createAction,
        private readonly UpdateElectronicWalletAccountAction $updateAction,
        private readonly ToggleElectronicWalletAccountStatusAction $toggleStatusAction,
        private readonly TreasuryBalanceService $balanceService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('admin.electronic_wallets.view');

        $query = ElectronicWalletAccount::query()->with([
            'currency',
            'glAccount',
            'currencyAccounts.currency',
            'currencyAccounts.glAccount',
        ])->orderBy('code');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('provider_name', 'like', "%{$search}%")
                    ->orWhere('account_label', 'like', "%{$search}%")
                    ->orWhere('account_identifier', 'like', "%{$search}%");
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

        $wallets = $query->paginate(15)->withQueryString();

        $wallets->getCollection()->transform(function (ElectronicWalletAccount $wallet) {
            $wallet->derived_balance = $this->balanceService->getAuthoritativeBalance($wallet);
            foreach ($wallet->currencyAccounts as $currAcc) {
                $currAcc->derived_balance = $this->balanceService->getAuthoritativeBalance($currAcc);
            }

            return $wallet;
        });

        $currencies = Currency::where('is_active', true)->get();

        $availableAccounts = TreasuryGlAccountAvailability::availableAssetAccounts();

        $metrics = [
            'total' => ElectronicWalletAccount::count(),
            'active' => ElectronicWalletAccount::where('is_active', true)->count(),
            'inactive' => ElectronicWalletAccount::where('is_active', false)->count(),
        ];

        return view('admin.finance.treasury.electronic_wallets.index', [
            'wallets' => $wallets,
            'currencies' => $currencies,
            'availableAccounts' => $availableAccounts,
            'metrics' => $metrics,
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('admin.electronic_wallets.create');

        $currencies = Currency::where('is_active', true)->orderByDesc('is_default')->get();
        $accounts = TreasuryGlAccountAvailability::availableAssetAccounts();

        return view('admin.finance.treasury.electronic_wallets.create', [
            'currencies' => $currencies,
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreElectronicWalletAccountRequest $request): RedirectResponse
    {
        $wallet = $this->createAction->execute($request->toDto());

        return redirect()
            ->route('admin.finance.treasury.electronic-wallets.index')
            ->with('success', "تم إضافة حساب المحفظة الإلكترونية '{$wallet->account_label}' بنجاح.");
    }

    public function edit(ElectronicWalletAccount $electronic_wallet): View
    {
        $this->authorizePermission('admin.electronic_wallets.update');

        $electronic_wallet->load(['currencyAccounts']);
        $currencies = Currency::where('is_active', true)->orderByDesc('is_default')->get();
        $accounts = TreasuryGlAccountAvailability::availableAssetAccounts();

        return view('admin.finance.treasury.electronic_wallets.edit', [
            'electronicWallet' => $electronic_wallet,
            'currencies' => $currencies,
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateElectronicWalletAccountRequest $request, ElectronicWalletAccount $electronic_wallet): RedirectResponse
    {
        $this->updateAction->execute($electronic_wallet, $request->toDto());

        return redirect()
            ->route('admin.finance.treasury.electronic-wallets.index')
            ->with('success', "تم تحديث بيانات المحفظة الإلكترونية '{$electronic_wallet->account_label}' بنجاح.");
    }

    public function toggle(ElectronicWalletAccount $electronicWallet): RedirectResponse
    {
        $this->authorizePermission('admin.electronic_wallets.toggle');

        $this->toggleStatusAction->execute($electronicWallet);

        $statusStr = $electronicWallet->is_active ? 'تفعيل' : 'تعطيل';

        return back()->with('success', "تم {$statusStr} حساب المحفظة الإلكترونية بنجاح.");
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }
    }
}
