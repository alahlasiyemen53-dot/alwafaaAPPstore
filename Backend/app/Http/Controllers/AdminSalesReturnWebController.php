<?php

namespace App\Http\Controllers;

use App\Actions\CalculateReturnableQuantityAction;
use App\Actions\CancelSalesReturnAction;
use App\Actions\CreateSalesReturnAction;
use App\Actions\PostSalesReturnAction;
use App\Actions\UpdateDraftSalesReturnAction;
use App\Enums\ReturnReasonCode;
use App\Enums\SalesReturnStatus;
use App\Http\Requests\StoreSalesReturnRequest;
use App\Http\Requests\UpdateSalesReturnRequest;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use App\Support\DateFilterNormalizer;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminSalesReturnWebController extends Controller
{
    public function index(Request $request): View
    {
        $query = SalesReturn::query()
            ->with(['order.customer', 'warehouse', 'currency', 'postedBy'])
            ->withCount('items')
            ->latest('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('order.customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($fromDate = DateFilterNormalizer::normalize($request->input('from_date'))) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate = DateFilterNormalizer::normalize($request->input('to_date'))) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $salesReturns = $query->paginate(15)->withQueryString();
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.sales_returns.index', [
            'salesReturns' => $salesReturns,
            'warehouses' => $warehouses,
            'statuses' => SalesReturnStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $selectedOrderId = $request->input('order_id');

        $orders = Order::query()
            ->whereHas('items')
            ->with(['customer', 'warehouse', 'currency'])
            ->latest('id')
            ->limit(50)
            ->get();

        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();
        $reasons = ReturnReasonCode::cases();

        return view('admin.sales_returns.create', [
            'orders' => $orders,
            'selectedOrderId' => $selectedOrderId,
            'warehouses' => $warehouses,
            'reasons' => $reasons,
        ]);
    }

    public function store(
        StoreSalesReturnRequest $request,
        CreateSalesReturnAction $createAction,
        PostSalesReturnAction $postAction
    ): RedirectResponse {
        try {
            $dto = $request->toDto();
            $userId = (int) auth()->id();

            $salesReturn = $createAction->execute($dto, $userId);

            if ($request->input('action') === 'post') {
                $postAction->execute($salesReturn, $userId);

                return redirect()->route('admin.sales-returns.show', $salesReturn)
                    ->with('success', sprintf('تم إنشاء واعتماد مرتجع المبيعات (%s) بنجاح وإضافة البضاعة للمخزون.', $salesReturn->return_number));
            }

            return redirect()->route('admin.sales-returns.show', $salesReturn)
                ->with('success', sprintf('تم حفظ مسودة مرتجع المبيعات (%s) بنجاح.', $salesReturn->return_number));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(SalesReturn $salesReturn): View
    {
        $salesReturn->load([
            'order.customer',
            'order.deliveryAddress',
            'warehouse',
            'currency',
            'items.productVariant.product',
            'items.unit',
            'stockMovements',
            'postedBy',
            'createdBy',
            'updatedBy',
        ]);

        return view('admin.sales_returns.show', [
            'salesReturn' => $salesReturn,
        ]);
    }

    public function edit(SalesReturn $salesReturn): View|RedirectResponse
    {
        if (! $salesReturn->canBeEdited()) {
            return redirect()->route('admin.sales-returns.show', $salesReturn)
                ->with('error', 'لا يمكن تعديل المرتجع لأنه ليس في حالة مسودة.');
        }

        $salesReturn->load(['order.customer', 'items']);
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();
        $reasons = ReturnReasonCode::cases();

        return view('admin.sales_returns.edit', [
            'salesReturn' => $salesReturn,
            'warehouses' => $warehouses,
            'reasons' => $reasons,
        ]);
    }

    public function update(
        UpdateSalesReturnRequest $request,
        SalesReturn $salesReturn,
        UpdateDraftSalesReturnAction $updateAction
    ): RedirectResponse {
        try {
            $dto = $request->toDto();
            $userId = (int) auth()->id();

            $updateAction->execute($salesReturn, $dto, $userId);

            return redirect()->route('admin.sales-returns.show', $salesReturn)
                ->with('success', 'تم تحديث مسودة المرتجع بنجاح.');
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function post(
        SalesReturn $salesReturn,
        PostSalesReturnAction $postAction
    ): RedirectResponse {
        try {
            $postAction->execute($salesReturn, (int) auth()->id());

            return redirect()->route('admin.sales-returns.show', $salesReturn)
                ->with('success', sprintf('تم اعتماد مرتجع المبيعات (%s) بنجاح واستلام الأصناف في المخزون.', $salesReturn->return_number));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(
        SalesReturn $salesReturn,
        CancelSalesReturnAction $cancelAction
    ): RedirectResponse {
        try {
            $cancelAction->execute($salesReturn, (int) auth()->id());

            return redirect()->route('admin.sales-returns.show', $salesReturn)
                ->with('success', 'تم إلغاء مسودة المرتجع بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * AJAX endpoint: Get returnable items for an order
     */
    public function getOrderItems(Order $order, CalculateReturnableQuantityAction $calculateAction): JsonResponse
    {
        $order->load(['items.productVariant.product', 'items.unit', 'warehouse', 'currency', 'customer']);

        $items = [];
        $totalReturnable = 0.0;

        foreach ($order->items as $item) {
            $calc = $calculateAction->execute($item);
            $totalReturnable += $calc['returnable_quantity'];

            $items[] = [
                'order_item_id' => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->product_name_snapshot,
                'variant_name' => $item->variant_name_snapshot,
                'sku' => $item->sku_snapshot,
                'unit_name' => $item->unit_name_snapshot,
                'issued_quantity' => $calc['issued_quantity'],
                'already_returned_quantity' => $calc['already_returned_quantity'],
                'returnable_quantity' => $calc['returnable_quantity'],
                'unit_price' => (float) $item->unit_price,
            ];
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name_snapshot ?? $order->customer?->name,
                'warehouse_id' => $order->warehouse_id,
                'warehouse_name' => $order->warehouse?->name_ar,
                'currency_symbol' => $order->currency->symbol ?? $order->currency->code,
                'total_returnable' => round($totalReturnable, 4),
            ],
            'items' => $items,
        ]);
    }
}
