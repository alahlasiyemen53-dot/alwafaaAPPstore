<?php

namespace App\Http\Controllers;

use App\Actions\CreateSupplierAction;
use App\Actions\ToggleSupplierStatusAction;
use App\Actions\UpdateSupplierAction;
use App\Data\SupplierData;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\Currency;
use App\Models\Supplier;
use App\Queries\AdminSupplierQuery;
use App\Queries\AdminSupplierStatementQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminSupplierWebController extends Controller
{
    public function index(Request $request, AdminSupplierQuery $query): View
    {
        $suppliers = $query->paginate(
            perPage: 20,
            search: $request->query('search'),
            status: $request->query('status'),
        );

        return view('admin.suppliers.index', [
            'suppliers' => $suppliers,
        ]);
    }

    public function create(): View
    {
        return view('admin.suppliers.form', [
            'supplier' => null,
        ]);
    }

    public function store(StoreSupplierRequest $request, CreateSupplierAction $action): RedirectResponse
    {
        $supplier = $action->execute(SupplierData::fromValidated($request->validated()));

        return redirect()->route('admin.suppliers.index')->with('success', "تم إضافة المورد {$supplier->name} بنجاح.");
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.form', [
            'supplier' => $supplier,
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier, UpdateSupplierAction $action): RedirectResponse
    {
        $action->execute($supplier, SupplierData::fromValidated($request->validated()));

        return redirect()->route('admin.suppliers.index')->with('success', "تم تحديث بيانات المورد {$supplier->name} بنجاح.");
    }

    public function toggleStatus(Supplier $supplier, ToggleSupplierStatusAction $action): RedirectResponse
    {
        $action->execute($supplier);

        $msg = $supplier->is_active ? 'تم تفعيل المورد بنجاح.' : 'تم تعطيل المورد بنجاح.';

        return redirect()->back(fallback: route('admin.suppliers.index'))->with('success', $msg);
    }

    public function statement(Request $request, Supplier $supplier, AdminSupplierStatementQuery $statementQuery): View
    {
        $currencyId = $request->query('currency_id') ? (int) $request->query('currency_id') : null;
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $entries = $statementQuery->paginate($supplier, $currencyId, $dateFrom, $dateTo);
        $balances = $supplier->getBalancesByCurrency();
        $currencies = Currency::query()->where('is_active', true)->get();

        return view('admin.suppliers.statement', [
            'supplier' => $supplier,
            'entries' => $entries,
            'balances' => $balances,
            'currencies' => $currencies,
        ]);
    }
}
