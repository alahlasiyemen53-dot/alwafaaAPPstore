@extends('layouts.admin')
@section('title', 'خصائص المنتجات | لمّة')
@section('page-title', 'خصائص المنتجات')
@section('content')
<div class="page-heading">
    <div>
        <h2 class="fs-18 mb-1">خصائص المنتجات</h2>
        <p class="text-muted mb-0">إدارة الخصائص والمواصفات القابلة لإعادة الاستخدام في المتغيرات</p>
    </div>
    @if(auth()->user()->hasPermission('admin.attributes.create'))
        <div>
            <a class="btn btn-primary" href="{{ route('admin.attributes.create') }}">
                <i class="ri-add-line me-1"></i> إضافة خاصية جديدة
            </a>
        </div>
    @endif
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.attributes.index') }}" class="row g-3 align-items-end filter-row">
            <div class="col-md-5">
                <label for="search" class="form-label">بحث</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ri-search-line"></i></span>
                    <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="ابحث بالاسم العربي أو الإنجليزي أو الرمز...">
                </div>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">الحالة</label>
                <select class="form-control" data-choices data-choices-search-false id="status" name="status">
                    <option value="">كل الحالات</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>معطل</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-filter-3-line me-1"></i> تصفية</button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('admin.attributes.index') }}" class="btn btn-light"><i class="ri-refresh-line"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>اسم الخاصية</th>
                        <th>الرمز البرمجي</th>
                        <th>عدد القيم</th>
                        <th>الترتيب</th>
                        <th>الحالة</th>
                        <th class="text-end" style="width: 220px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attributes as $attribute)
                        <tr>
                            <td>{{ $attribute->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $attribute->name_ar }}</div>
                                @if($attribute->name_en)
                                    <small class="text-muted" dir="ltr">{{ $attribute->name_en }}</small>
                                @endif
                            </td>
                            <td><code class="text-primary">{{ $attribute->code }}</code></td>
                            <td>
                                <a href="{{ route('admin.attributes.values.index', $attribute) }}" class="badge bg-info-subtle text-info border border-info-subtle fs-12 text-decoration-none">
                                    <i class="ri-list-check me-1"></i> {{ $attribute->values_count }} قيم
                                </a>
                            </td>
                            <td>{{ $attribute->sort_order }}</td>
                            <td>
                                @if($attribute->is_active)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">معطل</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('admin.attributes.values.index', $attribute) }}" class="btn btn-sm btn-soft-info" title="إدارة القيم">
                                        <i class="ri-list-settings-line"></i> القيم
                                    </a>
                                    @if(auth()->user()->hasPermission('admin.attributes.update'))
                                        <a href="{{ route('admin.attributes.edit', $attribute) }}" class="btn btn-sm btn-soft-primary" title="تعديل">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                    @endif
                                    @if($attribute->is_active && auth()->user()->hasPermission('admin.attributes.deactivate'))
                                        <form method="POST" action="{{ route('admin.attributes.deactivate', $attribute) }}" class="d-inline" data-confirm="هل تريد تعطيل الخاصية '{{ $attribute->name_ar }}'؟">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-soft-danger" title="تعطيل"><i class="ri-forbid-line"></i></button>
                                        </form>
                                    @elseif(!$attribute->is_active && auth()->user()->hasPermission('admin.attributes.activate'))
                                        <form method="POST" action="{{ route('admin.attributes.activate', $attribute) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-soft-success" title="تفعيل"><i class="ri-check-line"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div class="avatar-md mx-auto mb-3">
                                    <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                        <i class="ri-sound-module-line"></i>
                                    </div>
                                </div>
                                <h5 class="fs-14 mb-1">لا توجد خصائص مسجلة</h5>
                                <p class="text-muted mb-0">لم يتم العثور على أي خصائص مسجلة في النظام حتى الآن.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($attributes->hasPages())
        <div class="card-footer py-2">
            {{ $attributes->links() }}
        </div>
    @endif
</div>
@endsection
