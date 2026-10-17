@extends('layouts.admin')
@section('title', ($record ? 'تعديل ' : 'إضافة ').$entityLabel.' | الوفاء استور')
@section('page-title', $record ? 'تعديل '.$entityLabel : 'إضافة '.$entityLabel)
@section('content')
<div class="row justify-content-center"><div class="col-xl-9"><div class="card"><div class="card-header"><h2 class="card-title mb-0">بيانات السجل</h2></div><div class="card-body"><form method="POST" action="{{ $record ? route("admin.master-data.$entity.update",['record'=>$record->id]) : route("admin.master-data.$entity.store") }}">@csrf @if($record) @method('PUT') @endif
<div class="row g-3">
@if($entity === 'countries')
<div class="col-md-4">
    @if($record)
        <label class="form-label">رمز الدولة (ISO)</label>
        <input type="text" class="form-control bg-light" value="{{ $record->code }}" readonly dir="ltr">
        <div class="form-text fs-12 text-muted">رمز ISO ثابت بعد الإنشاء. لتغييره أنشئ دولة جديدة أو عطّل الحالية.</div>
    @else
        <label for="code" class="form-label required">رمز الدولة (ISO)</label>
        <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" maxlength="2" required dir="ltr" placeholder="YE">
        @error('code')<span class="field-error">{{ $message }}</span>@enderror
    @endif
</div>
<div class="col-md-4">
    <label for="calling_code" class="form-label required">مفتاح الاتصال</label>
    <input class="form-control @error('calling_code') is-invalid @enderror" id="calling_code" name="calling_code" value="{{ old('calling_code',$record?->calling_code ?? '+967') }}" required dir="ltr">
    @error('calling_code')<span class="field-error">{{ $message }}</span>@enderror
</div>
<div class="col-md-4">
    <label for="sort_order" class="form-label required">ترتيب العرض</label>
    <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order',$record?->sort_order ?? 0) }}" min="0" required>
    @error('sort_order')<span class="field-error">{{ $message }}</span>@enderror
</div>
@endif
@if(in_array($entity,['countries','currencies','units','brands','governorates','cities','districts','categories','warehouses','delivery-methods']))<div class="col-md-6"><label for="name_ar" class="form-label required">الاسم بالعربية</label><input class="form-control @error('name_ar') is-invalid @enderror" id="name_ar" name="name_ar" value="{{ old('name_ar',$record?->name_ar) }}" required>@error('name_ar')<span class="field-error">{{ $message }}</span>@enderror</div><div class="col-md-6"><label for="name_en" class="form-label">الاسم بالإنجليزية</label><input class="form-control @error('name_en') is-invalid @enderror" id="name_en" name="name_en" value="{{ old('name_en',$record?->name_en) }}" dir="ltr">@error('name_en')<span class="field-error">{{ $message }}</span>@enderror</div>@endif
@if(in_array($entity,['warehouses','delivery-methods','currencies','units']) && $record)<div class="col-md-6"><label class="form-label">الرمز البرمجي</label><input type="text" class="form-control bg-light" value="{{ $record->code }}" readonly dir="ltr"></div>@endif
@if(in_array($entity,['brands','governorates','cities','districts','categories']))
<div class="col-md-6">
    <label for="slug" class="form-label">المعرّف النصي (Slug)</label>
    @if($record)
        <div class="input-group">
            <input type="text" class="form-control bg-light" id="slug" value="{{ $record->slug }}" readonly dir="ltr">
            <span class="input-group-text text-muted"><i class="ri-lock-line" title="غير قابل للتعديل"></i></span>
        </div>
        <small class="text-muted">المعرّف النصي ثابت في الروابط ولا يتغير عند تعديل الاسم.</small>
    @else
        <div class="form-control bg-light text-muted d-flex align-items-center justify-content-between">
            <span class="fst-italic"><i class="ri-magic-line me-1 text-primary"></i> يتم توليده تلقائياً من الاسم</span>
            <span class="badge bg-primary-subtle text-primary">تلقائي</span>
        </div>
        <small class="text-muted">يقوم النظام بتوليد معرّف نصي فريد وثابت تلقائياً عند الحفظ.</small>
    @endif
