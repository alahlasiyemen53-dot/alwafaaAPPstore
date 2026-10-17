<?php

namespace App\Http\Controllers;

use App\Actions\CloseAccountingPeriodAction;
use App\Actions\CreateAccountingPeriodAction;
use App\Http\Requests\StoreAccountingPeriodRequest;
use App\Models\AccountingPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AdminAccountingPeriodWebController extends Controller
{
    public function __construct(
        private readonly CreateAccountingPeriodAction $createPeriodAction,
        private readonly CloseAccountingPeriodAction $closePeriodAction,
    ) {}

    public function index(): View
    {
        $this->authorizePermission('admin.accounting_periods.view');

        $periods = AccountingPeriod::query()
            ->withCount('journalEntries')
            ->orderByDesc('starts_at')
            ->paginate(15);

        return view('admin.finance.periods.index', [
            'periods' => $periods,
        ]);
    }

    public function store(StoreAccountingPeriodRequest $request): RedirectResponse
    {
        $period = $this->createPeriodAction->execute($request->toDto());

        return redirect()
            ->route('admin.finance.periods.index')
            ->with('success', "تم إنشاء الفترة المحاسبية '{$period->name}' بنجاح.");
    }

    public function close(AccountingPeriod $period): RedirectResponse
    {
        $this->authorizePermission('admin.accounting_periods.close');

        $closed = $this->closePeriodAction->execute($period);

        return redirect()
            ->route('admin.finance.periods.index')
            ->with('success', "تم إغلاق الفترة المحاسبية '{$closed->name}' بنجاح، ولن يقبل النظام أي قيود جديدة ضمنها.");
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بإدارة الفترات المحاسبية.');
        }
    }
}
