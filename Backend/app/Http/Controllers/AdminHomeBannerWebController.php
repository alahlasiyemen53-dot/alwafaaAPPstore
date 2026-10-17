<?php

namespace App\Http\Controllers;

use App\Actions\CreateHomeBannerAction;
use App\Actions\DeleteHomeBannerAction;
use App\Actions\ToggleHomeBannerStatusAction;
use App\Actions\UpdateHomeBannerAction;
use App\Data\StoreHomeBannerData;
use App\Data\UpdateHomeBannerData;
use App\Http\Requests\Admin\StoreHomeBannerRequest;
use App\Http\Requests\Admin\UpdateHomeBannerRequest;
use App\Models\HomeBanner;
use App\Queries\AdminHomeBannerQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminHomeBannerWebController extends Controller
{
    public function index(Request $request, AdminHomeBannerQuery $query): View
    {
        $filters = $request->only(['search', 'status', 'action_type']);

        return view('admin.marketing.banners.index', [
            'banners' => $query->paginate($filters),
            'statistics' => $query->statistics(),
            'filters' => $filters,
        ]);
    }

    public function create(AdminHomeBannerQuery $query): View
    {
        return view('admin.marketing.banners.form', [
            'banner' => null,
            'products' => $query->activeProducts(),
            'categories' => $query->activeCategories(),
            'promotions' => $query->activePromotions(),
        ]);
    }

    public function store(StoreHomeBannerRequest $request, CreateHomeBannerAction $action): RedirectResponse
    {
        $banner = $action->execute(StoreHomeBannerData::fromValidated($request->validated(), $request->file('image')));

        return redirect()->route('admin.marketing.banners.index')->with('success', 'تم إنشاء بنر الصفحة الرئيسية بنجاح.');
    }

    public function show(HomeBanner $banner): View
    {
        $banner->loadMissing(['media']);

        return view('admin.marketing.banners.show', [
            'banner' => $banner,
        ]);
    }

    public function edit(HomeBanner $banner, AdminHomeBannerQuery $query): View
    {
        $banner->loadMissing(['media']);

        return view('admin.marketing.banners.form', [
            'banner' => $banner,
            'products' => $query->activeProducts(),
            'categories' => $query->activeCategories(),
            'promotions' => $query->activePromotions(),
        ]);
    }

    public function update(UpdateHomeBannerRequest $request, HomeBanner $banner, UpdateHomeBannerAction $action): RedirectResponse
    {
        $action->execute($banner, UpdateHomeBannerData::fromValidated($request->validated(), $request->file('image')));

        return redirect()->route('admin.marketing.banners.index')->with('success', 'تم تحديث بيانات البنر بنجاح.');
    }

    public function destroy(HomeBanner $banner, DeleteHomeBannerAction $action): RedirectResponse
    {
        $action->execute($banner);

        return redirect()->route('admin.marketing.banners.index')->with('success', 'تم حذف البنر بنجاح.');
    }

    public function toggleStatus(HomeBanner $banner, ToggleHomeBannerStatusAction $action): RedirectResponse
    {
        $updated = $action->execute($banner);
        $message = $updated->is_active ? 'تم تفعيل البنر بنجاح.' : 'تم تعطيل البنر بنجاح.';

        return back()->with('success', $message);
    }
}
