<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HomeSectionType;
use App\Enums\ProductStatus;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductCollectionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AdminProductCollectionWebController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $collections = ProductCollection::query()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name_ar', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.marketing.collections.index', [
            'collections' => $collections,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.marketing.collections.form', [
            'collection' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $collection = ProductCollection::query()->create($data);
        Cache::forget('customer.home.legacy.v1');

        return redirect()
            ->route('admin.marketing.collections.products', $collection)
            ->with('success', 'تم إنشاء المجموعة. أضف المنتجات الآن.');
    }

    public function edit(ProductCollection $collection): View
    {
        return view('admin.marketing.collections.form', [
            'collection' => $collection,
        ]);
    }

    public function update(Request $request, ProductCollection $collection): RedirectResponse
    {
        $collection->update($this->validated($request, $collection));
        Cache::forget('customer.home.legacy.v1');

        return redirect()
            ->route('admin.marketing.collections.index')
            ->with('success', 'تم تحديث المجموعة.');
    }

    public function destroy(ProductCollection $collection): RedirectResponse
    {
        $collectionId = $collection->id;

        // Detach from any home section that points at this collection.
        HomeSection::query()
            ->where('type', HomeSectionType::Collection->value)
            ->get()
            ->each(function (HomeSection $section) use ($collectionId): void {
                $config = $section->configuration ?? [];
                if ((int) ($config['collection_id'] ?? 0) !== $collectionId) {
                    return;
                }
                $config['collection_id'] = null;
                $section->update([
                    'configuration' => $config,
                    'is_active' => false,
                ]);
            });

        $collection->clearMediaCollection('collection_cover');
        $collection->delete();
        Cache::forget('customer.home.legacy.v1');

        return redirect()
            ->route('admin.marketing.collections.index')
            ->with('success', 'تم حذف المجموعة.');
    }

    public function products(Request $request, ProductCollection $collection): View
    {
        $search = trim((string) $request->input('search', ''));

        $assigned = $collection->items()
            ->with(['product:id,name_ar,name_en,slug,status'])
            ->orderBy('sort_order')
            ->get();

        $candidates = Product::query()
            ->select(['id', 'name_ar', 'name_en', 'slug', 'status'])
            ->where('status', ProductStatus::Active)
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name_ar', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->whereNotIn('id', $assigned->pluck('product_id'))
            ->orderBy('name_ar')
            ->paginate(20)
            ->withQueryString();

        return view('admin.marketing.collections.products', [
            'collection' => $collection,
            'assigned' => $assigned,
            'candidates' => $candidates,
            'search' => $search,
        ]);
    }

    public function addProduct(Request $request, ProductCollection $collection): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $maxSort = (int) $collection->items()->max('sort_order');

        ProductCollectionItem::query()->firstOrCreate(
            [
                'collection_id' => $collection->id,
                'product_id' => (int) $data['product_id'],
            ],
            ['sort_order' => $maxSort + 1],
        );

        Cache::forget('customer.home.legacy.v1');

        return back()->with('success', 'تمت إضافة المنتج إلى المجموعة.');
    }

    public function removeProduct(ProductCollection $collection, Product $product): RedirectResponse
    {
        ProductCollectionItem::query()
            ->where('collection_id', $collection->id)
            ->where('product_id', $product->id)
            ->delete();

        Cache::forget('customer.home.legacy.v1');

        return back()->with('success', 'تمت إزالة المنتج من المجموعة.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ProductCollection $collection = null): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($data['name_en'] ?: $data['name_ar']) ?: 'collection-'.Str::random(6);
        }

        return [
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'slug' => $slug,
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => auth()->user()?->hasPermission('admin.product_collections.activate')
                ? $request->boolean('is_active')
                : ($collection?->is_active ?? true),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }
}