</div>
@endif
@if($entity === 'governorates')<div class="col-md-6"><label for="country_id" class="form-label required">الدولة</label><select class="form-control" data-choices id="country_id" name="country_id" required><option value="">اختر الدولة</option>@foreach($options['countries'] as $option)<option value="{{ $option->id }}" @selected(old('country_id',$record?->country_id)==$option->id)>{{ $option->name_ar }}</option>@endforeach</select></div>@endif
@if($entity === 'delivery-methods')
<div class="col-12"><p class="form-text text-muted mb-0">الرمز البرمجي يُولَّد تلقائياً. الرسوم تُدار من صفحة «إدارة» لكل طريقة توصيل (ربط المحافظات).</p></div>
<div class="col-md-6"><label for="estimated_delivery_ar" class="form-label">مدة التوصيل (عربي)</label><input class="form-control @error('estimated_delivery_ar') is-invalid @enderror" id="estimated_delivery_ar" name="estimated_delivery_ar" value="{{ old('estimated_delivery_ar',$record?->estimated_delivery_ar) }}">@error('estimated_delivery_ar')<span class="field-error">{{ $message }}</span>@enderror</div>
<div class="col-md-6"><label for="estimated_delivery_en" class="form-label">مدة التوصيل (إنجليزي)</label><input class="form-control @error('estimated_delivery_en') is-invalid @enderror" id="estimated_delivery_en" name="estimated_delivery_en" value="{{ old('estimated_delivery_en',$record?->estimated_delivery_en) }}" dir="ltr">@error('estimated_delivery_en')<span class="field-error">{{ $message }}</span>@enderror</div>
<div class="col-md-6"><label for="sort_order" class="form-label required">الترتيب</label><input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order',$record?->sort_order ?? 0) }}" min="0" required>@error('sort_order')<span class="field-error">{{ $message }}</span>@enderror</div>
@endif
@if($entity === 'currencies')<div class="col-md-3"><label for="symbol" class="form-label">العلامة</label><input class="form-control" id="symbol" name="symbol" value="{{ old('symbol',$record?->symbol) }}"></div><div class="col-md-3"><label for="decimal_places" class="form-label required">المنازل العشرية</label><input type="number" class="form-control" id="decimal_places" name="decimal_places" value="{{ old('decimal_places',$record?->decimal_places ?? 2) }}" min="0" max="8" required></div>@endif
@if($entity === 'units')<div class="col-md-6"><label for="decimal_places" class="form-label required">المنازل العشرية</label><input type="number" class="form-control" id="decimal_places" name="decimal_places" value="{{ old('decimal_places',$record?->decimal_places ?? 0) }}" min="0" max="8" required></div>@endif
@if($entity === 'categories')<div class="col-md-6"><label for="parent_id" class="form-label">التصنيف الأب</label><select class="form-control" data-choices id="parent_id" name="parent_id"><option value="">تصنيف رئيسي</option>@foreach($options['categories'] as $option)@if(!$record || $option->id !== $record->id)<option value="{{ $option->id }}" @selected(old('parent_id',$record?->parent_id)==$option->id)>{{ $option->name_ar }}</option>@endif @endforeach</select></div><div class="col-md-6"><label for="sort_order" class="form-label required">الترتيب</label><input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order',$record?->sort_order ?? 0) }}" min="0" required></div>@endif
@if($entity === 'cities')<div class="col-md-6"><label for="governorate_id" class="form-label required">المحافظة</label><select class="form-control" data-choices id="governorate_id" name="governorate_id" required><option value="">اختر المحافظة</option>@foreach($options['governorates'] as $option)<option value="{{ $option->id }}" @selected(old('governorate_id',$record?->governorate_id)==$option->id)>{{ $option->name_ar }}</option>@endforeach</select></div>@endif
@if($entity === 'districts')<div class="col-md-6"><label for="city_id" class="form-label required">المدينة</label><select class="form-control" data-choices id="city_id" name="city_id" required><option value="">اختر المدينة</option>@foreach($options['cities'] as $option)<option value="{{ $option->id }}" @selected(old('city_id',$record?->city_id)==$option->id)>{{ $option->name_ar }}</option>@endforeach</select></div>@endif
@if($entity === 'warehouses')<div class="col-md-4"><label for="governorate_id" class="form-label">المحافظة</label><select class="form-control" data-choices id="governorate_id" name="governorate_id"><option value="">بدون تحديد</option>@foreach($options['governorates'] as $option)<option value="{{ $option->id }}" @selected(old('governorate_id',$record?->governorate_id)==$option->id)>{{ $option->name_ar }}</option>@endforeach</select></div><div class="col-md-4"><label for="city_id" class="form-label">المدينة</label><select class="form-control" data-choices id="city_id" name="city_id"><option value="">بدون تحديد</option>@foreach($options['cities'] as $option)<option value="{{ $option->id }}" @selected(old('city_id',$record?->city_id)==$option->id)>{{ $option->name_ar }}</option>@endforeach</select></div><div class="col-md-4"><label for="district_id" class="form-label">المديرية</label><select class="form-control" data-choices id="district_id" name="district_id"><option value="">بدون تحديد</option>@foreach($options['districts'] as $option)<option value="{{ $option->id }}" @selected(old('district_id',$record?->district_id)==$option->id)>{{ $option->name_ar }}</option>@endforeach</select></div><div class="col-md-8"><label for="address_text" class="form-label">العنوان</label><input class="form-control" id="address_text" name="address_text" value="{{ old('address_text',$record?->address_text) }}"></div><div class="col-md-4"><label for="phone" class="form-label">الهاتف</label><input class="form-control" id="phone" name="phone" value="{{ old('phone',$record?->phone) }}" dir="ltr"></div>@endif
@if($entity === 'brands')<div class="col-md-6"><label for="sort_order" class="form-label required">الترتيب</label><input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order',$record?->sort_order ?? 0) }}" min="0" required>@error('sort_order')<span class="field-error">{{ $message }}</span>@enderror</div><div class="col-12"><label for="description" class="form-label">الوصف</label><textarea class="form-control" id="description" name="description" rows="4">{{ old('description',$record?->description) }}</textarea></div>@endif
<div class="col-12"><div class="form-check form-switch"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active',$record?->is_active ?? true))><label class="form-check-label" for="is_active">السجل نشط ومتاح للاستخدام (التسجيل والظهور)</label></div></div>
@if(in_array($entity, ['countries', 'governorates']))
@php
    $isDeliveryDefault = $record ? (bool) $record->is_delivery_active : ($entity === 'governorates');
