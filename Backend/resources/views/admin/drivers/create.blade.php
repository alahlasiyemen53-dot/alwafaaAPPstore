@extends('layouts.admin')
@section('title', 'إضافة مندوب توصيل جديد | الوفاء استور')
@section('page-title', 'إضافة مندوب توصيل')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header align-items-center d-flex justify-content-between">
                <h4 class="card-title mb-0"><i class="ri-user-add-line me-1 text-primary"></i> بيانات مندوب التوصيل الجديد</h4>
                <a href="{{ route('admin.drivers.index') }}" class="btn btn-sm btn-light">
                    <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.drivers.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">اسم المندوب <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="أدخل اسم المندوب الرباعي" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">رقم الهاتف <span class="text-danger">*</span></label>
                            <input type="text" dir="ltr" class="form-control text-start @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="مثال: 967770000000" required>
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" dir="ltr" class="form-control text-start @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="driver@example.com">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="license_number" class="form-label">رقم رخصة القيادة</label>
                            <input type="text" class="form-control @error('license_number') is-invalid @enderror" id="license_number" name="license_number" value="{{ old('license_number') }}" placeholder="أدخل رقم الرخصة">
                            @error('license_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="vehicle_type" class="form-label">نوع المركبة</label>
                            <select class="form-select @error('vehicle_type') is-invalid @enderror" id="vehicle_type" name="vehicle_type">
                                <option value="">اختر نوع المركبة...</option>
                                <option value="دراجة نارية" @selected(old('vehicle_type') === 'دراجة نارية')>دراجة نارية (Motorcycle)</option>
                                <option value="سيارة صالون" @selected(old('vehicle_type') === 'سيارة صالون')>سيارة صالون (Sedan)</option>
                                <option value="شاحنة صغيرة" @selected(old('vehicle_type') === 'شاحنة صغيرة')>شاحنة صغيرة (Van / Pickup)</option>
                                <option value="أخرى" @selected(old('vehicle_type') === 'أخرى')>أخرى</option>
                            </select>
                            @error('vehicle_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="vehicle_plate_number" class="form-label">رقم لوحة المركبة</label>
                            <input type="text" class="form-control @error('vehicle_plate_number') is-invalid @enderror" id="vehicle_plate_number" name="vehicle_plate_number" value="{{ old('vehicle_plate_number') }}" placeholder="مثال: ص 12345">
                            @error('vehicle_plate_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch form-switch-md">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label fw-medium" for="is_active">تفعيل حساب المندوب وإتاحته لإسناد الشحنات</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">ملاحظات إضافية</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" placeholder="أي ملاحظات حول أوقات العمل أو نطاق التغطية...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 text-end mt-4">
                            <a href="{{ route('admin.drivers.index') }}" class="btn btn-light me-2">إلغاء</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="ri-save-line me-1"></i> حفظ المندوب
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
