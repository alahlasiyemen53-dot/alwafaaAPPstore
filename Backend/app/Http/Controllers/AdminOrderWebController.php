<?php

namespace App\Http\Controllers;

use App\Actions\CalculateOrderTotalsAction;
use App\Actions\CancelOrderAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\Actions\UpdateDraftOrderAction;
use App\Enums\OrderStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\ProductVariantStatus;
use App\Http\Requests\Admin\CancelOrderRequest;
use App\Http\Requests\Admin\PreviewOrderPricingRequest;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\CurrencyConverter;
use App\Services\Delivery\GovernorateDeliveryAvailability;
use App\Support\DateFilterNormalizer;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminOrderWebController extends Controller
{
    public function index(Request $request): View
    {
        $query = Order::query()
            ->with(['customer', 'warehouse', 'currency'])
            ->withCount('items')
            ->latest('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('customer_phone_snapshot', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($currencyId = $request->input('currency_id')) {
            $query->where('currency_id', $currencyId);
        }

        if ($paymentStatus = $request->input('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($fromDate = $request->input('from_date')) {
            $normFrom = DateFilterNormalizer::normalize($fromDate);
            if ($normFrom) {
                $query->whereDate('created_at', '>=', $normFrom);
            }
        }

        if ($toDate = $request->input('to_date')) {
            $normTo = DateFilterNormalizer::normalize($toDate);
            if ($normTo) {
                $query->whereDate('created_at', '<=', $normTo);
            }
        }

        $orders = $query->paginate(15)->withQueryString();

        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name_ar')->get();
        $currencies = Currency::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('admin.orders.index', [
            'orders' => $orders,
            'warehouses' => $warehouses,
            'currencies' => $currencies,
            'statuses' => OrderStatus::cases(),
            'paymentStatuses' => OrderPaymentStatus::cases(),
        ]);
    }

    public function create(CurrencyConverter $currencyConverter): View
    {
        $customers = Customer::query()
            ->where('is_active', true)
            ->with(['addresses.governorate', 'addresses.city', 'addresses.district'])
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        $currencies = Currency::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        $baseCurrency = $currencyConverter->getBaseCurrency();

        $currencyRates = [];
        foreach ($currencies as $curr) {
            $conv = $currencyConverter->convert(1, $baseCurrency, $curr);
            $currencyRates[$curr->id] = [
                'rate' => $conv->isAvailable ? (float) $conv->amount : 1.0,
                'is_available' => $conv->isAvailable,
                'symbol' => $curr->symbol ?? $curr->code,
                'decimal_places' => $curr->decimal_places ?? 2,
            ];
        }

        $variants = ProductVariant::query()
            ->where('status', ProductVariantStatus::Active)
            ->whereHas('product', fn ($q) => $q->where('status', 'active'))
            ->with(['product', 'prices', 'attributeValues.attribute'])
            ->get();

        return view('admin.orders.create', [
            'customers' => $customers,
            'warehouses' => $warehouses,
            'currencies' => $currencies,
            'currencyRates' => $currencyRates,
            'baseCurrency' => $baseCurrency,
            'variants' => $variants,
        ]);
    }

    public function store(
        StoreOrderRequest $request,
        CreateOrderAction $createAction,
        ConfirmOrderAction $confirmAction
    ): RedirectResponse {
        try {
            $order = $createAction->execute($request->toDto());

            if ($request->input('action') === 'confirm') {
                $order = $confirmAction->execute($order);

                return redirect()->route('admin.orders.show', $order)
                    ->with('success', "تم إنشاء وتأكيد طلب المبيعات رقم {$order->order_number} وحجز كميات المخزون بنجاح.");
            }

            return redirect()->route('admin.orders.show', $order)
                ->with('success', "تم حفظ مسودة طلب المبيعات رقم {$order->order_number} بنجاح.");
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Order $order): View
    {
        $order->load([
            'customer',
            'warehouse',
            'currency',
            'deliveryAddress.governorate',
            'deliveryAddress.city',
            'deliveryAddress.district',
            'items.productVariant.product',
            'items.productVariant.product.media',
            'items.productVariant.media',
            'items.productVariant.supplier',
            'items.sourcingSupplier',
            'items.unit',
            'reservations.warehouse',
            'reservations.productVariant',
            'deliveries.driver',
            'purchaseOrders.supplier',
            'purchaseOrders.assignedDriver',
            'purchaseOrders.items',
            'pickupDriver',
            'creator',
            'updater',
            'confirmer',
            'canceller',
        ]);

        $activeDrivers = Driver::query()->where('is_active', true)->orderBy('name')->get();
        $suppliers = \App\Models\Supplier::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.orders.show', [
            'order' => $order,
            'activeDrivers' => $activeDrivers,
            'suppliers' => $suppliers,
        ]);
    }

    public function print(Order $order): View
    {
        $order->load([
            'customer',
            'warehouse',
            'currency',
            'deliveryAddress.governorate',
            'deliveryAddress.city',
            'deliveryAddress.district',
            'items.unit',
        ]);

        $appName = (string) (Setting::query()->where('key', 'application_name')->value('value') ?? 'الوفاء استور');

        return view('admin.orders.print', [
            'order' => $order,
            'appName' => $appName,
            'supportPhone' => Setting::query()->where('key', 'support_phone')->value('value'),
        ]);
    }

    public function edit(Order $order, CurrencyConverter $currencyConverter): View|RedirectResponse
    {
        if (! $order->canBeEdited()) {
            return redirect()->route('admin.orders.show', $order)
                ->with('error', 'لا يمكن تعديل الطلب لأنه ليس في حالة مسودة.');
        }

        $order->load(['items.productVariant.product', 'deliveryAddress']);

        $customers = Customer::query()
            ->where('is_active', true)
            ->with(['addresses.governorate', 'addresses.city', 'addresses.district'])
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        $currencies = Currency::query()
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        $baseCurrency = $currencyConverter->getBaseCurrency();

        $currencyRates = [];
        foreach ($currencies as $curr) {
            $conv = $currencyConverter->convert(1, $baseCurrency, $curr);
            $currencyRates[$curr->id] = [
                'rate' => $conv->isAvailable ? (float) $conv->amount : 1.0,
                'is_available' => $conv->isAvailable,
                'symbol' => $curr->symbol ?? $curr->code,
                'decimal_places' => $curr->decimal_places ?? 2,
            ];
        }

        $variants = ProductVariant::query()
            ->where('status', ProductVariantStatus::Active)
            ->whereHas('product', fn ($q) => $q->where('status', 'active'))
            ->with(['product', 'prices', 'attributeValues.attribute'])
            ->get();

        return view('admin.orders.edit', [
            'order' => $order,
            'customers' => $customers,
            'warehouses' => $warehouses,
            'currencies' => $currencies,
            'currencyRates' => $currencyRates,
            'baseCurrency' => $baseCurrency,
            'variants' => $variants,
        ]);
    }

    public function update(
        UpdateOrderRequest $request,
        Order $order,
        UpdateDraftOrderAction $updateAction,
        ConfirmOrderAction $confirmAction
    ): RedirectResponse {
        try {
            $updated = $updateAction->execute($order, $request->toDto());

            if ($request->input('action') === 'confirm') {
                $confirmed = $confirmAction->execute($updated);

                return redirect()->route('admin.orders.show', $confirmed)
                    ->with('success', "تم تحديث وتأكيد طلب المبيعات رقم {$confirmed->order_number} وحجز الكميات بنجاح.");
            }

            return redirect()->route('admin.orders.show', $updated)
                ->with('success', "تم تحديث مسودة طلب المبيعات رقم {$updated->order_number} بنجاح.");
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function confirm(Order $order, ConfirmOrderAction $action): RedirectResponse
    {
        try {
            $confirmed = $action->execute($order);

            return redirect()->route('admin.orders.show', $confirmed)
                ->with('success', "تم تأكيد طلب المبيعات رقم {$confirmed->order_number} وحجز الكميات المطلوبة في المخزون بنجاح.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(CancelOrderRequest $request, Order $order, CancelOrderAction $action): RedirectResponse
    {
        try {
            $cancelled = $action->execute($order, $request->toDto());

            return redirect()->route('admin.orders.show', $cancelled)
                ->with('success', "تم إلغاء طلب المبيعات رقم {$cancelled->order_number} وتحرير أي كميات محجوزة بنجاح.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function assignPickupDriver(
        \App\Http\Requests\Admin\AssignOrderPickupDriverRequest $request,
        Order $order,
        \App\Actions\AssignOrderPickupDriverAction $action,
    ): RedirectResponse {
        try {
            $updated = $action->execute($order, (int) $request->validated('driver_id'), (int) auth()->id());

            return redirect()->route('admin.orders.show', $updated)
                ->with('success', 'تم إسناد مندوب الاستلام من الموردين بنجاح.');
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function customerAddresses(Customer $customer): JsonResponse
    {
        $addresses = $customer->addresses()
            ->with(['governorate', 'city', 'district'])
            ->get()
            ->map(fn ($addr) => [
                'id' => $addr->id,
                'label' => $addr->label ?? 'عنوان',
                'contact_name' => $addr->contact_name,
                'contact_phone' => $addr->contact_phone,
                'is_default' => (bool) $addr->is_default,
                'formatted' => $addr->formatted_address ?: implode(' - ', array_filter([
                    $addr->governorate?->name_ar,
                    $addr->city?->name_ar,
                    $addr->district?->name_ar,
                    $addr->neighborhood,
                    $addr->street,
                ])),
            ]);

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'is_active' => (bool) $customer->is_active,
                'phone_verified' => $customer->isPhoneVerified(),
                'phone_verification_method' => $customer->phone_verification_method?->label(),
            ],
            'addresses' => $addresses,
        ]);
    }

    public function variantStock(Request $request): JsonResponse
    {
        $warehouseId = (int) $request->query('warehouse_id');
        $variantId = (int) $request->query('product_variant_id');

        /** @var Inventory|null $inventory */
        $inventory = Inventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->first();

        $quantity = $inventory ? (float) $inventory->quantity : 0.0;
        $reserved = $inventory ? (float) $inventory->reserved_quantity : 0.0;
        $available = max(0.0, $quantity - $reserved);

        return response()->json([
            'success' => true,
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
            'available_quantity' => $available,
        ]);
    }

    public function previewPricing(
        PreviewOrderPricingRequest $request,
        CalculateOrderTotalsAction $calculateTotalsAction,
        GovernorateDeliveryAvailability $governorateDeliveryAvailability
    ): JsonResponse {
        $user = $request->user();
        if (! $user || ! ($user->hasPermission('admin.orders.create') || $user->hasPermission('admin.orders.update'))) {
            abort(403);
        }

        try {
            $currencyId = (int) $request->validated('currency_id');
            $couponCode = $request->filled('coupon_code') ? (string) $request->validated('coupon_code') : null;
            $customerId = (int) $request->validated('customer_id');
            $itemsData = $request->toOrderItemsData();

            $deliveryFeeBase = null;
            if ($request->filled('customer_address_id')) {
                $address = CustomerAddress::query()->with('governorate')->find($request->input('customer_address_id'));
                if ($address && $address->governorate && $address->governorate->is_active) {
                    $methodCode = $request->filled('shipping_method')
                        ? trim((string) $request->input('shipping_method'))
                        : 'standard';
                    $assignment = $governorateDeliveryAvailability->resolve($address->governorate->id, $methodCode);
                    if ($assignment === null) {
                        return response()->json([
                            'error_message' => 'طريقة التوصيل المحددة غير متاحة للمحافظة المحددة.',
                        ], 422);
                    }
                    $deliveryFeeBase = (float) $assignment->delivery_fee;
                }
            }

            $result = $calculateTotalsAction->execute(
                currencyId: $currencyId,
                itemsData: $itemsData,
                couponCode: $couponCode,
                customerId: $customerId,
                lockForUpdate: false,
                deliveryFeeBase: $deliveryFeeBase,
            );

            return response()->json($result);
        } catch (DomainException $e) {
            return response()->json([
                'error_message' => $e->getMessage(),
            ], 422);
        }
    }
}
