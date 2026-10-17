@extends('layouts.admin')
@section('title', 'استيراد منتجات Excel | الوفاء استور')
@section('page-title', 'استيراد منتجات Excel')
@section('content')
@php($preview = session('product_import_preview'))
<div class="page-heading"><div><h2 class="fs-18 mb-1">استيراد المنتجات من Excel</h2><p>إنشاء فقط: افحص جميع الصفوف قبل أي كتابة في قاعدة البيانات.</p></div><a class="btn btn-soft-primary" href="{{ route('admin.products.import.template') }}"><i class="ri-download-2-line me-1"></i> تحميل نموذج Excel</a></div>
<div class="card"><div class="card-body"><form method="POST" action="{{ route('admin.products.import.preview') }}" enctype="multipart/form-data" class="row g-3">@csrf
    <div class="col-md-8"><label class="form-label" for="workbook">ملف XLSX</label><input class="form-control @error('workbook') is-invalid @enderror" id="workbook" name="workbook" type="file" accept=".xlsx" required><div class="form-text">الحد الأقصى 5MB و500 صف. استخدم codes المعتمدة في size وshade، واسم/code المورد والمستودع.</div>@error('workbook')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><i class="ri-search-line me-1"></i> فحص الملف</button></div>
</form></div></div>
@if($preview)
<div class="card"><div class="card-body"><h3 class="card-title">نتيجة الفحص</h3><div class="row text-center g-3"><div class="col"><strong>{{ $preview['total'] }}</strong><div class="text-muted">إجمالي الصفوف</div></div><div class="col text-success"><strong>{{ $preview['valid'] }}</strong><div>صالحة</div></div><div class="col text-danger"><strong>{{ $preview['invalid'] }}</strong><div>غير صالحة</div></div><div class="col"><strong>{{ $preview['products'] }}</strong><div class="text-muted">منتجات جديدة</div></div></div>
@if($preview['errors'])
<div class="alert alert-danger mt-4"><strong>أخطاء يجب إصلاحها</strong><ul class="mb-0">
@foreach($preview['errors'] as $row => $messages)
@foreach($messages as $message)
<li>الصف {{ $row }}: {{ $message }}</li>
@endforeach
@endforeach
</ul></div>
@endif
@if($preview['warnings'])
<div class="alert alert-warning mt-3"><ul class="mb-0">
@foreach($preview['warnings'] as $row => $message)
<li>الصف {{ $row }}: {{ $message }}</li>
@endforeach
</ul></div>
@endif
@if($preview['valid'] > 0 && $preview['invalid'] === 0)
<form method="POST" action="{{ route('admin.products.import.confirm') }}" class="mt-4" data-confirm="سيتم إنشاء المنتجات والمتغيرات الظاهرة في المعاينة. هل تريد المتابعة؟">@csrf<button class="btn btn-success" type="submit"><i class="ri-check-line me-1"></i> تأكيد الاستيراد</button></form>
@endif
</div></div>
@endif
@endsection
