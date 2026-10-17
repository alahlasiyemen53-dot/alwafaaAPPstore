<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Queries\GeneralLedgerQuery;
use App\Queries\Reporting\FinancialIntegrityQuery;
use App\Queries\TrialBalanceQuery;
use App\Services\SupplierLedgerReconciliationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminFinancialReportsWebController extends Controller
{
    public function __construct(
        private readonly TrialBalanceQuery $trialBalanceQuery,
        private readonly GeneralLedgerQuery $generalLedgerQuery,
        private readonly SupplierLedgerReconciliationService $reconciliationService,
        private readonly FinancialIntegrityQuery $integrityQuery,
    ) {}

    public function integrity(ReportFilterRequest $request): View
    {
        $this->authorizePermission('admin.reports.finance.view');

        $filters = $request->toData();
        $result = $this->integrityQuery->execute($filters->range);

        return view('admin.finance.reports.integrity', [
            'filters' => $filters,
            'result' => $result,
        ]);
    }

    public function trialBalance(Request $request): View
    {
        $this->authorizePermission('admin.trial_balance.view');

        $periodId = $request->filled('period_id') ? (int) $request->input('period_id') : null;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $accountType = $request->input('account_type');

        $report = $this->trialBalanceQuery->execute($periodId, $startDate, $endDate, $accountType);
        $periods = AccountingPeriod::query()->orderByDesc('starts_at')->get();

        return view('admin.finance.reports.trial_balance', [
            'report' => $report,
            'periods' => $periods,
            'accountTypes' => AccountType::cases(),
            'filters' => [
                'period_id' => $periodId,
                'start_date' => $report['start_date'],
                'end_date' => $report['end_date'],
                'account_type' => $accountType,
            ],
        ]);
    }

    public function generalLedger(Request $request): View
    {
        $this->authorizePermission('admin.general_ledger.view');

        $accounts = Account::where('is_active', true)->where('is_postable', true)->orderBy('code')->get();
        $selectedAccountId = $request->filled('account_id') ? (int) $request->input('account_id') : $accounts->first()?->id;

        $report = null;
        if ($selectedAccountId) {
            $periodId = $request->filled('period_id') ? (int) $request->input('period_id') : null;
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $report = $this->generalLedgerQuery->execute($selectedAccountId, $periodId, $startDate, $endDate);
        }

        $periods = AccountingPeriod::query()->orderByDesc('starts_at')->get();

        return view('admin.finance.reports.general_ledger', [
            'report' => $report,
            'accounts' => $accounts,
            'selectedAccountId' => $selectedAccountId,
            'periods' => $periods,
            'filters' => [
                'account_id' => $selectedAccountId,
                'period_id' => $request->input('period_id'),
                'start_date' => $report['start_date'] ?? $request->input('start_date'),
                'end_date' => $report['end_date'] ?? $request->input('end_date'),
            ],
        ]);
    }

    public function reconcileSupplierLedger(): View
    {
        $this->authorizePermission('admin.general_ledger.view');

        $result = $this->reconciliationService->reconcile();

        return view('admin.finance.reports.supplier_reconciliation', [
            'reconciliation' => $result,
        ]);
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بعرض التقارير المالية.');
        }
    }
}
