@extends('layouts.admin')
@section('title', 'تسجيل الدخول | الوفاء استور')
@section('content')
<div class="auth-page-wrapper pt-5 auth-wafaa">
    <div class="auth-one-bg-position auth-one-bg"><div class="bg-overlay"></div><div class="shape"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120" aria-hidden="true"><path d="M0 36C144 54 432 123 720 124s576-67 720-84v100H0z"></path></svg></div></div>
    <main class="auth-page-content"><div class="container">
        <div class="row"><div class="col-12"><div class="text-center mt-sm-5 mb-4"><a href="{{ route('admin.login') }}" class="auth-brand"><span class="wafaa-mark">و</span><span>الوفاء استور</span></a><p class="mt-3 text-white-50">لوحة إدارة الوفاء استور</p></div></div></div>
        <div class="row justify-content-center"><div class="col-md-8 col-lg-6 col-xl-5"><div class="card mt-4 card-bg-fill"><div class="card-body p-4">
            <div class="text-center mt-2"><h1 class="text-primary fs-22">مرحبًا بعودتك</h1><p class="text-muted">سجّل الدخول إلى مساحة الإدارة الآمنة.</p></div>
            <div class="p-2 mt-4">
                @if($errors->any())<div class="alert alert-danger" role="alert"><i class="ri-error-warning-line me-1"></i>{{ $errors->first() }}</div>@endif
                <form method="POST" action="{{ route('admin.authenticate') }}">@csrf
                    <div class="mb-3"><label for="identifier" class="form-label required">البريد الإلكتروني أو الهاتف</label><input type="text" class="form-control @error('identifier') is-invalid @enderror" id="identifier" name="identifier" value="{{ old('identifier') }}" required autofocus autocomplete="username" placeholder="admin@example.com">@error('identifier')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="mb-3"><label for="password" class="form-label required">كلمة المرور</label><div class="position-relative auth-pass-inputgroup"><input type="password" class="form-control pe-5 @error('password') is-invalid @enderror" id="password" name="password" required autocomplete="current-password" placeholder="••••••••••••"><button class="btn btn-link position-absolute top-0 auth-pass-toggle text-muted" type="button" data-password-toggle="#password" aria-label="إظهار كلمة المرور"><i class="ri-eye-line align-middle"></i></button></div>@error('password')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="mt-4"><button class="btn btn-success w-100" type="submit"><i class="ri-login-box-line me-1"></i> الدخول إلى لوحة الإدارة</button></div>
                </form>
            </div>
        </div></div><p class="mt-4 text-center text-muted">الدخول مخصص للمستخدمين المصرّح لهم فقط.</p></div></div>
    </div></main>
    <footer class="footer"><div class="container"><p class="mb-0 text-center text-muted">{{ now()->year }} © الوفاء استور.</p></div></footer>
</div>
@endsection
