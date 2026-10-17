@extends('layouts.admin')
@section('title', 'تفاصيل البنر | الوفاء استور')
@section('page-title', 'تفاصيل البنر')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="mb-1 text-dark fw-bold">{{ $banner->title_ar }}</h4>
                <p class="text-muted mb-0 fs-13">معاينة تفاصيل البنر والوجهة التسويقية والجدولة الزمنية.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.marketing.banners.index') }}" class="btn btn-light">العودة للبنرات</a>
                @if(auth()->user()->hasPermission('admin.home_banners.update'))
                    <a href="{{ route('admin.marketing.banners.edit', $banner) }}" class="btn btn-primary">
                        <i class="ri-pencil-line me-1"></i> تعديل البنر
                    </a>
                @endif
            </div>
        </div>

        {{-- Preview Banner Image Card --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">معاينة صورة البنر</h5>
            </div>
            <div class="card-body text-center p-4 bg-light-subtle">
                @if($banner->getBannerImageUrl())
                    <img src="{{ $banner->getBannerImageUrl('large') ?: $banner->getBannerImageUrl() }}" alt="{{ $banner->title_ar }}" class="img-fluid rounded border shadow-sm" style="max-height: 400px; width: auto;">
                @else
                    <div class="py-5 text-muted">
                        <i class="ri-image-line fs-48 d-block mb-2"></i>
                        لا توجد صورة مسجلة لهذا البنر.
                    </div>
                @endif
            </div>
        </div>

        {{-- Detailed Attributes Card --}}
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">بيانات ومعايير العرض</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0 g-3">
                    <dt class="col-sm-3">العنوان بالعربية</dt>
                    <dd class="col-sm-9 fw-semibold text-dark">{{ $banner->title_ar }}</dd>

                    <dt class="col-sm-3">العنوان بالإنجليزية</dt>
                    <dd class="col-sm-9" dir="ltr">{{ $banner->title_en ?: '—' }}</dd>

                    <dt class="col-sm-3">النص الإضافي (عربي)</dt>
                    <dd class="col-sm-9">{{ $banner->subtitle_ar ?: '—' }}</dd>

                    <dt class="col-sm-3">النص الإضافي (إنجليزي)</dt>
                    <dd class="col-sm-9" dir="ltr">{{ $banner->subtitle_en ?: '—' }}</dd>

                    <dt class="col-sm-3">حالة التفعيل</dt>
                    <dd class="col-sm-9">
                        @if($banner->is_active)
                            <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">معطل</span>
                        @endif
                    </dd>

                    <dt class="col-sm-3">ترتيب الظهور</dt>
                    <dd class="col-sm-9">
                        <span class="badge bg-light text-body border">{{ $banner->sort_order }}</span>
                    </dd>

                    <dt class="col-sm-3">نوع الوجهة المرتبطة</dt>
                    <dd class="col-sm-9">
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                            {{ $banner->action_type->label() }}
                        </span>
                    </dd>

                    <dt class="col-sm-3">تفاصيل الوجهة</dt>
                    <dd class="col-sm-9 fw-medium">
                        {{ $banner->getDestinationLabel() }}
                    </dd>

                    <dt class="col-sm-3">الجدولة الزمنية</dt>
                    <dd class="col-sm-9">
                        @if($banner->starts_at || $banner->ends_at)
                            <div>
                                @if($banner->starts_at)
                                    <div><span class="text-muted">يبدأ في:</span> {{ $banner->starts_at->format('Y-m-d H:i') }}</div>
                                @endif
                                @if($banner->ends_at)
                                    <div><span class="text-muted">ينتهي في:</span> {{ $banner->ends_at->format('Y-m-d H:i') }}</div>
                                @endif
                            </div>
                        @else
                            <span class="text-muted">دائم (بدون حدود زمنية)</span>
                        @endif
                    </dd>

                    <dt class="col-sm-3">تاريخ الإنشاء</dt>
                    <dd class="col-sm-9 text-muted">{{ $banner->created_at->format('Y-m-d H:i') }}</dd>

                    <dt class="col-sm-3">آخر تحديث</dt>
                    <dd class="col-sm-9 text-muted">{{ $banner->updated_at->format('Y-m-d H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
