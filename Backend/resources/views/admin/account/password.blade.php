@extends('layouts.admin')
@section('title', 'تغيير كلمة المرور | لمّة')
@section('page-title', 'تغيير كلمة المرور')
@section('content')
<div class="row justify-content-center"><div class="col-xl-7"><div class="card"><div class="card-header"><h2 class="card-title mb-0">تأمين الحساب</h2></div><div class="card-body">
    <p class="text-muted">استخدم كلمة مرور لا تقل عن 12 حرفًا وتحتوي على أحرف كبيرة وصغيرة وأرقام.</p>
    <form method="POST" action="{{ route('admin.password.update.web') }}">@csrf @method('PUT')
        @foreach([['current_password','كلمة المرور الحالية'],['password','كلمة المرور الجديدة'],['password_confirmation','تأكيد كلمة المرور الجديدة']] as [$field,$label])
            <div class="mb-3"><label for="{{ $field }}" class="form-label required">{{ $label }}</label><div class="position-relative"><input type="password" class="form-control pe-5 @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" required autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}"><button class="btn btn-link position-absolute top-0 auth-pass-toggle text-muted" type="button" data-password-toggle="#{{ $field }}" aria-label="إظهار {{ $label }}"><i class="ri-eye-line"></i></button></div>@error($field)<span class="field-error">{{ $message }}</span>@enderror</div>
        @endforeach
        <div class="form-actions"><a class="btn btn-light" href="{{ route('admin.profile.edit') }}">إلغاء</a><button class="btn btn-primary" type="submit"><i class="ri-lock-password-line me-1"></i> تغيير كلمة المرور</button></div>
    </form>
</div></div></div></div>
@endsection
