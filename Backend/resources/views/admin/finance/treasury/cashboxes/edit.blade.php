@extends('layouts.admin')
@section('title', 'تعديل الصندوق النقدي | الوفاء استور')
@section('page-title', 'تعديل الصندوق النقدي')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">تعديل بيانات الصندوق: {{ $cashbox->name_ar }}</h5>
                <a href="{{ route('admin.finance.treasury.cashboxes.index') }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-right-line align-bottom me-1"></i> العودة للقائمة
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.finance.treasury.cashboxes.update', $cashbox) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">رمز الصندوق <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $cashbox->code) }}" required>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">العملات المدعومة <span class="text-danger">*</span></label>
                            @php
                                $openedCurrencyIds = old('currency_ids', $cashbox->currencyAccounts->pluck('currency_id')->all());
                            @endphp
                            <select name="currency_ids[]" class="form-select @error('currency_ids') is-invalid @enderror" multiple size="{{ min(6, max(3, $currencies->count())) }}" required>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}" @selected(collect($openedCurrencyIds)->contains($currency->id))>
                                        {{ $currency->name_ar }} ({{ $currency->code }})@if($currency->id == $cashbox->currency_id) — أساسية@endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted">يمكن إضافة عملات جديدة بنفس حساب الأستاذ. العملة الأساسية لا تتغير بعد فتح أكثر من عملة.</div>
                            @error('currency_ids')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('currency_ids.*')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الاسم بالعربية <span class="text-danger">*</span></label>
                            <input type="text" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar', $cashbox->name_ar) }}" required>
                            @error('name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الاسم بالإنجليزية</label>
                            <input type="text" name="name_en" class="form-control @error('name_en') is-invalid @enderror" value="{{ old('name_en', $cashbox->name_en) }}">
                            @error('name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">حساب الأستاذ العام المربوط <span class="text-danger">*</span></label>
                            <select name="gl_account_id" class="form-select @error('gl_account_id') is-invalid @enderror" required>
                                @if($cashbox->glAccount && !$accounts->contains('id', $cashbox->gl_account_id))
                                    <option value="{{ $cashbox->glAccount->id }}" selected>{{ $cashbox->glAccount->code }} - {{ $cashbox->glAccount->name_ar }}</option>
                                @endif
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ old('gl_account_id', $cashbox->gl_account_id) == $account->id ? 'selected' : '' }}>
                                        {{ $account->code }} - {{ $account->name_ar }}
                                    </option>
                                @endforeach
                            </select>
                            @error('gl_account_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', $cashbox->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">الصندوق نشط</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">ملاحظات إضافية</label>
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $cashbox->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <a href="{{ route('admin.finance.treasury.cashboxes.index') }}" class="btn btn-light me-2">إلغاء</a>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> حفظ التعديلات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
