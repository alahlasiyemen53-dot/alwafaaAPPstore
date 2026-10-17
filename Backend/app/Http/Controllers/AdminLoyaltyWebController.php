<?php

namespace App\Http\Controllers;

use App\Actions\AdjustLoyaltyPointsAction;
use App\Http\Requests\Admin\AdjustCustomerLoyaltyRequest;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Services\LoyaltyBalanceQuery;
use App\Services\LoyaltyStatementQuery;
use App\Support\DateFilterNormalizer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminLoyaltyWebController extends Controller
{
    public function __construct(
        private readonly LoyaltyBalanceQuery $balanceQuery,
        private readonly LoyaltyStatementQuery $statementQuery,
    ) {}

    public function index(Request $request): View
    {
        $query = CustomerLoyaltyAccount::query()->with('customer')->latest('updated_at');

        if ($search = $request->input('search')) {
            $term = trim($search);
            $query->whereHas('customer', function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $accounts = $query->paginate(15)->withQueryString();

        // Calculate dynamic points balance per account
        $accounts->getCollection()->transform(function (CustomerLoyaltyAccount $account) {
            $account->dynamic_balance = $this->balanceQuery->getBalance($account);

            return $account;
        });

        $customers = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']);

        return view('admin.loyalty.index', [
            'accounts' => $accounts,
            'customers' => $customers,
        ]);
    }

    public function show(CustomerLoyaltyAccount $account, Request $request): View
    {
        $account->load('customer');
        $startDate = DateFilterNormalizer::normalize($request->input('start_date'));
        $endDate = DateFilterNormalizer::normalize($request->input('end_date'));

        $statement = $this->statementQuery->getStatement($account, $startDate, $endDate);
        $currentBalance = $this->balanceQuery->getBalance($account);

        return view('admin.loyalty.show', [
            'account' => $account,
            'statement' => $statement,
            'currentBalance' => $currentBalance,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function adjust(AdjustCustomerLoyaltyRequest $request, AdjustLoyaltyPointsAction $action): RedirectResponse
    {
        try {
            $entry = $action->execute($request->toDto());

            return back()->with('success', sprintf('تمت تسوية نقاط الولاء بنجاح (رقم الحركة: %s).', $entry->entry_number));
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
