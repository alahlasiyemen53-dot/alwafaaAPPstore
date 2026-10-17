@extends('layouts.admin')

@section('title', 'إضافة مستوى عملاء جديد')
@section('page-title', 'إضافة مستوى عملاء جديد')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header align-items-center d-flex">
                <h4 class="card-title mb-0 flex-grow-1">بيانات المستوى الجديد</h4>
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.customer-tiers.index') }}" class="btn btn-light btn-sm">
                        <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.customer-tiers.store') }}" id="createTierForm">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="code" class="form-label required">كود المستوى (Code) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" placeholder="مثال: VIP, GOLD, SILVER" required autofocus>
                            <div class="form-text">رمز تعريفي فريد ومستقر بالأحرف الإنجليزية الكبيرة. لا يمكن تغييره بعد استخدامه.</div>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="sort_order" class="form-label">ترتيب العرض</label>
                            <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" step="1">
                            <div class="form-text">ترتيب ظهور المستوى في القوائم ولوحة التحكم (الأصغر يظهر أولاً).</div>
                            @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="name_ar" class="form-label required">اسم المستوى (بالعربية) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name_ar') is-invalid @enderror" id="name_ar" name="name_ar" value="{{ old('name_ar') }}" placeholder="مثال: كبار الشخصيات VIP" required>
                            @error('name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="name_en" class="form-label">اسم المستوى (بالإنجليزية)</label>
                            <input type="text" class="form-control @error('name_en') is-invalid @enderror" id="name_en" name="name_en" value="{{ old('name_en') }}" placeholder="مثال: VIP Tier">
                            @error('name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">وصف المستوى</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="وصف توضيحي لطبيعة وفئة هذا المستوى الإداري...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch form-switch-md mt-2">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-medium" for="is_active">تفعيل المستوى</label>
                            </div>
                            <div class="form-text">المستويات المفعلة فقط يمكن تعيين العملاء إليها.</div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch form-switch-md mt-2">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
                                <label class="form-check-label fw-medium" for="is_default">تعيين كمستوى افتراضي للنظام</label>
                            </div>
                            <div class="form-text text-warning">تحذير: تفعيل هذا الخيار سينقل الافتراضية إلى هذا المستوى ويلغيها من المستوى الحالي.</div>
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-between">
                            <a href="{{ route('admin.customer-tiers.index') }}" class="btn btn-light">إلغاء</a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="ri-save-line me-1"></i> حفظ المستوى
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
