@extends('layouts.admin')
@section('title', 'إضافة عميل جديد | لمّة')
@section('page-title', 'إضافة عميل جديد')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="ri-user-add-line me-1 text-primary"></i> بيانات العميل الأساسية</h4>
                <a href="{{ route('admin.customers.index') }}" class="btn btn-sm btn-light">
                    <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.customers.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label required">اسم العميل</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="أدخل اسم العميل الكامل" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label required">رقم الهاتف</label>
                            <div class="input-group" dir="ltr">
                                <span class="input-group-text"><i class="ri-phone-line"></i></span>
                                <input type="text" class="form-control text-start @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="77XXXXXXX أو +96777XXXXXXX" required>
                            </div>
                            <div class="form-text text-muted">يقبل أرقام الهواتف اليمنية (مثل: 771234567) أو الدولية الكاملة.</div>
                            @error('phone')
                                <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">البريد الإلكتروني <span class="text-muted fw-normal fs-12">(اختياري)</span></label>
                            <div class="input-group" dir="ltr">
                                <span class="input-group-text"><i class="ri-mail-line"></i></span>
                                <input type="email" class="form-control text-start @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="customer@example.com">
                            </div>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">حالة الحساب</label>
                            <div class="form-check form-switch form-switch-success form-switch-md mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                                <label class="form-check-label ms-2" for="is_active">حساب نشط ومفعل</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">ملاحظات <span class="text-muted fw-normal fs-12">(اختياري)</span></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="أي ملاحظات إضافية حول العميل">{{ old('notes') }}</textarea>
                        </div>

                        <div class="col-12 text-end mt-4">
                            <a href="{{ route('admin.customers.index') }}" class="btn btn-light me-2">إلغاء</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i> حفظ العميل
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card bg-light border">
            <div class="card-body">
                <h5 class="card-title text-primary"><i class="ri-information-line me-1"></i> حول توثيق رقم الهاتف</h5>
                <p class="text-muted fs-13 mt-2 mb-2">
                    يتم إنشاء العميل الجديد بحالة <strong>غير موثق</strong> بشكل تلقائي.
                </p>
                <p class="text-muted fs-13 mb-0">
                    بعد حفظ العميل، يمكن للمشرف المعتمد توثيق رقم الهاتف يدوياً من صفحة تفاصيل العميل، أو من خلال رمز التحقق (OTP) في التحديثات القادمة.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
