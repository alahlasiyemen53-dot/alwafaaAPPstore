@extends('layouts.admin')

@section('title', 'تعديل مستوى العميل | ' . $tier->name_ar)
@section('page-title', 'تعديل مستوى العميل')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header align-items-center d-flex">
                <h4 class="card-title mb-0 flex-grow-1">تعديل بيانات المستوى: {{ $tier->name_ar }}</h4>
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.customer-tiers.index') }}" class="btn btn-light btn-sm">
                        <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($tier->hasMembershipHistory())
                    <div class="alert alert-info border-0 d-flex align-items-center mb-3">
                        <i class="ri-information-line fs-18 me-2"></i>
                        <div>
                            هذا المستوى يحتوي على سجل عضويات لعملاء (<strong>{{ $tier->memberships()->count() }}</strong> سجل). لضمان سلامة التدقيق، تم قفل تعديل الكود التعريفي (Code).
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.customer-tiers.update', $tier) }}" id="editTierForm">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="code" class="form-label required">كود المستوى (Code) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $tier->code) }}" {{ $tier->hasMembershipHistory() ? 'readonly' : 'required' }}>
                            <div class="form-text">
                                @if($tier->hasMembershipHistory())
                                    <span class="text-muted"><i class="ri-lock-line me-1"></i> الكود مقفل لوجود سجل عضويات مسجلة به.</span>
                                @else
                                    رمز تعريفي فريد ومستقر بالأحرف الإنجليزية.
                                @endif
                            </div>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="sort_order" class="form-label">ترتيب العرض</label>
                            <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $tier->sort_order) }}" min="0" step="1">
                            <div class="form-text">ترتيب ظهور المستوى في القوائم.</div>
                            @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="name_ar" class="form-label required">اسم المستوى (بالعربية) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name_ar') is-invalid @enderror" id="name_ar" name="name_ar" value="{{ old('name_ar', $tier->name_ar) }}" required>
                            @error('name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="name_en" class="form-label">اسم المستوى (بالإنجليزية)</label>
                            <input type="text" class="form-control @error('name_en') is-invalid @enderror" id="name_en" name="name_en" value="{{ old('name_en', $tier->name_en) }}">
                            @error('name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">وصف المستوى</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $tier->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch form-switch-md mt-2">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $tier->is_active) ? 'checked' : '' }} {{ $tier->is_default ? 'disabled' : '' }}>
                                <label class="form-check-label fw-medium" for="is_active">تفعيل المستوى</label>
                            </div>
                            @if($tier->is_default)
                                <input type="hidden" name="is_active" value="1">
                                <div class="form-text text-muted"><i class="ri-lock-line me-1"></i> لا يمكن تعطيل المستوى الافتراضي للنظام.</div>
                            @else
                                <div class="form-text">المستويات المفعلة فقط يمكن تعيين العملاء إليها.</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch form-switch-md mt-2">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" {{ old('is_default', $tier->is_default) ? 'checked' : '' }} {{ $tier->is_default ? 'disabled' : '' }}>
                                <label class="form-check-label fw-medium" for="is_default">المستوى الافتراضي للنظام</label>
                            </div>
                            @if($tier->is_default)
                                <input type="hidden" name="is_default" value="1">
                                <div class="form-text text-info"><i class="ri-check-double-line me-1"></i> هذا هو المستوى الافتراضي الحالي للنظام. لإلغاء الافتراضية، عيّن مستوى آخر كافتراضي.</div>
                            @else
                                <div class="form-text text-warning">تفعيل هذا الخيار سينقل الافتراضية إلى هذا المستوى.</div>
                            @endif
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-between">
                            <a href="{{ route('admin.customer-tiers.index') }}" class="btn btn-light">إلغاء</a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="ri-save-line me-1"></i> تحديث المستوى
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
