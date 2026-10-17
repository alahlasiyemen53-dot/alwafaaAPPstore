<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CancelPurchaseOrderAction;
use App\Actions\CancelPurchaseOrderItemAction;
use App\Actions\ChangePurchaseOrderSupplierAction;
use App\Actions\HoldPurchaseOrderAction;
use App\Actions\MarkPurchaseOrderCollectedAction;
use App\Actions\MarkPurchaseOrderFailedAction;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\Admin\CancelPurchaseOrderItemRequest;
use App\Http\Requests\Admin\CancelPurchaseOrderRequest;
use App\Http\Requests\Admin\ChangePurchaseOrderSupplierRequest;
use App\Http\Requests\Admin\HoldPurchaseOrderRequest;
use App\Http\Requests\Admin\MarkPurchaseOrderFailedRequest;
use App\Models\Driver;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\DateFilterNormalizer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminPurchaseOrderWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission('admin.sourcing.view');

        $query = PurchaseOrder::query()
            ->with(['order.customer', 'supplier', 'assignedDriver', 'items'])
            ->withCount(['items as active_items_count' => fn ($q) => $q->where('status', 'pending')])
            ->latest('id');

        if ($search = $request->input('search')) {
            $term = trim((string) $search);
            $query->where(function ($q) use ($term): void {
                $q->where('po_number', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$term}%"))
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
                    ->orWhereHas('assignedDriver', fn ($dq) => $dq->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
                    ->orWhereHas('items', fn ($iq) => $iq->where('sku_snapshot', 'like', "%{$term}%")
                        ->orWhere('supplier_item_number_snapshot', 'like', "%{$term}%")
                        ->orWhere('product_name_snapshot', 'like', "%{$term}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($supplierId = $request->input('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($driverId = $request->input('driver_id')) {
            $query->where('assigned_driver_id', $driverId);
        }

        $dateError = null;
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $normFrom = DateFilterNormalizer::normalize($request->input('date_from'));
            $normTo = DateFilterNormalizer::normalize($request->input('date_to'));
            if ($normFrom && $normTo && $normFrom > $normTo) {
                $dateError = 'تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية.';
            } else {
                if ($normFrom) {
                    $query->whereDate('created_at', '>=', $normFrom);
                }
                if ($normTo) {
                    $query->whereDate('created_at', '<=', $normTo);
                }
            }
        } elseif ($request->filled('date_from')) {
            $normFrom = DateFilterNormalizer::normalize($request->input('date_from'));
            if ($normFrom) {
                $query->whereDate('created_at', '>=', $normFrom);
            }
        } elseif ($request->filled('date_to')) {
            $normTo = DateFilterNormalizer::normalize($request->input('date_to'));
            if ($normTo) {
                $query->whereDate('created_at', '<=', $normTo);
            }
        }

        $purchaseOrders = $query->paginate(15)->withQueryString();

        $metrics = [
            'pending' => PurchaseOrder::query()->where('status', PurchaseOrderStatus::Pending)->count(),
            'assigned' => PurchaseOrder::query()->where('status', PurchaseOrderStatus::Assigned)->count(),
            'collected' => PurchaseOrder::query()->where('status', PurchaseOrderStatus::Collected)->count(),
            'on_hold' => PurchaseOrder::query()->where('status', PurchaseOrderStatus::OnHold)->count(),
            'failed' => PurchaseOrder::query()->where('status', PurchaseOrderStatus::Failed)->count(),
        ];

        return view('admin.purchase_orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'metrics' => $metrics,
            'drivers' => Driver::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => PurchaseOrderStatus::cases(),
            'filters' => $request->only(['search', 'status', 'supplier_id', 'driver_id', 'date_from', 'date_to']),
            'dateError' => $dateError,
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorizePermission('admin.sourcing.view');

        $purchaseOrder->load([
            'order.customer',
            'order.pickupDriver',
            'order.purchaseOrders',
            'supplier',
            'assignedDriver',
            'items.orderItem',
            'items.productVariant',
            'purchaseInvoice',
        ]);

        return view('admin.purchase_orders.show', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function confirmCollected(
        PurchaseOrder $purchaseOrder,
        MarkPurchaseOrderCollectedAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $action->execute($purchaseOrder, (int) auth()->id());

            return back()->with('success', 'تم تأكيد استلام طلب الشراء من المورد بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function changeSupplier(
        ChangePurchaseOrderSupplierRequest $request,
        PurchaseOrder $purchaseOrder,
        ChangePurchaseOrderSupplierAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $updated = $action->execute(
                purchaseOrder: $purchaseOrder,
                newSupplierId: (int) $request->validated('supplier_id'),
                purchaseOrderItemId: $request->validated('purchase_order_item_id')
                    ? (int) $request->validated('purchase_order_item_id')
                    : null,
                userId: (int) auth()->id(),
            );

            return redirect()
                ->route('admin.purchase-orders.show', $updated)
                ->with('success', 'تم تغيير مورد طلب الشراء بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function hold(
        HoldPurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        HoldPurchaseOrderAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $action->execute($purchaseOrder, (string) $request->validated('reason'), (int) auth()->id());

            return back()->with('success', 'تم تعليق طلب الشراء بسبب صنف ناقص.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function markFailed(
        MarkPurchaseOrderFailedRequest $request,
        PurchaseOrder $purchaseOrder,
        MarkPurchaseOrderFailedAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $action->execute($purchaseOrder, (string) $request->validated('failure_reason'), (int) auth()->id());

            return back()->with('success', 'تم تسجيل تعذر استلام طلب الشراء.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function cancel(
        CancelPurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        CancelPurchaseOrderAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $action->execute($purchaseOrder, (string) $request->validated('cancellation_reason'), (int) auth()->id());

            return back()->with('success', 'تم إلغاء طلب الشراء.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function cancelItem(
        CancelPurchaseOrderItemRequest $request,
        PurchaseOrderItem $purchaseOrderItem,
        CancelPurchaseOrderItemAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $po = $action->execute(
                $purchaseOrderItem,
                (string) $request->validated('cancellation_reason'),
                (int) auth()->id(),
            );

            return redirect()
                ->route('admin.purchase-orders.show', $po)
                ->with('success', 'تم إلغاء البند من طلب الشراء وطلب العميل.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    private function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بتنفيذ هذه العملية.');
        }
    }
}
