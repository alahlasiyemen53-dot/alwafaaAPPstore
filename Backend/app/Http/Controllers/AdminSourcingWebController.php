<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssignSourcingToDriverAction;
use App\Actions\CancelSourcingAction;
use App\Actions\ConfirmSourcingCollectedAction;
use App\Actions\MarkSourcingFailedAction;
use App\Enums\SourcingStatus;
use App\Http\Requests\Admin\AssignSourcingDriverRequest;
use App\Http\Requests\Admin\CancelSourcingRequest;
use App\Http\Requests\Admin\MarkSourcingFailedRequest;
use App\Models\Driver;
use App\Models\OrderSourcingRequirement;
use App\Models\Supplier;
use App\Support\DateFilterNormalizer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminSourcingWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission('admin.sourcing.view');

        $query = OrderSourcingRequirement::query()
            ->with([
                'order.customer',
                'orderItem',
                'productVariant.product',
                'supplier',
                'assignedDriver',
            ])
            ->latest('id');

        if ($search = $request->input('search')) {
            $term = trim($search);
            $query->where(function ($q) use ($term): void {
                $q->whereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$term}%"))
                    ->orWhereHas('productVariant', fn ($vq) => $vq->where('sku', 'like', "%{$term}%")
                        ->orWhere('supplier_item_number', 'like', "%{$term}%")
                        ->orWhereHas('product', fn ($pq) => $pq->where('name_ar', 'like', "%{$term}%")->orWhere('name_en', 'like', "%{$term}%")))
                    ->orWhereHas('orderItem', fn ($oiq) => $oiq->where('supplier_item_number_snapshot', 'like', "%{$term}%"))
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('assignedDriver', fn ($dq) => $dq->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
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

        $requirements = $query->paginate(15)->withQueryString();

        $metrics = [
            'pending' => OrderSourcingRequirement::query()->where('status', SourcingStatus::Pending)->count(),
            'assigned' => OrderSourcingRequirement::query()->where('status', SourcingStatus::Assigned)->count(),
            'collected' => OrderSourcingRequirement::query()->where('status', SourcingStatus::Collected)->count(),
            'failed' => OrderSourcingRequirement::query()->where('status', SourcingStatus::Failed)->count(),
        ];

        $drivers = Driver::query()->where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.sourcing.index', [
            'requirements' => $requirements,
            'metrics' => $metrics,
            'drivers' => $drivers,
            'suppliers' => $suppliers,
            'statuses' => SourcingStatus::cases(),
            'filters' => $request->only(['search', 'status', 'supplier_id', 'driver_id', 'date_from', 'date_to']),
            'dateError' => $dateError,
        ]);
    }

    public function assignDriver(
        AssignSourcingDriverRequest $request,
        OrderSourcingRequirement $requirement,
        AssignSourcingToDriverAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $action->execute(
                requirement: $requirement,
                driverId: (int) $request->validated('driver_id'),
                userId: (int) auth()->id(),
            );

            return back()->with('success', 'تم إسناد مهمة التوريد إلى مندوب التوصيل بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function confirmCollected(
        OrderSourcingRequirement $requirement,
        ConfirmSourcingCollectedAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $action->execute(
                requirement: $requirement,
                userId: (int) auth()->id(),
            );

            return back()->with('success', 'تم تأكيد استلام البند من المورد بنجاح؛ البند الآن في عهدة المندوب.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function markFailed(
        MarkSourcingFailedRequest $request,
        OrderSourcingRequirement $requirement,
        MarkSourcingFailedAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $action->execute(
                requirement: $requirement,
                failureReason: (string) $request->validated('failure_reason'),
                userId: (int) auth()->id(),
            );

            return back()->with('success', 'تم تسجيل تعذر استلام البند من المورد بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function cancel(
        CancelSourcingRequest $request,
        OrderSourcingRequirement $requirement,
        CancelSourcingAction $action,
    ): RedirectResponse {
        $this->authorizePermission('admin.sourcing.manage');

        try {
            $action->execute(
                requirement: $requirement,
                reason: $request->validated('cancellation_reason'),
                userId: (int) auth()->id(),
            );

            return back()->with('success', 'تم إلغاء مهمة التوريد بنجاح.');
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
