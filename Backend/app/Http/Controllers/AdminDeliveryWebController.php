<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssignOrderToDriverAction;
use App\Actions\MarkDeliveryDeliveredAction;
use App\Actions\MarkDeliveryFailedAction;
use App\Actions\ReturnFailedDeliveryToWarehouseAction;
use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryStatus;
use App\Http\Requests\Admin\AssignOrderToDriverRequest;
use App\Http\Requests\Admin\MarkDeliveryFailedRequest;
use App\Http\Requests\Admin\ReturnDeliveryToWarehouseRequest;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Support\DateFilterNormalizer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminDeliveryWebController extends Controller
{
    public function index(Request $request): View
    {
        $query = Delivery::query()
            ->with(['order.customer', 'order.currency', 'driver', 'creator'])
            ->latest('id');

        if ($search = $request->input('search')) {
            $term = trim($search);
            $query->where(function ($q) use ($term): void {
                $q->where('delivery_number', 'like', "%{$term}%")
                    ->orWhere('recipient_name', 'like', "%{$term}%")
                    ->orWhere('recipient_phone', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$term}%"))
                    ->orWhereHas('driver', fn ($dq) => $dq->where('name', 'like', "%{$term}%")->orWhere('driver_code', 'like', "%{$term}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($driverId = $request->input('driver_id')) {
            $query->where('driver_id', $driverId);
        }

        if ($fromDate = $request->input('from_date')) {
            $normalizedFrom = DateFilterNormalizer::normalize($fromDate);
            if ($normalizedFrom) {
                $query->whereDate('assigned_at', '>=', $normalizedFrom);
            }
        }

        if ($toDate = $request->input('to_date')) {
            $normalizedTo = DateFilterNormalizer::normalize($toDate);
            if ($normalizedTo) {
                $query->whereDate('assigned_at', '<=', $normalizedTo);
            }
        }

        $deliveries = $query->paginate(15)->withQueryString();

        $metrics = [
            'total' => Delivery::query()->count(),
            'assigned' => Delivery::query()->where('status', DeliveryStatus::Assigned)->count(),
            'delivered' => Delivery::query()->where('status', DeliveryStatus::Delivered)->count(),
            'failed' => Delivery::query()->where('status', DeliveryStatus::Failed)->count(),
            'returned' => Delivery::query()->where('status', DeliveryStatus::ReturnedToWarehouse)->count(),
        ];

        $drivers = Driver::query()->orderBy('name')->get();

        return view('admin.deliveries.index', [
            'deliveries' => $deliveries,
            'metrics' => $metrics,
            'drivers' => $drivers,
            'statuses' => DeliveryStatus::cases(),
            'filters' => $request->only(['search', 'status', 'driver_id', 'from_date', 'to_date']),
        ]);
    }

    public function show(Delivery $delivery): View
    {
        $delivery->load([
            'order.customer',
            'order.warehouse',
            'order.currency',
            'order.items.productVariant.product',
            'driver',
            'creator',
            'failedBy',
        ]);

        return view('admin.deliveries.show', [
            'delivery' => $delivery,
            'failureReasons' => DeliveryFailureReason::cases(),
        ]);
    }

    public function assign(AssignOrderToDriverRequest $request, Order $order, AssignOrderToDriverAction $action): RedirectResponse
    {
        try {
            $delivery = $action->execute(
                order: $order,
                data: $request->toDto(),
                userId: (int) $request->user()?->id,
            );

            return redirect()->route('admin.deliveries.show', $delivery)
                ->with('success', "تم إسناد الطلب ({$order->order_number}) لمندوب التوصيل بنجاح، وتم تسجيل إذن خروج البضاعة من المستودع برقم توصيل ({$delivery->delivery_number})");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function markDelivered(Delivery $delivery, MarkDeliveryDeliveredAction $action): RedirectResponse
    {
        try {
            $action->execute(
                delivery: $delivery,
                codCollectedAmount: null,
                notes: null,
                userId: (int) auth()->id(),
            );

            return back()->with('success', "تم تأكيد تسليم الشحنة ({$delivery->delivery_number}) بنجاح إلى العميل.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function markFailed(MarkDeliveryFailedRequest $request, Delivery $delivery, MarkDeliveryFailedAction $action): RedirectResponse
    {
        try {
            $action->execute(
                delivery: $delivery,
                data: $request->toDto(),
                userId: (int) auth()->id(),
            );

            return back()->with('success', "تم تسجيل تعذر تسليم الشحنة ({$delivery->delivery_number}) بنجاح. البضاعة ما زالت في عهدة المندوب بانتظار إعادتها للمستودع.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function returnToWarehouse(ReturnDeliveryToWarehouseRequest $request, Delivery $delivery, ReturnFailedDeliveryToWarehouseAction $action): RedirectResponse
    {
        try {
            $action->execute(
                delivery: $delivery,
                data: $request->toDto(),
                userId: (int) auth()->id(),
            );

            return back()->with('success', "تم إرجاع بضاعة الشحنة ({$delivery->delivery_number}) إلى المستودع وزيادة المخزون الفعلي بنجاح وحركة وارد حركة إرجاع توصيل.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }
}
