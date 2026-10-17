@extends('layouts.admin')

@section('title', 'باقات المنتجات والعروض المجمعة')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">باقات المنتجات (Bundles)</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item">التسويق والعروض</li>
                        <li class="breadcrumb-item active">باقات المنتجات</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-check-double-line me-1 align-middle"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-0">
                    <div class="row align-items-center gy-3">
                        <div class="col-sm">
                            <h5 class="card-title mb-0">سجل باقات المنتجات</h5>
                        </div>
                        <div class="col-sm-auto">
                            @if(auth()->user()->hasPermission('admin.bundles.create'))
                                <a href="{{ route('admin.marketing.bundles.create') }}" class="btn btn-primary add-btn">
                                    <i class="ri-add-line align-bottom me-1"></i> إنشاء باقة جديدة
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body border border-dashed border-end-0 border-start-0">
                    <form method="GET" action="{{ route('admin.marketing.bundles.index') }}" class="row g-3 align-items-end filter-row">
                        <div class="row g-3">
                            <div class="col-xxl-4 col-sm-6">
                                <div class="search-box">
                                    <input type="text" name="search" class="form-control" placeholder="بحث بالكود أو اسم الباقة..." value="{{ $filters['search'] ?? '' }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                            <div class="col-xxl-3 col-sm-4">
                                <select name="pricing_type" class="form-select">
                                    <option value="">-- كل أنواع التسعير --</option>
                                    <option value="fixed_bundle_price" {{ ($filters['pricing_type'] ?? '') === 'fixed_bundle_price' ? 'selected' : '' }}>سعر إجمالي محدد للباقة</option>
                                    <option value="percentage_discount" {{ ($filters['pricing_type'] ?? '') === 'percentage_discount' ? 'selected' : '' }}>نسبة خصم على المجموع (%)</option>
                                    <option value="fixed_amount_discount" {{ ($filters['pricing_type'] ?? '') === 'fixed_amount_discount' ? 'selected' : '' }}>مبلغ خصم ثابت على الباقة</option>
                                </select>
                            </div>
                            <div class="col-xxl-3 col-sm-4">
                                <select name="is_active" class="form-select">
                                    <option value="">-- كل الحالات --</option>
                                    <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>نشطة</option>
                                    <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>معطلة</option>
                                </select>
                            </div>
                            <div class="col-xxl-2 col-sm-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">تصفية</button>
                                <a href="{{ route('admin.marketing.bundles.index') }}" class="btn btn-light w-100">إعادة تعيين</a>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive table-card mb-1 mt-3">
                        <table class="table table-nowrap align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>الكود</th>
                                    <th>اسم الباقة</th>
                                    <th>طريقة التسعير</th>
                                    <th>القيمة</th>
                                    <th>عدد المكونات</th>
                                    <th>فترة الصلاحية</th>
                                    <th>الأولوية</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bundles as $bundle)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-primary font-monospace fs-13 border border-primary-subtle px-2 py-1">
                                                {{ $bundle->code }}
                                            </span>
                                        </td>
                                        <td>{{ $bundle->name }}</td>
                                        <td><span class="badge bg-primary-subtle text-primary">{{ $bundle->pricing_type->label() }}</span></td>
                                        <td class="fw-semibold">
                                            @if($bundle->pricing_type->value === 'percentage_discount')
                                                {{ number_format((float) $bundle->value, 2) }}%
                                            @else
                                                {{ number_format((float) $bundle->value, 2) }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info fs-12">
                                                <i class="ri-stack-line me-1"></i> {{ $bundle->items_count }} منتجات/متغيرات
                                            </span>
                                        </td>
                                        <td>
                                            <small class="d-block text-muted">من: {{ $bundle->starts_at ? $bundle->starts_at->format('Y-m-d H:i') : 'مفتوح' }}</small>
                                            <small class="d-block text-muted">إلى: {{ $bundle->ends_at ? $bundle->ends_at->format('Y-m-d H:i') : 'مفتوح' }}</small>
                                        </td>
                                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $bundle->priority }}</span></td>
                                        <td>
                                            @if($bundle->is_active)
                                                <span class="badge bg-success-subtle text-success">نشطة</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">معطلة</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                @if(auth()->user()->hasPermission('admin.bundles.update'))
                                                    <a href="{{ route('admin.marketing.bundles.edit', $bundle) }}" class="btn btn-sm btn-soft-primary">
                                                        <i class="ri-pencil-line"></i> تعديل
                                                    </a>
                                                @endif
                                                @if(auth()->user()->hasPermission('admin.bundles.activate') || auth()->user()->hasPermission('admin.bundles.deactivate'))
                                                    <form method="POST" action="{{ route('admin.marketing.bundles.toggle', $bundle) }}" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-sm {{ $bundle->is_active ? 'btn-soft-danger' : 'btn-soft-success' }}">
                                                            {{ $bundle->is_active ? 'تعطيل' : 'تفعيل' }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            لا توجد باقات منتجات مسجلة تطابق معايير البحث.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                        {{ $bundles->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
