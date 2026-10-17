<?php

namespace App\Http\Controllers;

use App\Actions\CreateCustomerAddressAction;
use App\Actions\DeleteCustomerAddressAction;
use App\Actions\SetDefaultCustomerAddressAction;
use App\Actions\UpdateCustomerAddressAction;
use App\Data\CustomerAddressData;
use App\Http\Requests\Admin\StoreCustomerAddressRequest;
use App\Http\Requests\Admin\UpdateCustomerAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminCustomerAddressWebController extends Controller
{
    public function store(StoreCustomerAddressRequest $request, Customer $customer, CreateCustomerAddressAction $action): RedirectResponse
    {
        $action->execute($customer, CustomerAddressData::fromValidated($request->validated()));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'تمت إضافة العنوان بنجاح.');
    }

    public function update(UpdateCustomerAddressRequest $request, Customer $customer, CustomerAddress $address, UpdateCustomerAddressAction $action): RedirectResponse
    {
        abort_if($address->customer_id !== $customer->id, 404);

        $action->execute($address, CustomerAddressData::fromValidated($request->validated()));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'تم تحديث بيانات العنوان بنجاح.');
    }

    public function destroy(Request $request, Customer $customer, CustomerAddress $address, DeleteCustomerAddressAction $action): RedirectResponse
    {
        abort_if(! $request->user()?->hasPermission('admin.customer_addresses.delete'), 403);
        abort_if($address->customer_id !== $customer->id, 404);

        $action->execute($address);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'تم حذف العنوان بنجاح.');
    }

    public function setDefault(Request $request, Customer $customer, CustomerAddress $address, SetDefaultCustomerAddressAction $action): RedirectResponse
    {
        abort_if(! $request->user()?->hasPermission('admin.customer_addresses.set_default'), 403);
        abort_if($address->customer_id !== $customer->id, 404);

        $action->execute($address);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'تم تعيين العنوان كافتراضي بنجاح.');
    }
}
