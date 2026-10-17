@extends('layouts.admin')

@section('title', 'العروض الترويجية')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">العروض الترويجية والتخفيضات</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item">التسويق والعروض</li>
                        <li class="breadcrumb-item active">العروض الترويجية</li>
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
                            <h5 class="card-title mb-0">سجل العروض الترويجية</h5>
                        </div>
                        <div class="col-sm-auto">
                            @if(auth()->user()->hasPermission('admin.promotions.create'))
                                <a href="{{ route('admin.marketing.promotions.create') }}" class="btn btn-primary add-btn">
                                    <i class="ri-add-line align-bottom me-1"></i> إضافة عرض ترويجي جديد
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body border border-dashed border-end-0 border-start-0">
                    <form method="GET" action="{{ route('admin.marketing.promotions.index') }}" class="row g-3 align-items-end filter-row">
                        <div class="row g-3">
                            <div class="col-xxl-4 col-sm-6">
                                <div class="search-box">
                                    <input type="text" name="search" class="form-control" placeholder="بحث بالكود أو الاسم..." value="{{ $filters['search'] ?? '' }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-sm-4">
                                <select name="type" class="form-select">
                                    <option value="">-- كل الأنواع --</option>
                                    <option value="percentage" {{ ($filters['type'] ?? '') === 'percentage' ? 'selected' : '' }}>نسبة مئوية (%)</option>
                                    <option value="fixed_amount" {{ ($filters['type'] ?? '') === 'fixed_amount' ? 'selected' : '' }}>مبلغ خصم ثابت</option>
                                    <option value="fixed_price" {{ ($filters['type'] ?? '') === 'fixed_price' ? 'selected' : '' }}>سعر مخفض ثابت</option>
                                </select>
                            </div>
                            <div class="col-xxl-2 col-sm-4">
                                <select name="scope" class="form-select">
                                    <option value="">-- كل النطاقات --</option>
                                    <option value="all" {{ ($filters['scope'] ?? '') === 'all' ? 'selected' : '' }}>جميع المنتجات</option>
                                    <option value="product" {{ ($filters['scope'] ?? '') === 'product' ? 'selected' : '' }}>منتجات محددة</option>
                                    <option value="variant" {{ ($filters['scope'] ?? '') === 'variant' ? 'selected' : '' }}>متغيرات محددة</option>
                                    <option value="category" {{ ($filters['scope'] ?? '') === 'category' ? 'selected' : '' }}>فئات محددة</option>
                                    <option value="brand" {{ ($filters['scope'] ?? '') === 'brand' ? 'selected' : '' }}>علامات تجارية</option>
                                </select>
                            </div>
                            <div class="col-xxl-2 col-sm-4">
                                <select name="is_active" class="form-select">
                                    <option value="">-- كل الحالات --</option>
                                    <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>نشط</option>
                                    <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>معطل</option>
                                </select>
                            </div>
                            <div class="col-xxl-2 col-sm-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">تصفية</button>
                                <a href="{{ route('admin.marketing.promotions.index') }}" class="btn btn-light w-100">إعادة تعيين</a>
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
                                    <th>اسم العرض</th>
                                    <th>النوع</th>
                                    <th>القيمة</th>
                                    <th>النطاق المستهدف</th>
                                    <th>فترة الصلاحية</th>
                                    <th>الأولوية</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($promotions as $promo)
                                    <tr>
                                        <td><span class="fw-medium font-monospace">{{ $promo->code }}</span></td>
                                        <td>{{ $promo->name }}</td>
                                        <td><span class="badge bg-primary-subtle text-primary">{{ $promo->type->label() }}</span></td>
                                        <td class="fw-semibold">
                                            @if($promo->type->value === 'percentage')
                                                {{ number_format((float) $promo->value, 2) }}%
                                            @else
                                                {{ number_format((float) $promo->value, 2) }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info">{{ $promo->scope->label() }}</span>
                                            @if($promo->targets_count > 0)
                                                <small class="text-muted">({{ $promo->targets_count }} عنصر)</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="d-block text-muted">من: {{ $promo->starts_at ? $promo->starts_at->format('Y-m-d H:i') : 'مفتوح' }}</small>
                                            <small class="d-block text-muted">إلى: {{ $promo->ends_at ? $promo->ends_at->format('Y-m-d H:i') : 'مفتوح' }}</small>
                                        </td>
                                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $promo->priority }}</span></td>
                                        <td>
                                            @if($promo->is_active)
                                                <span class="badge bg-success-subtle text-success">نشط</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">معطل</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                @if(auth()->user()->hasPermission('admin.promotions.update'))
                                                    <a href="{{ route('admin.marketing.promotions.edit', $promo) }}" class="btn btn-sm btn-soft-primary">
                                                        <i class="ri-pencil-line"></i> تعديل
                                                    </a>
                                                @endif
                                                @if(auth()->user()->hasPermission('admin.promotions.activate') || auth()->user()->hasPermission('admin.promotions.deactivate'))
                                                    <form method="POST" action="{{ route('admin.marketing.promotions.toggle', $promo) }}" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-sm {{ $promo->is_active ? 'btn-soft-danger' : 'btn-soft-success' }}">
                                                            {{ $promo->is_active ? 'تعطيل' : 'تفعيل' }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="text-muted">لا توجد عروض ترويجية مسجلة تطابق معايير البحث.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                        {{ $promotions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
