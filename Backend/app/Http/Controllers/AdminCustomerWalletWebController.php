<?php

namespace App\Http\Controllers;

use App\Actions\CreditCustomerWalletAction;
use App\Actions\DebitCustomerWalletAction;
use App\Actions\RefundToCustomerWalletAction;
use App\Actions\ReverseCustomerWalletEntryAction;
use App\Http\Requests\Admin\AdjustCustomerWalletRequest;
use App\Http\Requests\Admin\RefundToCustomerWalletRequest;
use App\Http\Requests\Admin\ReverseCustomerWalletRequest;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerWalletAccount;
use App\Models\CustomerWalletEntry;
use App\Services\CustomerWalletBalanceQuery;
use App\Services\CustomerWalletStatementQuery;
use App\Support\DateFilterNormalizer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminCustomerWalletWebController extends Controller
{
    public function __construct(
        private readonly CustomerWalletBalanceQuery $balanceQuery,
        private readonly CustomerWalletStatementQuery $statementQuery,
    ) {}

    public function index(Request $request): View
    {
        $query = CustomerWalletAccount::query()->with(['customer', 'currency'])->latest('updated_at');

        if ($search = $request->input('search')) {
            $term = trim($search);
            $query->whereHas('customer', function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        if ($currencyId = $request->input('currency_id')) {
            $query->where('currency_id', $currencyId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $accounts = $query->paginate(15)->withQueryString();

        // Calculate dynamic balance per account
        $accounts->getCollection()->transform(function (CustomerWalletAccount $account) {
            $account->dynamic_balance = $this->balanceQuery->getBalance($account);

            return $account;
        });

        $currencies = Currency::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']);

        return view('admin.customer_wallets.index', [
            'accounts' => $accounts,
            'currencies' => $currencies,
            'customers' => $customers,
        ]);
    }

    public function show(CustomerWalletAccount $account, Request $request): View
    {
        $account->load(['customer', 'currency']);
        $startDate = DateFilterNormalizer::normalize($request->input('start_date'));
        $endDate = DateFilterNormalizer::normalize($request->input('end_date'));

        $statement = $this->statementQuery->getStatement($account, $startDate, $endDate);
        $currentBalance = $this->balanceQuery->getBalance($account);

        return view('admin.customer_wallets.show', [
            'account' => $account,
            'statement' => $statement,
            'currentBalance' => $currentBalance,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function adjust(
        AdjustCustomerWalletRequest $request,
        CreditCustomerWalletAction $creditAction,
        DebitCustomerWalletAction $debitAction
    ): RedirectResponse {
        try {
            $dto = $request->toDto();

            if ($dto->type === 'credit') {
                $entry = $creditAction->execute($dto);
            } else {
                $entry = $debitAction->execute($dto);
            }

            return back()->with('success', sprintf('تمت تسوية محفظة العميل بنجاح (رقم الحركة: %s).', $entry->entry_number));
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function refund(
        RefundToCustomerWalletRequest $request,
        RefundToCustomerWalletAction $refundAction
    ): RedirectResponse {
        try {
            $entry = $refundAction->execute($request->toDto());

            return back()->with('success', sprintf('تم استرداد قيمة المرتجع إلى محفظة العميل بنجاح (رقم الحركة: %s).', $entry->entry_number));
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function reverse(
        CustomerWalletEntry $entry,
        ReverseCustomerWalletRequest $request,
        ReverseCustomerWalletEntryAction $reverseAction
    ): RedirectResponse {
        try {
            $reversal = $reverseAction->execute(
                entry: $entry,
                reason: (string) $request->validated('reason'),
                userId: (int) auth()->id()
            );

            return back()->with('success', sprintf('تم عكس حركة المحفظة بنجاح (رقم الحركة العكسية: %s).', $reversal->entry_number));
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
