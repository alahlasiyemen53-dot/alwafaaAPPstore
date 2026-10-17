@extends('layouts.admin')
@section('title', 'الملف الشخصي | لمّة')
@section('page-title', 'الملف الشخصي')
@section('content')
<div class="row justify-content-center"><div class="col-xl-8"><div class="card"><div class="card-header"><h2 class="card-title mb-0">بيانات الحساب</h2></div><div class="card-body">
    <form method="POST" action="{{ route('admin.profile.update') }}">@csrf @method('PUT')
        <div class="row g-3"><div class="col-md-12"><label for="name" class="form-label required">الاسم</label><input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $profile->name) }}" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="col-md-6"><label for="email" class="form-label">البريد الإلكتروني</label><input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $profile->email) }}" dir="ltr">@error('email')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="col-md-6"><label for="phone" class="form-label">رقم الهاتف</label><input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $profile->phone) }}" dir="ltr">@error('phone')<span class="field-error">{{ $message }}</span>@enderror</div></div>
        <div class="mt-4"><span class="form-label d-block">الأدوار الحالية</span><div class="d-flex flex-wrap gap-1">@foreach($profile->roles as $role)<span class="badge bg-info-subtle text-info">{{ $role->display_name ?: $role->name }}</span>@endforeach</div></div>
        <div class="form-actions mt-4"><a class="btn btn-light" href="{{ route('admin.dashboard') }}">إلغاء</a><button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i> حفظ التغييرات</button></div>
    </form>
</div></div></div></div>
@endsection
