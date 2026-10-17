@extends('layouts.admin')
@section('title', ($attribute ? 'تعديل خاصية: ' . $attribute->name_ar : 'إضافة خاصية جديدة') . ' | الوفاء استور')
@section('page-title', $attribute ? 'تعديل خاصية' : 'إضافة خاصية')
@section('content')
<div class="page-heading">
    <div>
        <h2 class="fs-18 mb-1">{{ $attribute ? 'تعديل الخاصية: ' . $attribute->name_ar : 'إضافة خاصية جديدة' }}</h2>
        <p class="text-muted mb-0">حدد تفاصيل الخاصية مثل الاسم والرمز البرمجي والترتيب</p>
    </div>
    <div>
        <a class="btn btn-light" href="{{ route('admin.attributes.index') }}"><i class="ri-arrow-right-line me-1"></i> العودة للخصائص</a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">بيانات الخاصية</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ $attribute ? route('admin.attributes.update', $attribute) : route('admin.attributes.store') }}">
                    @csrf
                    @if($attribute)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name_ar" class="form-label">الاسم بالعربي <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name_ar') is-invalid @enderror" id="name_ar" name="name_ar" value="{{ old('name_ar', $attribute?->name_ar) }}" required placeholder="مثال: الحجم أو اللون">
                            @error('name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="name_en" class="form-label">الاسم بالإنجليزي</label>
                            <input type="text" class="form-control @error('name_en') is-invalid @enderror" id="name_en" name="name_en" value="{{ old('name_en', $attribute?->name_en) }}" dir="ltr" placeholder="e.g. Size or Color">
                            @error('name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="code" class="form-label">الرمز البرمجي (فريد) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $attribute?->code) }}" required dir="ltr" placeholder="e.g. size or color">
                            <small class="text-muted">يستخدم كمعرّف تقني فريد في النظام باللغة الإنجليزية</small>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="sort_order" class="form-label">الترتيب</label>
                            <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $attribute?->sort_order ?? 0) }}" min="0">
                            @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $attribute?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="is_active">الخاصية نشطة ومتاحة للاستخدام</label>
                            </div>
                        </div>

                        <div class="col-12 text-end pt-3 border-top">
                            <a href="{{ route('admin.attributes.index') }}" class="btn btn-light me-2">إلغاء</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i> {{ $attribute ? 'حفظ التعديلات' : 'إنشاء الخاصية ومتابعة إضافة القيم' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
