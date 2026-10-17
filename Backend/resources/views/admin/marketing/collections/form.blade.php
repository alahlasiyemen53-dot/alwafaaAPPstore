@extends('layouts.admin')
@section('title', ($collection ? 'تعديل مجموعة' : 'مجموعة جديدة').' | لمّة')
@section('page-title', $collection ? 'تعديل مجموعة' : 'مجموعة جديدة')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $collection ? route('admin.marketing.collections.update', $collection) : route('admin.marketing.collections.store') }}">
            @csrf
            @if($collection) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">الاسم عربي</label>
                    <input type="text" name="name_ar" class="form-control" value="{{ old('name_ar', $collection?->name_ar) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">الاسم إنجليزي</label>
                    <input type="text" name="name_en" class="form-control" value="{{ old('name_en', $collection?->name_en) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $collection?->slug) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الترتيب</label>
                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $collection?->sort_order ?? 0) }}">
                </div>
                @if(auth()->user()->hasPermission('admin.product_collections.activate'))
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $collection?->is_active ?? true))>
                        <label class="form-check-label" for="is_active">مفعّلة</label>
                    </div>
                </div>
                @elseif($collection)
                <div class="col-md-3 d-flex align-items-end">
                    <span class="badge {{ $collection->is_active ? 'bg-success-subtle text-success' : 'bg-light text-muted' }}">
                        {{ $collection->is_active ? 'مفعّلة' : 'معطّلة' }}
                    </span>
                </div>
                @endif
                <div class="col-12">
                    <label class="form-label">الوصف</label>
                    <textarea name="description_ar" class="form-control" rows="3">{{ old('description_ar', $collection?->description_ar) }}</textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary">حفظ</button>
                <a href="{{ route('admin.marketing.collections.index') }}" class="btn btn-light">رجوع</a>
            </div>
        </form>
    </div>
</div>
@endsection
