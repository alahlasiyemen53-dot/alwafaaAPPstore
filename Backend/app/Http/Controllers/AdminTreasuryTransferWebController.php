<?php

namespace App\Http\Controllers;

use App\Actions\CreateTreasuryTransferAction;
use App\Http\Requests\StoreTreasuryTransferRequest;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\ElectronicWalletAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AdminTreasuryTransferWebController extends Controller
{
    public function __construct(
        private readonly CreateTreasuryTransferAction $createTransferAction,
    ) {}

    public function create(): View
    {
        $this->authorizePermission('admin.treasury_transfers.create');

        $cashboxes = Cashbox::where('is_active', true)->with(['currency', 'currencyAccounts.currency'])->get();
        $bankAccounts = BankAccount::where('is_active', true)->with(['currency', 'currencyAccounts.currency'])->get();
        $wallets = ElectronicWalletAccount::where('is_active', true)->with(['currency', 'currencyAccounts.currency'])->get();
        $currencies = Currency::where('is_active', true)->get();

        return view('admin.finance.treasury.transfers.create', [
            'cashboxes' => $cashboxes,
            'bankAccounts' => $bankAccounts,
            'wallets' => $wallets,
            'electronicWallets' => $wallets,
            'currencies' => $currencies,
        ]);
    }

    public function store(StoreTreasuryTransferRequest $request): RedirectResponse
    {
        $transfer = $this->createTransferAction->execute($request->toDto());

        return redirect()
            ->route('admin.finance.treasury.transactions.show', $transfer)
            ->with('success', "تم إنشاء مسودة التحويل المالي رقم '{$transfer->transaction_number}' بنجاح، ويمكنك الآن مراجعته وترحيله.");
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }
    }
}
