@extends('layouts.admin')
@section('title', 'إضافة حساب جديد | لمّة')
@section('page-title', 'إضافة حساب مالي')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">بيانات الحساب الجديد</h5>
                    <a href="{{ route('admin.finance.accounts.index') }}" class="btn btn-light btn-sm">
                        <i class="ri-arrow-right-line me-1"></i> العودة لدليل الحسابات
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.finance.accounts.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">رمز الحساب (الكود)</label>
                            <input type="text" name="code" class="form-control font-monospace @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="مثال: 1150" required>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">أرقام وحروف بدون مسافات، فريد على مستوى النظام.</small>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label required">اسم الحساب بالعربية</label>
                            <input type="text" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar') }}" placeholder="مثال: العهد النقدية المؤقتة" required>
                            @error('name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">اسم الحساب بالإنجليزية</label>
                            <input type="text" name="name_en" class="form-control @error('name_en') is-invalid @enderror" value="{{ old('name_en') }}" placeholder="e.g. Petty Cash">
                            @error('name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">الحساب الرئيسي (الأب)</label>
                            <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                <option value="">-- بدون حساب أب (حساب جذر رئيسي) --</option>
                                @foreach($parentAccounts as $parent)
                                    <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->code }} - {{ $parent->name_ar }} ({{ $parent->type->label() }})
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">نوع الحساب</label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                @foreach($accountTypes as $type)
                                    <option value="{{ $type->value }}" {{ old('type') === $type->value ? 'selected' : '' }}>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">طبيعة الرصيد (اختياري، يحدد تلقائياً حسب النوع)</label>
                            <select name="normal_balance" class="form-select @error('normal_balance') is-invalid @enderror">
                                <option value="">-- افتراضي بحسب نوع الحساب --</option>
                                @foreach($normalBalances as $balance)
                                    <option value="{{ $balance->value }}" {{ old('normal_balance') === $balance->value ? 'selected' : '' }}>
                                        {{ $balance->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('normal_balance')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch form-switch-md mt-3">
                                <input class="form-check-input" type="checkbox" name="is_postable" id="is_postable" value="1" {{ old('is_postable', '1') ? 'checked' : '' }}>
                                <label class="form-check-label fw-medium" for="is_postable">حساب يقبل الترحيل المباشر</label>
                                <small class="text-muted d-block">عطّله إذا كان الحساب تجميعياً (رئيسياً) للمستويات الفرعية فقط.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch form-switch-md mt-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                                <label class="form-check-label fw-medium" for="is_active">الحساب نشط</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">الوصف والملاحظات</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="ملاحظات محاسبية إضافية...">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <a href="{{ route('admin.finance.accounts.index') }}" class="btn btn-light me-2">إلغاء</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="ri-save-line me-1"></i> حفظ الحساب</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
