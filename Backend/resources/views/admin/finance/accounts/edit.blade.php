@extends('layouts.admin')
@section('title', 'تعديل الحساب | لمّة')
@section('page-title', 'تعديل الحساب المالي')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">تعديل: {{ $account->name_ar }}</h5>
                        <span class="badge bg-primary-subtle text-primary font-monospace">{{ $account->code }}</span>
                        <span class="badge {{ $account->type->badgeClass() }}">{{ $account->type->label() }}</span>
                    </div>
                    <a href="{{ route('admin.finance.accounts.index') }}" class="btn btn-light btn-sm">
                        <i class="ri-arrow-right-line me-1"></i> العودة لدليل الحسابات
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.finance.accounts.update', $account) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">اسم الحساب بالعربية</label>
                            <input type="text" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar', $account->name_ar) }}" required>
                            @error('name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">اسم الحساب بالإنجليزية</label>
                            <input type="text" name="name_en" class="form-control @error('name_en') is-invalid @enderror" value="{{ old('name_en', $account->name_en) }}">
                            @error('name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">الحساب الرئيسي (الأب)</label>
                            <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                <option value="">-- بدون حساب أب (حساب جذر رئيسي) --</option>
                                @foreach($parentAccounts as $parent)
                                    <option value="{{ $parent->id }}" {{ old('parent_id', $account->parent_id) == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->code }} - {{ $parent->name_ar }} ({{ $parent->type->label() }})
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch form-switch-md mt-3">
                                <input class="form-check-input" type="checkbox" name="is_postable" id="is_postable" value="1" {{ old('is_postable', $account->is_postable) ? 'checked' : '' }}>
                                <label class="form-check-label fw-medium" for="is_postable">حساب يقبل الترحيل المباشر</label>
                                <small class="text-muted d-block">لا يمكن إلغاؤه إذا كانت هناك قيود مسجلة على الحساب.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch form-switch-md mt-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $account->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label fw-medium" for="is_active">الحساب نشط</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">الوصف والملاحظات</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $account->description) }}</textarea>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <a href="{{ route('admin.finance.accounts.index') }}" class="btn btn-light me-2">إلغاء</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="ri-save-line me-1"></i> حفظ التعديلات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
