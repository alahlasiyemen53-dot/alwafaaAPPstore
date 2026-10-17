@extends('layouts.admin')
@section('title', 'أقسام الصفحة الرئيسية | لمّة')
@section('page-title', 'أقسام الصفحة الرئيسية')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">الأقسام النشطة</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>النوع</th>
                                <th>العنوان</th>
                                <th>الترتيب</th>
                                <th>الحالة</th>
                                <th class="text-end">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sections as $section)
                                <tr>
                                    <td><code>{{ $section->type->value }}</code></td>
                                    <td>
                                        @if(auth()->user()->hasPermission('admin.home_sections.update'))
                                            <form method="POST" action="{{ route('admin.marketing.home-sections.update', $section) }}" class="row g-2">
                                                @csrf
                                                @method('PUT')
                                                <div class="col-12">
                                                    <input type="text" name="title_ar" class="form-control form-control-sm" value="{{ $section->title_ar }}" required>
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="title_en" class="form-control form-control-sm" value="{{ $section->title_en }}">
                                                </div>
                                                <div class="col-3">
                                                    <input type="number" name="sort_order" class="form-control form-control-sm" value="{{ $section->sort_order }}">
                                                </div>
                                                <div class="col-3">
                                                    <button class="btn btn-sm btn-primary w-100">حفظ</button>
                                                </div>
                                                @if($section->type->value === 'collection')
                                                    <div class="col-12">
                                                        <select name="collection_id" class="form-select form-select-sm">
                                                            <option value="">اختر مجموعة</option>
                                                            @foreach($collections as $collection)
                                                                <option value="{{ $collection->id }}" @selected(($section->configuration['collection_id'] ?? null) == $collection->id)>
                                                                    {{ $collection->name_ar }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @endif
                                            </form>
                                        @else
                                            <div class="fw-semibold">{{ $section->title_ar }}</div>
                                            @if($section->title_en)
                                                <div class="text-muted small">{{ $section->title_en }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    <td>{{ $section->sort_order }}</td>
                                    <td>
                                        @if(auth()->user()->hasPermission('admin.home_sections.activate'))
                                            <form method="POST" action="{{ route('admin.marketing.home-sections.toggle', $section) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-sm {{ $section->is_active ? 'btn-success' : 'btn-secondary' }}">
                                                    {{ $section->is_active ? 'مفعّل' : 'معطّل' }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="badge {{ $section->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                                {{ $section->is_active ? 'مفعّل' : 'معطّل' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if(auth()->user()->hasPermission('admin.home_sections.delete'))
                                            <form method="POST" action="{{ route('admin.marketing.home-sections.destroy', $section) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا القسم نهائياً؟');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-soft-danger" title="حذف">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">لا توجد أقسام — أضف قسماً أو شغّل الـ Seeder.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        @if(auth()->user()->hasPermission('admin.home_sections.create'))
            <div class="card">
                <div class="card-header"><h5 class="mb-0">إضافة قسم</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.marketing.home-sections.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">النوع</label>
                            <select name="type" class="form-select" required>
                                @foreach($types as $type)
                                    <option value="{{ $type->value }}">{{ $type->labelAr() }} ({{ $type->value }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">العنوان عربي</label>
                            <input type="text" name="title_ar" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">العنوان إنجليزي</label>
                            <input type="text" name="title_en" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الترتيب</label>
                            <input type="number" name="sort_order" class="form-control" value="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">مجموعة (لقسم collection)</label>
                            <select name="collection_id" class="form-select">
                                <option value="">—</option>
                                @foreach($collections as $collection)
                                    <option value="{{ $collection->id }}">{{ $collection->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked>
                            <label class="form-check-label" for="is_active">مفعّل</label>
                        </div>
                        <button class="btn btn-primary w-100">إضافة</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
