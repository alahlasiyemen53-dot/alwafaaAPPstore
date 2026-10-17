@extends('layouts.admin')
@section('title', 'بنرات الصفحة الرئيسية | لمّة')
@section('page-title', 'بنرات ومحتوى الصفحة الرئيسية')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="mb-1 text-dark fw-bold">إدارة بنرات الصفحة الرئيسية</h4>
                <p class="text-muted mb-0 fs-13">إدارة صور وتوجيهات وجدولة بنرات العرض الترويجي في واجهة المتجر والتطبيق.</p>
            </div>
            @if(auth()->user()->hasPermission('admin.home_banners.create'))
                <a href="{{ route('admin.marketing.banners.create') }}" class="btn btn-primary">
                    <i class="ri-add-line align-bottom me-1"></i> إضافة بنر جديد
                </a>
            @endif
        </div>

        {{-- Statistics Cards --}}
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0 fs-12">إجمالي البنرات المسجلة</p>
                                <h4 class="fs-22 fw-semibold mb-0 mt-2">{{ $statistics['total'] }}</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle text-info rounded-circle fs-3">
                                    <i class="ri-image-2-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0 fs-12">البنرات المفعلة</p>
                                <h4 class="fs-22 fw-semibold mb-0 mt-2 text-success">{{ $statistics['active'] }}</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3">
                                    <i class="ri-checkbox-circle-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0 fs-12">المعروضة للعملاء حالياً</p>
                                <h4 class="fs-22 fw-semibold mb-0 mt-2 text-primary">{{ $statistics['scheduled_now'] }}</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3">
                                    <i class="ri-calendar-check-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters & Search Card --}}
        <div class="card">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.marketing.banners.index') }}" class="row g-3 align-items-end filter-row">
                    <div class="col-md-4">
                        <label for="search" class="form-label">البحث بالعنوان</label>
                        <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="ابحث باسم البنر أو الوصف...">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">حالة العرض</label>
                        <select class="form-control" data-choices data-choices-search-false id="status" name="status">
                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>جميع الحالات</option>
                            <option value="scheduled_now" @selected(($filters['status'] ?? '') === 'scheduled_now')>المعروضة حالياً (ضمن الجدولة)</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>مفعل فقط</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>معطل فقط</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="action_type" class="form-label">نوع وجهة التوجيه</label>
                        <select class="form-control" data-choices data-choices-search-false id="action_type" name="action_type">
                            <option value="all" @selected(($filters['action_type'] ?? 'all') === 'all')>الكل</option>
                            @foreach(\App\Enums\BannerActionType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(($filters['action_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-soft-primary w-100"><i class="ri-filter-3-line me-1"></i> تطبيق</button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 140px;">معاينة البنر</th>
                                <th>العنوان والوصف</th>
                                <th>نوع الوجهة</th>
                                <th>الترتيب</th>
                                <th>الجدولة الزمنية</th>
                                <th>الحالة</th>
                                <th class="text-end" style="width: 140px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($banners as $banner)
                                <tr>
                                    <td>
                                        @if($banner->getBannerImageUrl('thumb'))
                                            <a href="{{ $banner->getBannerImageUrl() }}" target="_blank">
                                                <img src="{{ $banner->getBannerImageUrl('thumb') }}" alt="{{ $banner->title_ar }}" class="rounded border shadow-sm" style="width: 120px; height: 60px; object-fit: cover;">
                                            </a>
                                        @else
                                            <div class="bg-light rounded border d-flex align-items-center justify-content-center text-muted" style="width: 120px; height: 60px;">
                                                <i class="ri-image-line fs-20"></i>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark fs-14">{{ $banner->title_ar }}</div>
                                        @if($banner->title_en)
                                            <div class="text-muted fs-12" dir="ltr">{{ $banner->title_en }}</div>
                                        @endif
                                        @if($banner->subtitle_ar)
                                            <div class="text-muted fs-12 text-truncate mt-1" style="max-width: 250px;">{{ $banner->subtitle_ar }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-1">
                                            {{ $banner->action_type->label() }}
                                        </span>
                                        @if($banner->action_reference && $banner->action_type !== \App\Enums\BannerActionType::None)
                                            <div class="text-muted fs-12">{{ $banner->getDestinationLabel() }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-body border">{{ $banner->sort_order }}</span>
                                    </td>
                                    <td>
                                        @if($banner->starts_at || $banner->ends_at)
                                            <div class="fs-12">
                                                @if($banner->starts_at)
                                                    <div><span class="text-muted">من:</span> {{ $banner->starts_at->format('Y-m-d H:i') }}</div>
                                                @endif
                                                @if($banner->ends_at)
                                                    <div><span class="text-muted">إلى:</span> {{ $banner->ends_at->format('Y-m-d H:i') }}</div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="badge bg-light text-muted border">دائم (بدون انتهاء)</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($banner->is_active)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">معطل</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('admin.marketing.banners.show', $banner) }}" class="btn btn-sm btn-soft-info" title="عرض التفاصيل">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @if(auth()->user()->hasPermission('admin.home_banners.update'))
                                                <a href="{{ route('admin.marketing.banners.edit', $banner) }}" class="btn btn-sm btn-soft-primary" title="تعديل">
                                                    <i class="ri-pencil-line"></i>
                                                </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('admin.home_banners.activate'))
                                                <form method="POST" action="{{ route('admin.marketing.banners.toggle', $banner) }}" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm {{ $banner->is_active ? 'btn-soft-warning' : 'btn-soft-success' }}" title="{{ $banner->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                        <i class="{{ $banner->is_active ? 'ri-pause-line' : 'ri-play-line' }}"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if(auth()->user()->hasPermission('admin.home_banners.delete'))
                                                <form method="POST" action="{{ route('admin.marketing.banners.destroy', $banner) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا البنر نهائياً؟');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-soft-danger" title="حذف">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="text-center py-5">
                                            <div class="avatar-md mx-auto mb-3">
                                                <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                                    <i class="ri-image-line"></i>
                                                </div>
                                            </div>
                                            <h5 class="fs-16 mb-1">لا توجد بنرات مسجلة</h5>
                                            <p class="text-muted mb-0">أضف أول بنر رئيسي لمتجرك لعرضه للعملاء.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($banners->hasPages())
                <div class="card-footer">
                    {{ $banners->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
