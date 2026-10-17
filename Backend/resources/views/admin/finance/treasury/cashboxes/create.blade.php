@extends('layouts.admin')
@section('title', 'إضافة صندوق نقدي جديد | الوفاء استور')
@section('page-title', 'إضافة صندوق نقدي')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">بيانات الصندوق الجديد</h5>
                <a href="{{ route('admin.finance.treasury.cashboxes.index') }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-right-line align-bottom me-1"></i> العودة للقائمة
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.finance.treasury.cashboxes.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">رمز الصندوق <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="مثال: CB-01" required>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">العملات المدعومة <span class="text-danger">*</span></label>
                            <select name="currency_ids[]" class="form-select @error('currency_ids') is-invalid @enderror" multiple size="{{ min(6, max(3, $currencies->count())) }}" required>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}" @selected(collect(old('currency_ids', []))->contains($currency->id) || (old('currency_id') == $currency->id))>
                                        {{ $currency->name_ar }} ({{ $currency->code }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted">يمكن اختيار أكثر من عملة. الأولى المختارة تصبح العملة الأساسية. جميع العملات تُربط بنفس حساب الأستاذ.</div>
                            @error('currency_ids')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('currency_ids.*')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الاسم بالعربية <span class="text-danger">*</span></label>
                            <input type="text" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar') }}" placeholder="مثال: الخزينة الرئيسية - صنعاء" required>
                            @error('name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الاسم بالإنجليزية (اختياري)</label>
                            <input type="text" name="name_en" class="form-control @error('name_en') is-invalid @enderror" value="{{ old('name_en') }}" placeholder="Main Cashbox Sana'a">
                            @error('name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">حساب الأستاذ العام المربوط (حساب أصل نشط وقابل للترحيل) <span class="text-danger">*</span></label>
                            <select name="gl_account_id" class="form-select @error('gl_account_id') is-invalid @enderror" required>
                                <option value="">اختر حساب الأستاذ العام...</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ old('gl_account_id') == $account->id ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name_ar }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted">يمكن ربط نفس حساب الدليل بعدة صناديق/بنوك/محافظ. الرصيد التشغيلي منفصل لكل حساب×عملة، والمجموع يظهر في دليل الأستاذ.</div>
                            @error('gl_account_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">تفعيل الصندوق فور الإنشاء</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">ملاحظات إضافية</label>
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" placeholder="ملاحظات حول هذا الصندوق...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <a href="{{ route('admin.finance.treasury.cashboxes.index') }}" class="btn btn-light me-2">إلغاء</a>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> حفظ الصندوق</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
