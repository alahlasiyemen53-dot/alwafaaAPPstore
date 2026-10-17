<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use App\Models\ProductCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

final class AdminHomeSectionWebController extends Controller
{
    public function index(): View
    {
        return view('admin.marketing.home-sections.index', [
            'sections' => HomeSection::query()->ordered()->get(),
            'types' => HomeSectionType::cases(),
            'collections' => ProductCollection::query()->orderBy('name_ar')->get(['id', 'name_ar', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', HomeSectionType::values())],
            'title_ar' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'collection_id' => ['nullable', 'integer', 'exists:product_collections,id'],
        ]);

        HomeSection::query()->create([
            'type' => $data['type'],
            'title_ar' => $data['title_ar'],
            'title_en' => $data['title_en'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'configuration' => $data['type'] === HomeSectionType::Collection->value
                ? ['collection_id' => (int) ($data['collection_id'] ?? 0)]
                : [],
        ]);

        Cache::forget('customer.home.legacy.v1');

        return back()->with('success', 'تم إضافة قسم الصفحة الرئيسية.');
    }

    public function update(Request $request, HomeSection $homeSection): RedirectResponse
    {
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'collection_id' => ['nullable', 'integer', 'exists:product_collections,id'],
        ]);

        $config = $homeSection->configuration ?? [];
        if ($homeSection->type === HomeSectionType::Collection) {
            $config['collection_id'] = (int) ($data['collection_id'] ?? ($config['collection_id'] ?? 0));
        }

        $homeSection->update([
            'title_ar' => $data['title_ar'],
            'title_en' => $data['title_en'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? $homeSection->sort_order),
            'is_active' => $request->boolean('is_active', $homeSection->is_active),
            'configuration' => $config,
        ]);

        Cache::forget('customer.home.legacy.v1');

        return back()->with('success', 'تم تحديث قسم الصفحة الرئيسية.');
    }

    public function toggle(HomeSection $homeSection): RedirectResponse
    {
        $homeSection->update(['is_active' => ! $homeSection->is_active]);
        Cache::forget('customer.home.legacy.v1');

        return back()->with('success', $homeSection->is_active ? 'تم تفعيل القسم.' : 'تم تعطيل القسم.');
    }

    public function destroy(HomeSection $homeSection): RedirectResponse
    {
        $homeSection->delete();
        Cache::forget('customer.home.legacy.v1');

        return back()->with('success', 'تم حذف قسم الصفحة الرئيسية.');
    }
}
