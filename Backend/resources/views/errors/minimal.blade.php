@extends('layouts.admin')
@section('title', trim($__env->yieldContent('code')).' | الوفاء استور')
@section('page-title', trim($__env->yieldContent('heading')))
@section('content')
<div class="row justify-content-center py-5">
    <div class="col-md-8 col-lg-6 col-xl-5">
        <div class="card text-center">
            <div class="card-body p-5">
                <div class="avatar-lg mx-auto mb-4"><div class="avatar-title bg-primary-subtle text-primary rounded-circle fs-24"><i class="ri-error-warning-line"></i></div></div>
                <p class="text-primary fw-semibold mb-2">@yield('code')</p>
                <h1 class="fs-24 mb-3">@yield('heading')</h1>
                <p class="text-muted mb-4">@yield('message')</p>
                <a href="{{ route('admin.login') }}" class="btn btn-primary"><i class="ri-home-4-line me-1"></i> العودة إلى النظام</a>
            </div>
        </div>
    </div>
</div>
@endsection
