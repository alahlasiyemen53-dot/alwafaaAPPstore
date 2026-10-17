<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Admin\GovernorateDeliveryMethodWebRequest;
use App\Models\DeliveryMethod;
use App\Models\Governorate;
use App\Models\GovernorateDeliveryMethod;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminGovernorateDeliveryMethodWebController extends Controller
{
    public function manage(DeliveryMethod $deliveryMethod): View
    {
        $assignments = GovernorateDeliveryMethod::query()
            ->with('governorate')
            ->where('delivery_method_id', $deliveryMethod->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20);

        return view('admin.master-data.delivery-methods.manage', [
            'deliveryMethod' => $deliveryMethod,
            'assignments' => $assignments,
        ]);
    }

    public function index(Request $request): View
    {
        $query = GovernorateDeliveryMethod::query()
            ->with(['governorate', 'deliveryMethod'])
            ->orderBy('governorate_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($request->filled('governorate_id')) {
            $query->where('governorate_id', (int) $request->input('governorate_id'));
        }

        if ($request->filled('delivery_method_id')) {
            $query->where('delivery_method_id', (int) $request->input('delivery_method_id'));
        }

        if ($request->filled('status')) {
            $active = $request->string('status')->toString() === 'active';
            $query->where('is_active', $active);
        }

        return view('admin.master-data.governorate-delivery-methods.index', [
            'assignments' => $query->paginate(20)->withQueryString(),
            'governorates' => Governorate::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'filters' => $request->only(['governorate_id', 'delivery_method_id', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        $lockedMethodId = $request->filled('delivery_method_id')
            ? (int) $request->input('delivery_method_id')
            : null;

        return view('admin.master-data.governorate-delivery-methods.form', [
            'assignment' => null,
            'governorates' => Governorate::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
            'deliveryMethods' => DeliveryMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'code']),
            'lockedDeliveryMethodId' => $lockedMethodId,
            'returnToMethodId' => $lockedMethodId,
        ]);
    }

    public function store(GovernorateDeliveryMethodWebRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $assignment = GovernorateDeliveryMethod::query()->create($data);

        $audit->record('admin.governorate_delivery_methods.created', $assignment, $request);

        return $this->redirectAfterMutation((int) $assignment->delivery_method_id)
            ->with('success', 'تم إنشاء الربط بنجاح.');
    }

    public function edit(GovernorateDeliveryMethod $assignment): View
    {
        return view('admin.master-data.governorate-delivery-methods.form', [
            'assignment' => $assignment,
            'governorates' => Governorate::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'deliveryMethods' => DeliveryMethod::query()->orderBy('sort_order')->get(['id', 'name_ar', 'code']),
            'lockedDeliveryMethodId' => null,
            'returnToMethodId' => (int) $assignment->delivery_method_id,
        ]);
    }

    public function update(GovernorateDeliveryMethodWebRequest $request, GovernorateDeliveryMethod $assignment, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $assignment->update($data);

        $audit->record('admin.governorate_delivery_methods.updated', $assignment, $request);

        return $this->redirectAfterMutation((int) $assignment->delivery_method_id)
            ->with('success', 'تم تحديث الربط بنجاح.');
    }

    public function activate(GovernorateDeliveryMethod $assignment, AuditLogger $audit): RedirectResponse
    {
        $assignment->update(['is_active' => true]);
        $audit->record('admin.governorate_delivery_methods.activated', $assignment);

        return back()->with('success', 'تم تفعيل الربط.');
    }

    public function deactivate(GovernorateDeliveryMethod $assignment, AuditLogger $audit): RedirectResponse
    {
        $assignment->update(['is_active' => false]);
        $audit->record('admin.governorate_delivery_methods.deactivated', $assignment);

        return back()->with('success', 'تم تعطيل الربط.');
    }

    private function redirectAfterMutation(int $deliveryMethodId): RedirectResponse
    {
        if (request()->filled('return_to_method') || request()->boolean('from_method_manage')) {
            return redirect()->route('admin.master-data.delivery-methods.manage', $deliveryMethodId);
        }

        $referer = (string) request()->headers->get('referer', '');
        if (str_contains($referer, '/delivery-methods/') && str_contains($referer, '/manage')) {
            return redirect()->route('admin.master-data.delivery-methods.manage', $deliveryMethodId);
        }

        return redirect()->route('admin.master-data.governorate-delivery-methods.index');
    }
}
