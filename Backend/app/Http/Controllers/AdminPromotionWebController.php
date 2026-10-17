<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePromotionAction;
use App\Actions\TogglePromotionStatusAction;
use App\Actions\UpdatePromotionAction;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminPromotionWebController extends Controller
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
        $this->authorizePermission('admin.promotions.view');

        $query = Promotion::query()->withCount('targets');

        if ($request->filled('search')) {
            $term = '%'.trim($request->string('search')->toString()).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        }

        if ($request->filled('scope')) {
            $query->where('scope', $request->string('scope')->toString());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $promotions = $query->orderByDesc('priority')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.marketing.promotions.index', [
            'promotions' => $promotions,
            'filters' => $request->only(['search', 'scope', 'type', 'is_active']),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('admin.promotions.create');

        return view('admin.marketing.promotions.create', [
            'products' => Product::query()->where('status', 'active')->orderBy('name_ar')->get(['id', 'name_ar']),
            'variants' => ProductVariant::query()->with('product')->where('status', 'active')->get(['id', 'product_id', 'sku']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
        ]);
    }

    public function store(StorePromotionRequest $request, CreatePromotionAction $action): RedirectResponse
    {
        $this->authorizePermission('admin.promotions.create');

        $promotion = $action->execute($request->toDto());

        return redirect()
            ->route('admin.marketing.promotions.index')
            ->with('success', sprintf('تم إنشاء العرض الترويجي (%s) بنجاح.', $promotion->name));
    }

    public function edit(Promotion $promotion): View
    {
        $this->authorizePermission('admin.promotions.update');

        $promotion->load('targets');

        return view('admin.marketing.promotions.edit', [
            'promotion' => $promotion,
            'products' => Product::query()->where('status', 'active')->orderBy('name_ar')->get(['id', 'name_ar']),
            'variants' => ProductVariant::query()->with('product')->where('status', 'active')->get(['id', 'product_id', 'sku']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
        ]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion, UpdatePromotionAction $action): RedirectResponse
    {
        $this->authorizePermission('admin.promotions.update');

        $action->execute($promotion, $request->toDto());

        return redirect()
            ->route('admin.marketing.promotions.index')
            ->with('success', sprintf('تم تحديث العرض الترويجي (%s) بنجاح.', $promotion->name));
    }

    public function toggle(Promotion $promotion, TogglePromotionStatusAction $action): RedirectResponse
    {
        $permission = $promotion->is_active ? 'admin.promotions.deactivate' : 'admin.promotions.activate';
        $this->authorizePermission($permission);

        $action->execute($promotion);

        $statusMsg = $promotion->is_active ? 'تفعيل' : 'تعطيل';

        return redirect()
            ->back()
            ->with('success', sprintf('تم %s العرض الترويجي بنجاح.', $statusMsg));
    }
}
