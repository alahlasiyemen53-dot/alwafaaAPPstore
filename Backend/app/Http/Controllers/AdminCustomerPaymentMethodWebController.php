<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentProviderCode;
use App\Http\Requests\Admin\CustomerPaymentMethodWebRequest;
use App\Models\CustomerPaymentMethod;
use App\Models\ElectronicWalletAccount;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class AdminCustomerPaymentMethodWebController extends Controller
{
    public function index(Request $request): View
    {
        $query = CustomerPaymentMethod::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $active = $request->string('status')->toString() === 'active';
            $query->where('is_active', $active);
        }

        return view('admin.master-data.customer-payment-methods.index', [
            'methods' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.customer-payment-methods.form', [
            'method' => null,
            'electronicWallets' => $this->activeElectronicWallets(),
        ]);
    }

    public function store(CustomerPaymentMethodWebRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->normalizedPayload($request);
        $method = CustomerPaymentMethod::query()->create($data);

        if ($request->hasFile('logo')) {
            $method->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        $audit->record('admin.customer_payment_methods.created', $method, $request);

        return redirect()->route('admin.master-data.customer-payment-methods.index')
            ->with('success', 'تم إنشاء طريقة الدفع بنجاح.');
    }

    public function edit(CustomerPaymentMethod $method): View
    {
        return view('admin.master-data.customer-payment-methods.form', [
            'method' => $method,
            'electronicWallets' => $this->activeElectronicWallets(),
        ]);
    }

    public function update(CustomerPaymentMethodWebRequest $request, CustomerPaymentMethod $method, AuditLogger $audit): RedirectResponse
    {
        $data = $this->normalizedPayload($request);
        $method->update($data);

        if ($request->hasFile('logo')) {
            $method->clearMediaCollection('logo');
            $method->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        $audit->record('admin.customer_payment_methods.updated', $method, $request);

        return redirect()->route('admin.master-data.customer-payment-methods.index')
            ->with('success', 'تم تحديث طريقة الدفع بنجاح.');
    }

    public function activate(CustomerPaymentMethod $method, AuditLogger $audit): RedirectResponse
    {
        $method->update(['is_active' => true]);
        $audit->record('admin.customer_payment_methods.activated', $method, request());

        return back()->with('success', 'تم تفعيل طريقة الدفع.');
    }

    public function deactivate(CustomerPaymentMethod $method, AuditLogger $audit): RedirectResponse
    {
        $method->update(['is_active' => false]);
        $audit->record('admin.customer_payment_methods.deactivated', $method, request());

        return back()->with('success', 'تم تعطيل طريقة الدفع.');
    }

    public function uploadLogo(Request $request, CustomerPaymentMethod $method): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        $method->clearMediaCollection('logo');
        $method->addMediaFromRequest('logo')->toMediaCollection('logo');

        return back()->with('success', 'تم رفع الشعار بنجاح.');
    }

    public function deleteLogo(CustomerPaymentMethod $method): RedirectResponse
    {
        $method->clearMediaCollection('logo');

        return back()->with('success', 'تم حذف الشعار.');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(CustomerPaymentMethodWebRequest $request): array
    {
        $data = $request->validated();
        unset($data['logo']);
        $data['is_active'] = $request->boolean('is_active');

        $provider = (string) ($data['provider_code'] ?? '');
        if ($provider === PaymentProviderCode::Cod->value) {
            $data['electronic_wallet_account_id'] = null;
        }

        return $data;
    }

    /** @return Collection<int, ElectronicWalletAccount> */
    private function activeElectronicWallets(): Collection
    {
        return ElectronicWalletAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }
}
