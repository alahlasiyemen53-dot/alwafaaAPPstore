@extends('layouts.admin')
@section('title', ($supplier ? 'تعديل بيانات مورد' : 'إضافة مورد جديد').' | الوفاء استور')
@section('page-title', $supplier ? 'تعديل مورد' : 'إضافة مورد جديد')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-9">
        <form method="POST" action="{{ $supplier ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}">
            @csrf
            @if($supplier) @method('PUT') @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="ri-truck-line me-1 text-primary"></i> بيانات المورد الأساسية</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label required">اسم المورد / الشركة</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $supplier?->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="code" class="form-label">كود المورد (اختياري)</label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $supplier?->code) }}" placeholder="يتم التوليد تلقائياً مثل SUP-0001" dir="ltr">
                            <div class="form-text">اتركه فارغاً للتوليد التسلسلي التلقائي.</div>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="contact_person" class="form-label">اسم المسؤول / جهة الاتصال</label>
                            <input type="text" class="form-control @error('contact_person') is-invalid @enderror" id="contact_person" name="contact_person" value="{{ old('contact_person', $supplier?->contact_person) }}">
                            @error('contact_person')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label required">رقم الهاتف الأساسي</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $supplier?->phone) }}" required dir="ltr">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="secondary_phone" class="form-label">رقم هاتف إضافي</label>
                            <input type="text" class="form-control @error('secondary_phone') is-invalid @enderror" id="secondary_phone" name="secondary_phone" value="{{ old('secondary_phone', $supplier?->secondary_phone) }}" dir="ltr">
                            @error('secondary_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $supplier?->email) }}" dir="ltr">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="tax_number" class="form-label">الرقم الضريبي / السجل التجاري</label>
                            <input type="text" class="form-control @error('tax_number') is-invalid @enderror" id="tax_number" name="tax_number" value="{{ old('tax_number', $supplier?->tax_number) }}" dir="ltr">
                            @error('tax_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $supplier?->is_active ?? true))>
                                <label class="form-check-label fw-semibold" for="is_active">المورد نشط ومتاح لعمليات الشراء</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">العنوان والموقع</label>
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $supplier?->address) }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">ملاحظات عامة</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes', $supplier?->notes) }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light">إلغاء</a>
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> حفظ المورد</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
