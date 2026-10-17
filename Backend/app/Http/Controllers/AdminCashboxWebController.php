<?php

namespace App\Http\Controllers;

use App\Actions\CreateCashboxAction;
use App\Actions\ToggleCashboxStatusAction;
use App\Actions\UpdateCashboxAction;
use App\Http\Requests\StoreCashboxRequest;
use App\Http\Requests\UpdateCashboxRequest;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Services\TreasuryBalanceService;
use App\Support\TreasuryGlAccountAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminCashboxWebController extends Controller
{
    public function __construct(
        private readonly CreateCashboxAction $createAction,
        private readonly ToggleCashboxStatusAction $toggleStatusAction,
        private readonly TreasuryBalanceService $balanceService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('admin.cashboxes.view');

        $query = Cashbox::query()->with([
            'currency',
            'glAccount',
            'currencyAccounts.currency',
            'currencyAccounts.glAccount',
        ])->orderBy('code');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
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

        $cashboxes = $query->paginate(15)->withQueryString();

        // Attach derived balance from General Ledger to each cashbox and each currency account
        $cashboxes->getCollection()->transform(function (Cashbox $cashbox) {
            $cashbox->derived_balance = $this->balanceService->getAuthoritativeBalance($cashbox);
            foreach ($cashbox->currencyAccounts as $currAcc) {
                $currAcc->derived_balance = $this->balanceService->getAuthoritativeBalance($currAcc);
            }

            return $cashbox;
        });

        $currencies = Currency::where('is_active', true)->get();

        $availableAccounts = TreasuryGlAccountAvailability::availableAssetAccounts();

        $metrics = [
            'total' => Cashbox::count(),
            'active' => Cashbox::where('is_active', true)->count(),
            'inactive' => Cashbox::where('is_active', false)->count(),
        ];

        return view('admin.finance.treasury.cashboxes.index', [
            'cashboxes' => $cashboxes,
            'currencies' => $currencies,
            'availableAccounts' => $availableAccounts,
            'metrics' => $metrics,
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('admin.cashboxes.create');

        $currencies = Currency::where('is_active', true)->orderByDesc('is_default')->get();
        // Asset accounts, active, postable, not dedicated to any currency sub-account yet
        $accounts = TreasuryGlAccountAvailability::availableAssetAccounts();

        return view('admin.finance.treasury.cashboxes.create', [
            'currencies' => $currencies,
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreCashboxRequest $request): RedirectResponse
    {
        $cashbox = $this->createAction->execute($request->toDto());

        return redirect()
            ->route('admin.finance.treasury.cashboxes.index')
            ->with('success', "تم إنشاء الصندوق النقدي {$cashbox->name_ar} بنجاح.");
    }

    public function edit(Cashbox $cashbox): View
    {
        $cashbox->load(['currency', 'glAccount', 'currencyAccounts']);
        $currencies = Currency::where('is_active', true)->orderBy('name_ar')->get();
        $accounts = TreasuryGlAccountAvailability::availableAssetAccounts();

        return view('admin.finance.treasury.cashboxes.edit', [
            'cashbox' => $cashbox,
            'currencies' => $currencies,
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateCashboxRequest $request, Cashbox $cashbox, UpdateCashboxAction $action): RedirectResponse
    {
        $updated = $action->execute($cashbox, $request->toDto());

        return redirect()
            ->route('admin.finance.treasury.cashboxes.index')
            ->with('success', "تم تحديث بيانات الصندوق {$updated->name_ar} بنجاح.");
    }

    public function toggle(Cashbox $cashbox): RedirectResponse
    {
        $this->authorizePermission('admin.cashboxes.toggle');

        $this->toggleStatusAction->execute($cashbox);

        $statusStr = $cashbox->is_active ? 'تفعيل' : 'تعطيل';

        return back()->with('success', "تم {$statusStr} الصندوق النقدي بنجاح.");
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }
    }
}
