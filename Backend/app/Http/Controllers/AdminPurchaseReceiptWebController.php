<?php

namespace App\Http\Controllers;

use App\Actions\CreatePurchaseReceiptAction;
use App\Actions\DeletePurchaseReceiptAction;
use App\Actions\PostPurchaseReceiptAction;
use App\Actions\UpdatePurchaseReceiptAction;
use App\Data\PurchaseReceiptData;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Http\Requests\Admin\StorePurchaseReceiptRequest;
use App\Http\Requests\Admin\UpdatePurchaseReceiptRequest;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReceipt;
use App\Models\Warehouse;
use App\Queries\AdminPurchaseReceiptQuery;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminPurchaseReceiptWebController extends Controller
{
    public function index(Request $request, AdminPurchaseReceiptQuery $query): View
    {
        $receipts = $query->paginate($request->query(), perPage: 20);
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.purchase_receipts.index', [
            'receipts' => $receipts,
            'warehouses' => $warehouses,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedInvoiceId = $request->query('purchase_invoice_id');
        $selectedInvoice = null;

        if ($selectedInvoiceId) {
            $selectedInvoice = PurchaseInvoice::query()
                ->where('status', PurchaseInvoiceStatus::Posted)
                ->with(['supplier', 'warehouse', 'items.productVariant.product', 'items.unit', 'items.purchaseReceiptItems.purchaseReceipt'])
                ->find($selectedInvoiceId);
        }

        $postedInvoices = PurchaseInvoice::query()
            ->where('status', PurchaseInvoiceStatus::Posted)
            ->with(['supplier', 'warehouse'])
            ->orderByDesc('id')
            ->get();

        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.purchase_receipts.create', [
            'selectedInvoice' => $selectedInvoice,
            'postedInvoices' => $postedInvoices,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(StorePurchaseReceiptRequest $request, CreatePurchaseReceiptAction $action): RedirectResponse
    {
        try {
            $receipt = $action->execute(PurchaseReceiptData::fromArray($request->validated()));
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchase-receipts.show', $receipt)
            ->with('success', "تم إنشاء مسودة سند الاستلام رقم {$receipt->receipt_number} بنجاح.");
    }

    public function show(PurchaseReceipt $receipt): View
    {
        $receipt->loadMissing([
            'purchaseInvoice.supplier',
            'warehouse',
            'receivedBy',
            'createdBy',
            'updatedBy',
            'items.productVariant.product',
            'items.purchaseInvoiceItem.unit',
        ]);

        return view('admin.purchase_receipts.show', [
            'receipt' => $receipt,
        ]);
    }

    public function edit(PurchaseReceipt $receipt): View|RedirectResponse
    {
        if ($receipt->status === PurchaseReceiptStatus::Posted) {
            return redirect()->route('admin.purchase-receipts.show', $receipt)
                ->with('error', 'لا يمكن تعديل سند استلام تم ترحيله مسبقاً.');
        }

        $receipt->loadMissing([
            'purchaseInvoice.supplier',
            'purchaseInvoice.items.productVariant.product',
            'purchaseInvoice.items.unit',
            'items',
            'warehouse',
        ]);

        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.purchase_receipts.edit', [
            'receipt' => $receipt,
            'warehouses' => $warehouses,
        ]);
    }

    public function update(
        UpdatePurchaseReceiptRequest $request,
        PurchaseReceipt $receipt,
        UpdatePurchaseReceiptAction $action
    ): RedirectResponse {
        try {
            $data = PurchaseReceiptData::fromArray(array_merge(
                $request->validated(),
                ['purchase_invoice_id' => $receipt->purchase_invoice_id]
            ));
            $updated = $action->execute($receipt, $data);
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchase-receipts.show', $updated)
            ->with('success', "تم تحديث سند الاستلام رقم {$updated->receipt_number} بنجاح.");
    }

    public function destroy(PurchaseReceipt $receipt, DeletePurchaseReceiptAction $action): RedirectResponse
    {
        $number = $receipt->receipt_number;
        try {
            $action->execute($receipt);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchase-receipts.index')
            ->with('success', "تم حذف سند الاستلام {$number} بنجاح.");
    }

    public function post(PurchaseReceipt $receipt, PostPurchaseReceiptAction $action): RedirectResponse
    {
        try {
            $posted = $action->execute($receipt);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchase-receipts.show', $posted)
            ->with('success', "تم ترحيل سند الاستلام رقم {$posted->receipt_number} وإيداع الكميات في المخزون بنجاح.");
    }
}
