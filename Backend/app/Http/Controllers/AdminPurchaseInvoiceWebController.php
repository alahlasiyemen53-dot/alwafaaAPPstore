<?php

namespace App\Http\Controllers;

use App\Actions\CreatePurchaseInvoiceAction;
use App\Actions\DeletePurchaseInvoiceAction;
use App\Actions\PostPurchaseInvoiceAction;
use App\Actions\UpdatePurchaseInvoiceAction;
use App\Data\PurchaseInvoiceData;
use App\Http\Requests\Admin\StorePurchaseInvoiceRequest;
use App\Http\Requests\Admin\UpdatePurchaseInvoiceRequest;
use App\Models\PurchaseInvoice;
use App\Queries\AdminPurchaseInvoiceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminPurchaseInvoiceWebController extends Controller
{
    public function index(Request $request, AdminPurchaseInvoiceQuery $query): View
    {
        $invoices = $query->paginate(
            perPage: 20,
            search: $request->query('search'),
            supplierId: $request->query('supplier_id') ? (int) $request->query('supplier_id') : null,
            warehouseId: $request->query('warehouse_id') ? (int) $request->query('warehouse_id') : null,
            currencyId: $request->query('currency_id') ? (int) $request->query('currency_id') : null,
            status: $request->query('status'),
            dateFrom: $request->query('date_from'),
            dateTo: $request->query('date_to'),
        );

        return view('admin.purchases.index', [
            'invoices' => $invoices,
            'suppliers' => $query->activeSuppliers(),
            'warehouses' => $query->activeWarehouses(),
            'currencies' => $query->activeCurrencies(),
        ]);
    }

    public function create(AdminPurchaseInvoiceQuery $query): View
    {
        return view('admin.purchases.form', [
            'invoice' => null,
            'suppliers' => $query->activeSuppliers(),
            'warehouses' => $query->activeWarehouses(),
            'currencies' => $query->activeCurrencies(),
            'units' => $query->activeUnits(),
            'variants' => $query->purchasableVariants(),
        ]);
    }

    public function store(StorePurchaseInvoiceRequest $request, CreatePurchaseInvoiceAction $action): RedirectResponse
    {
        $invoice = $action->execute(PurchaseInvoiceData::fromValidated($request->validated()));

        return redirect()->route('admin.purchases.show', $invoice)->with('success', "تم إنشاء فاتورة الشراء رقم {$invoice->invoice_number} بنجاح.");
    }

    public function show(PurchaseInvoice $invoice): View
    {
        $invoice->loadMissing([
            'supplier',
            'warehouse',
            'currency',
            'items.productVariant.product',
            'items.productVariant.attributeValues.attribute',
            'items.unit',
            'poster',
            'creator',
            'updater',
            'ledgerEntry',
        ]);

        return view('admin.purchases.show', [
            'invoice' => $invoice,
        ]);
    }

    public function edit(PurchaseInvoice $invoice, AdminPurchaseInvoiceQuery $query): View
    {
        $invoice->loadMissing([
            'items.productVariant.product',
            'items.productVariant.attributeValues.attribute',
            'items.unit',
        ]);

        return view('admin.purchases.form', [
            'invoice' => $invoice,
            'suppliers' => $query->activeSuppliers(),
            'warehouses' => $query->activeWarehouses(),
            'currencies' => $query->activeCurrencies(),
            'units' => $query->activeUnits(),
            'variants' => $query->purchasableVariants(),
        ]);
    }

    public function update(UpdatePurchaseInvoiceRequest $request, PurchaseInvoice $invoice, UpdatePurchaseInvoiceAction $action): RedirectResponse
    {
        $updated = $action->execute($invoice, PurchaseInvoiceData::fromValidated($request->validated()));

        return redirect()->route('admin.purchases.show', $updated)->with('success', "تم تحديث فاتورة الشراء رقم {$updated->invoice_number} بنجاح.");
    }

    public function destroy(PurchaseInvoice $invoice, DeletePurchaseInvoiceAction $action): RedirectResponse
    {
        $number = $invoice->invoice_number;
        $action->execute($invoice);

        return redirect()->route('admin.purchases.index')->with('success', "تم حذف فاتورة الشراء {$number} بنجاح.");
    }

    public function post(PurchaseInvoice $invoice, PostPurchaseInvoiceAction $action): RedirectResponse
    {
        try {
            $action->execute($invoice);
        } catch (ConflictHttpException $e) {
            return redirect()->back(fallback: route('admin.purchases.show', $invoice))->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchases.show', $invoice)->with('success', "تم ترحيل فاتورة الشراء {$invoice->invoice_number} وقيد الالتزام للمورد بنجاح.");
    }
}
