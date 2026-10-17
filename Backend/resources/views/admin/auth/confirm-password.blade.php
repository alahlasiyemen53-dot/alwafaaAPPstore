@extends('layouts.admin')
@section('title', 'تأكيد كلمة المرور | لمّة')
@section('page-title', 'تأكيد كلمة المرور')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h2 class="fs-18 mb-1">تأكيد الهوية</h2>
                <p class="text-muted fs-13 mb-4">هذه العملية حساسة. أعد إدخال كلمة المرور للمتابعة.</p>
                <form method="POST" action="{{ route('password.confirm.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="password" class="form-label required">كلمة المرور الحالية</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required autofocus autocomplete="current-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ url()->previous() }}" class="btn btn-light">إلغاء</a>
                        <button type="submit" class="btn btn-primary"><i class="ri-shield-keyhole-line me-1"></i> تأكيد والمتابعة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
