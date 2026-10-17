<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AdminCostPriceWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission('admin.products.update');

        $query = ProductVariant::query()
            ->with(['product', 'supplier'])
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->select('product_variants.*')
            ->orderBy('products.name_ar')
            ->orderBy('product_variants.sku');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('product_variants.sku', 'like', "%{$search}%")
                    ->orWhere('products.name_ar', 'like', "%{$search}%")
                    ->orWhere('products.name_en', 'like', "%{$search}%");
            });
        }

        $variants = $query->paginate(40)->withQueryString();

        return view('admin.cost_prices.index', [
            'variants' => $variants,
            'filters' => ['search' => $search ?? ''],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizePermission('admin.products.update');

        $validated = $request->validate([
            'costs' => ['required', 'array'],
            'costs.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['costs'] as $variantId => $cost) {
                ProductVariant::query()
                    ->whereKey((int) $variantId)
                    ->update(['cost_price' => max(0, (float) ($cost ?? 0))]);
            }
        });

        return back()->with('success', 'تم حفظ أسعار الشراء بنجاح.');
    }

    private function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بتنفيذ هذه العملية.');
        }
    }
}
