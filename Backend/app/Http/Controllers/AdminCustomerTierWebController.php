<?php

namespace App\Http\Controllers;

use App\Actions\CreateCustomerTierAction;
use App\Actions\DeleteCustomerTierAction;
use App\Actions\UpdateCustomerTierAction;
use App\Http\Requests\Admin\StoreCustomerTierRequest;
use App\Http\Requests\Admin\UpdateCustomerTierRequest;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Queries\AdminCustomerTierQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminCustomerTierWebController extends Controller
{
    public function index(Request $request, AdminCustomerTierQuery $query): View
    {
        $tiers = $query->paginate($request);

        $totalTiers = CustomerTier::query()->count();
        $activeTiers = CustomerTier::query()->where('is_active', true)->count();
        $totalAssignedCustomers = Customer::query()->whereNotNull('current_tier_id')->count();

        return view('admin.customer_tiers.index', [
            'tiers' => $tiers,
            'totalTiers' => $totalTiers,
            'activeTiers' => $activeTiers,
            'totalAssignedCustomers' => $totalAssignedCustomers,
        ]);
    }

    public function create(): View
    {
        return view('admin.customer_tiers.create');
    }

    public function store(StoreCustomerTierRequest $request, CreateCustomerTierAction $action): RedirectResponse
    {
        $tier = $action->execute($request->validated());

        return redirect()
            ->route('admin.customer-tiers.index')
            ->with('success', "تم إنشاء المستوى '{$tier->name_ar}' بنجاح.");
    }

    public function edit(CustomerTier $tier): View
    {
        return view('admin.customer_tiers.edit', [
            'tier' => $tier,
        ]);
    }

    public function update(UpdateCustomerTierRequest $request, CustomerTier $tier, UpdateCustomerTierAction $action): RedirectResponse
    {
        $action->execute($tier, $request->validated());

        return redirect()
            ->route('admin.customer-tiers.index')
            ->with('success', "تم تحديث المستوى '{$tier->name_ar}' بنجاح.");
    }

    public function destroy(CustomerTier $tier, DeleteCustomerTierAction $action): RedirectResponse
    {
        $tierName = $tier->name_ar;
        $action->execute($tier);

        return redirect()
            ->route('admin.customer-tiers.index')
            ->with('success', "تم حذف المستوى '{$tierName}' بنجاح.");
    }

    public function toggleStatus(CustomerTier $tier, UpdateCustomerTierAction $action): RedirectResponse
    {
        $action->execute($tier, [
            'is_active' => ! $tier->is_active,
        ]);

        $statusText = $tier->fresh()->is_active ? 'تفعيل' : 'تعطيل';

        return redirect()
            ->route('admin.customer-tiers.index')
            ->with('success', "تم {$statusText} المستوى '{$tier->name_ar}' بنجاح.");
    }
}
