@extends('layouts.admin')
@section('title', 'تعديل بيانات العميل | لمّة')
@section('page-title', 'تعديل بيانات العميل')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="ri-edit-line me-1 text-primary"></i> تعديل بيانات العميل: {{ $customer->name }}</h4>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-subtle-primary">
                        <i class="ri-eye-line me-1"></i> عرض التفاصيل
                    </a>
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-sm btn-light">
                        <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($customer->isPhoneVerified())
                    <div class="alert alert-warning alert-border-left alert-dismissible fade show" role="alert" id="phone-change-warning">
                        <div class="d-flex align-items-center">
                            <i class="ri-alert-line fs-20 me-2"></i>
                            <div>
                                <strong>تنبيه هام حول توثيق الهاتف:</strong> رقم الهاتف الحالي لهذا العميل موثق بنجاح. تعديل رقم الهاتف الكنسي سيؤدي تلقائياً إلى <u>إلغاء حالة التوثيق الحالية</u>، وسيتطلب الرقم الجديد تأكيداً جديداً عبر OTP أو من الإدارة.
                            </div>
                        </div>
                    </div>
                @endif

                <form action="{{ route('admin.customers.update', $customer) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label required">اسم العميل</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $customer->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label required">رقم الهاتف</label>
                            <div class="input-group" dir="ltr">
                                <span class="input-group-text"><i class="ri-phone-line"></i></span>
                                <input type="text" class="form-control text-start @error('phone') is-invalid @enderror" id="phone" name="phone" data-original-phone="{{ $customer->phone }}" value="{{ old('phone', $customer->phone) }}" required>
                            </div>
                            <div class="form-text text-muted">يقبل أرقام الهواتف اليمنية أو الدولية. تغيير تنسيق العرض فقط لا يلغي التوثيق.</div>
                            @error('phone')
                                <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">البريد الإلكتروني <span class="text-muted fw-normal fs-12">(اختياري)</span></label>
                            <div class="input-group" dir="ltr">
                                <span class="input-group-text"><i class="ri-mail-line"></i></span>
                                <input type="email" class="form-control text-start @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $customer->email) }}">
                            </div>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">حالة الحساب</label>
                            <div class="form-check form-switch form-switch-success form-switch-md mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $customer->is_active))>
                                <label class="form-check-label ms-2" for="is_active">حساب نشط ومفعل</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">ملاحظات <span class="text-muted fw-normal fs-12">(اختياري)</span></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $customer->notes) }}</textarea>
                        </div>

                        <div class="col-12 text-end mt-4">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-light me-2">إلغاء</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i> حفظ التغييرات
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">حالة التوثيق الحالية</h5>
            </div>
            <div class="card-body">
                <div class="text-center py-2">
                    @if($customer->isPhoneVerified())
                        <div class="avatar-sm mx-auto mb-2">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-2">
                                <i class="ri-shield-check-fill"></i>
                            </span>
                        </div>
                        <h6 class="text-success mb-1">رقم الهاتف موثق</h6>
                        <p class="text-muted fs-12 mb-0">
                            طريقة التوثيق: {{ $customer->phone_verification_method?->label() }}
                        </p>
                        <p class="text-muted fs-11 mt-1">
                            تاريخ التوثيق: {{ $customer->phone_verified_at?->format('Y-m-d H:i') }}
                        </p>
                    @else
                        <div class="avatar-sm mx-auto mb-2">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-2">
                                <i class="ri-shield-flash-line"></i>
                            </span>
                        </div>
                        <h6 class="text-warning mb-1">رقم الهاتف غير موثق</h6>
                        <p class="text-muted fs-12 mb-0">
                            يمكن توثيق الهاتف من شاشة تفاصيل العميل.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const phoneInput = document.getElementById('phone');
    const warningAlert = document.getElementById('phone-change-warning');
    if (phoneInput && warningAlert) {
        const originalPhone = phoneInput.getAttribute('data-original-phone');
        phoneInput.addEventListener('input', function () {
            if (phoneInput.value.trim() !== originalPhone.trim()) {
                warningAlert.classList.add('border', 'border-danger');
            } else {
                warningAlert.classList.remove('border', 'border-danger');
            }
        });
    }
});
</script>
@endpush
