<?php

namespace App\Http\Controllers;

use App\Actions\ActivateCustomerAction;
use App\Actions\AssignCustomerTierAction;
use App\Actions\CreateCustomerAction;
use App\Actions\DeactivateCustomerAction;
use App\Actions\UpdateCustomerAction;
use App\Actions\VerifyCustomerPhoneByAdminAction;
use App\Data\CustomerData;
use App\Http\Requests\Admin\AssignCustomerTierRequest;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\District;
use App\Models\Governorate;
use App\Models\User;
use App\Queries\GetCustomerTierHistoryQuery;
use App\Queries\GetCustomerWishlistQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminCustomerWebController extends Controller
{
    public function index(Request $request): View
    {
        $query = Customer::query()
            ->with(['tier:id,code,name_ar,name_en,is_default'])
            ->withCount('addresses')
            ->latest('id');

        if ($search = $request->input('search')) {
            $term = trim($search);
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('normalized_phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($verification = $request->input('verification')) {
            if ($verification === 'verified') {
                $query->whereNotNull('phone_verified_at');
            } elseif ($verification === 'unverified') {
                $query->whereNull('phone_verified_at');
            }
        }

        if ($method = $request->input('method')) {
            $query->where('phone_verification_method', $method);
        }

        if ($tierId = $request->input('tier_id')) {
            $query->where('current_tier_id', $tierId);
        }

        $customers = $query->paginate(15)->withQueryString();

        $metrics = [
            'total' => Customer::query()->count(),
            'active' => Customer::query()->where('is_active', true)->count(),
            'verified' => Customer::query()->whereNotNull('phone_verified_at')->count(),
            'unverified' => Customer::query()->whereNull('phone_verified_at')->count(),
        ];

        $availableTiers = CustomerTier::query()->orderBy('sort_order')->get(['id', 'name_ar', 'code']);

        return view('admin.customers.index', [
            'customers' => $customers,
            'metrics' => $metrics,
            'availableTiers' => $availableTiers,
            'filters' => $request->only(['search', 'status', 'verification', 'method', 'tier_id']),
        ]);
    }

    public function create(): View
    {
        return view('admin.customers.create');
    }

    public function store(StoreCustomerRequest $request, CreateCustomerAction $action): RedirectResponse
    {
        $customer = $action->execute(CustomerData::fromValidated($request->validated()));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'تم إنشاء حساب العميل بنجاح.');
    }

    public function show(
        Customer $customer,
        GetCustomerWishlistQuery $wishlistQuery,
        GetCustomerTierHistoryQuery $tierHistoryQuery,
    ): View {
        $customer->load([
            'phoneVerifier:id,name,email',
            'addresses.country',
            'addresses.governorate',
            'addresses.city',
            'addresses.district',
            'tier:id,code,name_ar,name_en,is_default',
            'currentTierMembership.assignedBy:id,name,email',
        ]);

        $countries = Country::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'code']);
        $governorates = Governorate::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'country_id']);
        $cities = City::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'governorate_id']);
        $districts = District::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'city_id']);

        $wishlistItems = $wishlistQuery->getBounded($customer, 10);
        $wishlistCount = $wishlistQuery->count($customer);

        $reviewsCount = $customer->reviews()->count();
        $recentReviews = $customer->reviews()
            ->with('product')
            ->latest('id')
            ->take(5)
            ->get();

        $tierHistory = $tierHistoryQuery->getBounded($customer, 20);
        $tierHistoryCount = $tierHistoryQuery->count($customer);
        $activeTiers = CustomerTier::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name_ar', 'name_en']);

        return view('admin.customers.show', [
            'customer' => $customer,
            'countries' => $countries,
            'governorates' => $governorates,
            'cities' => $cities,
            'districts' => $districts,
            'wishlistItems' => $wishlistItems,
            'wishlistCount' => $wishlistCount,
            'reviewsCount' => $reviewsCount,
            'recentReviews' => $recentReviews,
            'tierHistory' => $tierHistory,
            'tierHistoryCount' => $tierHistoryCount,
            'activeTiers' => $activeTiers,
            'googleMapsWebApiKey' => config('google_maps.web_api_key'),
        ]);
    }

    public function assignTier(
        AssignCustomerTierRequest $request,
        Customer $customer,
        AssignCustomerTierAction $action,
    ): RedirectResponse {
        $targetTier = CustomerTier::query()->findOrFail($request->validated('tier_id'));

        $action->execute(
            customer: $customer,
            targetTier: $targetTier,
            reason: $request->validated('reason'),
            assignedBy: auth()->user(),
        );

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', "تم تغيير مستوى العميل إلى '{$targetTier->name_ar}' بنجاح.");
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', [
            'customer' => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomerAction $action): RedirectResponse
    {
        $action->execute($customer, CustomerData::fromValidated($request->validated()));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'تم تحديث بيانات العميل بنجاح.');
    }

    public function activate(Customer $customer, ActivateCustomerAction $action): RedirectResponse
    {
        $action->execute($customer);

        return back()->with('success', 'تم تفعيل حساب العميل بنجاح.');
    }

    public function deactivate(Customer $customer, DeactivateCustomerAction $action): RedirectResponse
    {
        $action->execute($customer);

        return back()->with('success', 'تم تعطيل حساب العميل بنجاح.');
    }

    public function verifyPhone(Request $request, Customer $customer, VerifyCustomerPhoneByAdminAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($customer, $user);

        return back()->with('success', 'تم تأكيد وتوثيق رقم هاتف العميل يدوياً بنجاح.');
    }
}
