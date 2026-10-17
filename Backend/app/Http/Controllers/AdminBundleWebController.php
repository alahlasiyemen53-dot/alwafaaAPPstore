<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBundleAction;
use App\Actions\ToggleBundleStatusAction;
use App\Actions\UpdateBundleAction;
use App\Http\Requests\StoreBundleRequest;
use App\Http\Requests\UpdateBundleRequest;
use App\Models\Bundle;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminBundleWebController extends Controller
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
        $this->authorizePermission('admin.bundles.view');

        $query = Bundle::query()->withCount('items');

        if ($request->filled('search')) {
            $term = '%'.trim($request->string('search')->toString()).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        }

        if ($request->filled('pricing_type')) {
            $query->where('pricing_type', $request->string('pricing_type')->toString());
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $bundles = $query->orderByDesc('priority')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.marketing.bundles.index', [
            'bundles' => $bundles,
            'filters' => $request->only(['search', 'pricing_type', 'is_active']),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('admin.bundles.create');

        $variants = ProductVariant::query()
            ->with(['product'])
            ->where('status', 'active')
            ->get();

        return view('admin.marketing.bundles.create', [
            'variants' => $variants,
        ]);
    }

    public function store(StoreBundleRequest $request, CreateBundleAction $action): RedirectResponse
    {
        $this->authorizePermission('admin.bundles.create');

        $bundle = $action->execute($request->toDto());

        return redirect()
            ->route('admin.marketing.bundles.index')
            ->with('success', sprintf('تم إنشاء باقة المنتجات (%s) بنجاح.', $bundle->name));
    }

    public function edit(Bundle $bundle): View
    {
        $this->authorizePermission('admin.bundles.update');

        $bundle->load(['items.productVariant.product']);

        $variants = ProductVariant::query()
            ->with(['product'])
            ->where('status', 'active')
            ->get();

        return view('admin.marketing.bundles.edit', [
            'bundle' => $bundle,
            'variants' => $variants,
        ]);
    }

    public function update(UpdateBundleRequest $request, Bundle $bundle, UpdateBundleAction $action): RedirectResponse
    {
        $this->authorizePermission('admin.bundles.update');

        $action->execute($bundle, $request->toDto());

        return redirect()
            ->route('admin.marketing.bundles.index')
            ->with('success', sprintf('تم تحديث باقة المنتجات (%s) بنجاح.', $bundle->name));
    }

    public function toggle(Bundle $bundle, ToggleBundleStatusAction $action): RedirectResponse
    {
        $permission = $bundle->is_active ? 'admin.bundles.deactivate' : 'admin.bundles.activate';
        $this->authorizePermission($permission);

        $action->execute($bundle);

        $statusMsg = $bundle->is_active ? 'تفعيل' : 'تعطيل';

        return redirect()
            ->back()
            ->with('success', sprintf('تم %s باقة المنتجات بنجاح.', $statusMsg));
    }
}