@endphp
<div class="col-12"><div class="form-check form-switch"><input type="hidden" name="is_delivery_active" value="0"><input class="form-check-input" type="checkbox" role="switch" id="is_delivery_active" name="is_delivery_active" value="1" @checked(old('is_delivery_active', $isDeliveryDefault))><label class="form-check-label" for="is_delivery_active">تفعيل التوصيل (متاح كوجهة شحن وتوصيل للطلبات)</label></div><div class="form-text fs-12 text-muted">عند تفعيل هذا الخيار، سيتمكن العملاء من اختيار هذه {{ $entity === 'countries' ? 'الدولة' : 'المحافظة' }} كعنوان توصيل لطلباتهم.</div></div>
@endif
</div><div class="form-actions mt-4"><a class="btn btn-light" href="{{ route("admin.master-data.$entity.index") }}">إلغاء</a><button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i> حفظ البيانات</button></div></form></div></div>

@if($record && in_array($entity, ['brands', 'categories']))
@php
    $mediaCollection = $entity === 'brands' ? 'brand_logo' : 'category_image';
    $mediaUrl = $record->getFirstMediaUrl($mediaCollection, 'small');
    $mediaLabel = $entity === 'brands' ? 'شعار العلامة التجارية' : 'صورة التصنيف';
    $uploadRoute = $entity === 'brands' ? route('admin.master-data.brands.logo.upload', ['brand' => $record->id]) : route('admin.master-data.categories.image.upload', ['category' => $record->id]);
    $deleteRoute = $entity === 'brands' ? route('admin.master-data.brands.logo.delete', ['brand' => $record->id]) : route('admin.master-data.categories.image.delete', ['category' => $record->id]);
@endphp
<div class="card mt-3"><div class="card-header"><h2 class="card-title mb-0">{{ $mediaLabel }}</h2></div><div class="card-body">
<div class="d-flex align-items-center gap-3 mb-3">
@if($mediaUrl)<img src="{{ $mediaUrl }}" alt="{{ $mediaLabel }}" class="avatar-lg rounded border">@else<div class="avatar-lg"><div class="avatar-title bg-light text-muted rounded fs-24"><i class="ri-image-line"></i></div></div>@endif
@if($mediaUrl)<form method="POST" action="{{ $deleteRoute }}" data-confirm="سيتم حذف {{ $mediaLabel }} نهائياً. هل تريد المتابعة؟">@csrf @method('DELETE')<button type="submit" class="btn btn-soft-danger btn-sm"><i class="ri-delete-bin-line me-1"></i> حذف {{ $mediaLabel }}</button></form>@endif
</div>
<form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="row g-2 align-items-end">
@csrf
<div class="col-md-8"><label for="media_file" class="form-label">{{ $mediaUrl ? 'استبدال الملف' : 'رفع ملف جديد' }}</label><input type="file" class="form-control @error('file') is-invalid @enderror" id="media_file" name="file" accept="image/jpeg,image/png,image/webp" required>@error('file')<span class="field-error">{{ $message }}</span>@enderror<div class="form-text fs-12 text-muted">JPEG أو PNG أو WebP، بحد أقصى 2 ميجابايت.</div></div>
<div class="col-md-4"><button type="submit" class="btn btn-soft-primary w-100"><i class="ri-upload-2-line me-1"></i> رفع</button></div>
</form>
</div></div>
@endif

</div></div>
@endsection
