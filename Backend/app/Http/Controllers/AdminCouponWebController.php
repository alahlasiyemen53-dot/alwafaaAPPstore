<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCouponAction;
use App\Actions\ToggleCouponStatusAction;
use App\Actions\UpdateCouponAction;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminCouponWebController extends Controller
{
    private function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بالقيام بهذا الإجراء.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizePermission('admin.coupons.view');

        $query = Coupon::query()->withCount('activeUsages');

        if ($request->filled('search')) {
            $term = '%'.trim($request->string('search')->toString()).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        }

        if ($request->filled('discount_type')) {
            $query->where('discount_type', $request->string('discount_type')->toString());
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $coupons = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.marketing.coupons.index', [
            'coupons' => $coupons,
            'filters' => $request->only(['search', 'discount_type', 'is_active']),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('admin.coupons.create');

        return view('admin.marketing.coupons.create', [
            'products' => Product::query()->where('status', 'active')->orderBy('name_ar')->get(['id', 'name_ar']),
            'variants' => ProductVariant::query()->with('product')->where('status', 'active')->get(['id', 'product_id', 'sku']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
        ]);
    }

    public function store(StoreCouponRequest $request, CreateCouponAction $action): RedirectResponse
    {
        $this->authorizePermission('admin.coupons.create');

        $coupon = $action->execute($request->toDto());

        return redirect()
            ->route('admin.marketing.coupons.index')
            ->with('success', sprintf('تم إنشاء كوبون الخصم (%s) بنجاح.', $coupon->code));
    }

    public function edit(Coupon $coupon): View
    {
        $this->authorizePermission('admin.coupons.update');

        $coupon->load('targets');

        return view('admin.marketing.coupons.edit', [
            'coupon' => $coupon,
            'products' => Product::query()->where('status', 'active')->orderBy('name_ar')->get(['id', 'name_ar']),
            'variants' => ProductVariant::query()->with('product')->where('status', 'active')->get(['id', 'product_id', 'sku']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon, UpdateCouponAction $action): RedirectResponse
    {
        $this->authorizePermission('admin.coupons.update');

        $action->execute($coupon, $request->toDto());

        return redirect()
            ->route('admin.marketing.coupons.index')
            ->with('success', sprintf('تم تحديث كوبون الخصم (%s) بنجاح.', $coupon->code));
    }

    public function toggle(Coupon $coupon, ToggleCouponStatusAction $action): RedirectResponse
    {
        $permission = $coupon->is_active ? 'admin.coupons.deactivate' : 'admin.coupons.activate';
        $this->authorizePermission($permission);

        $action->execute($coupon);

        $statusMsg = $coupon->is_active ? 'تفعيل' : 'تعطيل';

        return redirect()
            ->back()
            ->with('success', sprintf('تم %s كوبون الخصم بنجاح.', $statusMsg));
    }

    public function usages(Coupon $coupon, Request $request): View
    {
        $this->authorizePermission('admin.coupons.view');

        $usages = $coupon->usages()
            ->with(['customer', 'order', 'currency'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.marketing.coupons.usages', [
            'coupon' => $coupon,
            'usages' => $usages,
        ]);
    }
}
