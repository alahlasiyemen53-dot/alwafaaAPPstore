@extends('layouts.admin')
@section('title', ($assignment ? 'تعديل ربط' : 'إضافة ربط').' | لمّة')
@section('page-title', $assignment ? 'تعديل ربط توصيل' : 'إضافة ربط توصيل')
@section('content')
@php($lockedMethodId = $lockedDeliveryMethodId ?? null)
@php($returnMethodId = $returnToMethodId ?? $assignment?->delivery_method_id ?? $lockedMethodId)
@php($cancelUrl = $returnMethodId ? route('admin.master-data.delivery-methods.manage', $returnMethodId) : route('admin.master-data.governorate-delivery-methods.index'))
<div class="page-heading"><div><h2 class="fs-18 mb-1">{{ $assignment ? 'تعديل الربط' : 'ربط جديد' }}</h2><p>اربط طريقة توصيل بمحافظة وحدد الرسوم.</p></div></div>
<div class="card"><div class="card-body">
<form method="POST" action="{{ $assignment ? route('admin.master-data.governorate-delivery-methods.update', $assignment) : route('admin.master-data.governorate-delivery-methods.store') }}">
@csrf
@if($assignment) @method('PUT') @endif
@if($returnMethodId)
    <input type="hidden" name="return_to_method" value="1">
    <input type="hidden" name="from_method_manage" value="1">
@endif
<div class="row g-3">
    <div class="col-md-6">
        <label for="governorate_id" class="form-label required">المحافظة</label>
        <select class="form-control @error('governorate_id') is-invalid @enderror" data-choices id="governorate_id" name="governorate_id" required>
            <option value="">اختر المحافظة</option>
            @foreach($governorates as $governorate)
                <option value="{{ $governorate->id }}" @selected(old('governorate_id', $assignment?->governorate_id) == $governorate->id)>{{ $governorate->name_ar }}</option>
            @endforeach
        </select>
        @error('governorate_id')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="delivery_method_id" class="form-label required">طريقة التوصيل</label>
        @if($lockedMethodId && ! $assignment)
            <input type="hidden" name="delivery_method_id" value="{{ $lockedMethodId }}">
            @php($lockedMethod = $deliveryMethods->firstWhere('id', $lockedMethodId))
            <input type="text" class="form-control bg-light" value="{{ $lockedMethod ? $lockedMethod->name_ar.' ('.$lockedMethod->code.')' : $lockedMethodId }}" readonly>
        @else
            <select class="form-control @error('delivery_method_id') is-invalid @enderror" data-choices id="delivery_method_id" name="delivery_method_id" required>
                <option value="">اختر طريقة التوصيل</option>
                @foreach($deliveryMethods as $method)
                    <option value="{{ $method->id }}" @selected(old('delivery_method_id', $assignment?->delivery_method_id ?? $lockedMethodId) == $method->id)>{{ $method->name_ar }} ({{ $method->code }})</option>
                @endforeach
            </select>
        @endif
        @error('delivery_method_id')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="delivery_fee" class="form-label required">رسوم التوصيل</label>
        <input type="number" class="form-control text-end @error('delivery_fee') is-invalid @enderror" id="delivery_fee" name="delivery_fee" value="{{ old('delivery_fee', $assignment?->delivery_fee ?? '0.00') }}" min="0" step="0.01" required dir="ltr">
        @error('delivery_fee')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="sort_order" class="form-label required">الترتيب</label>
        <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $assignment?->sort_order ?? 0) }}" min="0" required>
        @error('sort_order')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $assignment?->is_active ?? true))>
            <label class="form-check-label" for="is_active">الربط نشط</label>
        </div>
    </div>
</div>
<div class="form-actions mt-4">
    <a class="btn btn-light" href="{{ $cancelUrl }}">إلغاء</a>
    <button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i> حفظ</button>
</div>
</form>
</div></div>
@endsection
