@extends('layouts.admin')
@php
    use App\Enums\PaymentChannel;
    use App\Enums\PaymentFlowType;
    use App\Enums\PaymentProviderCode;
@endphp
@section('title', ($method ? 'تعديل طريقة دفع' : 'إضافة طريقة دفع').' | لمّة')
@section('page-title', $method ? 'تعديل طريقة دفع' : 'إضافة طريقة دفع')
@section('content')
<div class="page-heading"><div><h2 class="fs-18 mb-1">{{ $method ? 'تعديل طريقة الدفع' : 'طريقة دفع جديدة' }}</h2><p>حدد الرمز والمزوّد وسلوك الدفع في التطبيق.</p></div></div>
<div class="card"><div class="card-body">
<form method="POST" action="{{ $method ? route('admin.master-data.customer-payment-methods.update', $method) : route('admin.master-data.customer-payment-methods.store') }}" enctype="multipart/form-data">
@csrf
@if($method) @method('PUT') @endif
<div class="row g-3">
    <div class="col-md-6">
        <label for="code" class="form-label required">الرمز</label>
        @if($method)
            <input type="text" class="form-control bg-light" value="{{ $method->code }}" readonly dir="ltr">
            <input type="hidden" name="code" value="{{ $method->code }}">
        @else
            <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" required dir="ltr">
        @endif
        @error('code')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="provider_code" class="form-label required">مزوّد الدفع</label>
        <select class="form-control @error('provider_code') is-invalid @enderror" data-choices id="provider_code" name="provider_code" required>
            @foreach(PaymentProviderCode::cases() as $provider)
                <option value="{{ $provider->value }}" @selected(old('provider_code', $method?->provider_code?->value) === $provider->value)>{{ $provider->value }}</option>
            @endforeach
        </select>
        @error('provider_code')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="name_ar" class="form-label required">الاسم بالعربية</label>
        <input class="form-control @error('name_ar') is-invalid @enderror" id="name_ar" name="name_ar" value="{{ old('name_ar', $method?->name_ar) }}" required>
        @error('name_ar')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="name_en" class="form-label">الاسم بالإنجليزية</label>
        <input class="form-control @error('name_en') is-invalid @enderror" id="name_en" name="name_en" value="{{ old('name_en', $method?->name_en) }}" dir="ltr">
        @error('name_en')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="description_ar" class="form-label">الوصف (عربي)</label>
        <textarea class="form-control @error('description_ar') is-invalid @enderror" id="description_ar" name="description_ar" rows="2">{{ old('description_ar', $method?->description_ar) }}</textarea>
        @error('description_ar')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="description_en" class="form-label">الوصف (إنجليزي)</label>
        <textarea class="form-control @error('description_en') is-invalid @enderror" id="description_en" name="description_en" rows="2" dir="ltr">{{ old('description_en', $method?->description_en) }}</textarea>
        @error('description_en')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="instructions_ar" class="form-label">التعليمات (عربي)</label>
        <textarea class="form-control @error('instructions_ar') is-invalid @enderror" id="instructions_ar" name="instructions_ar" rows="2">{{ old('instructions_ar', $method?->instructions_ar) }}</textarea>
        @error('instructions_ar')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="instructions_en" class="form-label">التعليمات (إنجليزي)</label>
        <textarea class="form-control @error('instructions_en') is-invalid @enderror" id="instructions_en" name="instructions_en" rows="2" dir="ltr">{{ old('instructions_en', $method?->instructions_en) }}</textarea>
        @error('instructions_en')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-4">
        <label for="channel" class="form-label required">القناة</label>
        <select class="form-control @error('channel') is-invalid @enderror" data-choices data-choices-search-false id="channel" name="channel" required>
            @foreach(PaymentChannel::cases() as $channel)
                <option value="{{ $channel->value }}" @selected(old('channel', $method?->channel?->value) === $channel->value)>{{ $channel->label() }}</option>
            @endforeach
        </select>
        @error('channel')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-4">
        <label for="flow_type" class="form-label required">نوع التدفق</label>
        <select class="form-control @error('flow_type') is-invalid @enderror" data-choices data-choices-search-false id="flow_type" name="flow_type" required>
            @foreach(PaymentFlowType::cases() as $flow)
                <option value="{{ $flow->value }}" @selected(old('flow_type', $method?->flow_type?->value) === $flow->value)>{{ $flow->label() }}</option>
            @endforeach
        </select>
        @error('flow_type')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-4">
        <label for="sort_order" class="form-label required">الترتيب</label>
        <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $method?->sort_order ?? 0) }}" min="0" required>
        @error('sort_order')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="electronic_wallet_account_id" class="form-label">محفظة الخزينة الإلكترونية</label>
        <select class="form-control @error('electronic_wallet_account_id') is-invalid @enderror" data-choices id="electronic_wallet_account_id" name="electronic_wallet_account_id">
            <option value="">— غير مربوطة (COD) —</option>
            @foreach(($electronicWallets ?? []) as $wallet)
                <option value="{{ $wallet->id }}" @selected((string) old('electronic_wallet_account_id', $method?->electronic_wallet_account_id) === (string) $wallet->id)>
                    {{ $wallet->code }} — {{ $wallet->account_label }} ({{ $wallet->provider_name }})
                </option>
            @endforeach
        </select>
        <div class="form-text">إلزامي لجيب وفلوسك: يُورَّد قبض العميل مباشرة إلى هذه المحفظة دون عهدة مندوب.</div>
        @error('electronic_wallet_account_id')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="col-md-6">
        <label for="logo" class="form-label">شعار الطريقة</label>
        <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/*">
        @error('logo')<span class="field-error">{{ $message }}</span>@enderror
        @if($method && $method->getLogoUrl('thumb'))
            <div class="mt-2"><img src="{{ $method->getLogoUrl('thumb') }}" alt="" class="rounded" height="64"></div>
        @endif
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $method?->is_active ?? true))>
            <label class="form-check-label" for="is_active">الطريقة نشطة</label>
        </div>
    </div>
</div>
<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> حفظ</button>
    <a class="btn btn-light" href="{{ route('admin.master-data.customer-payment-methods.index') }}">إلغاء</a>
</div>
</form>
</div></div>
@endsection
