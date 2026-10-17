@extends('layouts.admin')

@section('title', 'كوبونات الخصم')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">كوبونات الخصم والقسائم</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item">التسويق والعروض</li>
                        <li class="breadcrumb-item active">كوبونات الخصم</li>
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
                            <h5 class="card-title mb-0">سجل كوبونات الخصم</h5>
                        </div>
                        <div class="col-sm-auto">
                            @if(auth()->user()->hasPermission('admin.coupons.create'))
                                <a href="{{ route('admin.marketing.coupons.create') }}" class="btn btn-primary add-btn">
                                    <i class="ri-add-line align-bottom me-1"></i> إنشاء كوبون جديد
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body border border-dashed border-end-0 border-start-0">
                    <form method="GET" action="{{ route('admin.marketing.coupons.index') }}" class="row g-3 align-items-end filter-row">
                        <div class="row g-3">
                            <div class="col-xxl-4 col-sm-6">
                                <div class="search-box">
                                    <input type="text" name="search" class="form-control" placeholder="بحث بكود الكوبون أو الاسم..." value="{{ $filters['search'] ?? '' }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                            <div class="col-xxl-3 col-sm-4">
                                <select name="discount_type" class="form-select">
                                    <option value="">-- كل أنواع الخصم --</option>
                                    <option value="percentage" {{ ($filters['discount_type'] ?? '') === 'percentage' ? 'selected' : '' }}>نسبة مئوية (%)</option>
                                    <option value="fixed_amount" {{ ($filters['discount_type'] ?? '') === 'fixed_amount' ? 'selected' : '' }}>مبلغ خصم ثابت</option>
                                </select>
                            </div>
                            <div class="col-xxl-3 col-sm-4">
                                <select name="is_active" class="form-select">
                                    <option value="">-- كل الحالات --</option>
                                    <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>نشط</option>
                                    <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>معطل</option>
                                </select>
                            </div>
                            <div class="col-xxl-2 col-sm-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">تصفية</button>
                                <a href="{{ route('admin.marketing.coupons.index') }}" class="btn btn-light w-100">إعادة تعيين</a>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive table-card mb-1 mt-3">
                        <table class="table table-nowrap align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>كود الكوبون</th>
                                    <th>اسم الكوبون</th>
                                    <th>نوع الخصم</th>
                                    <th>قيمة الخصم</th>
                                    <th>الحد الأدنى للطلب</th>
                                    <th>الاستخدام / الحد</th>
                                    <th>فترة الصلاحية</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($coupons as $coupon)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-primary font-monospace fs-13 border border-primary-subtle px-2 py-1">
                                                {{ $coupon->code }}
                                            </span>
                                        </td>
                                        <td>{{ $coupon->name }}</td>
                                        <td><span class="badge bg-primary-subtle text-primary">{{ $coupon->discount_type->label() }}</span></td>
                                        <td class="fw-semibold">
                                            @if($coupon->discount_type->value === 'percentage')
                                                {{ number_format((float) $coupon->discount_value, 2) }}%
                                                @if($coupon->maximum_discount_amount)
                                                    <small class="text-muted d-block">(سقف: {{ number_format((float) $coupon->maximum_discount_amount, 2) }})</small>
                                                @endif
                                            @else
                                                {{ number_format((float) $coupon->discount_value, 2) }}
                                            @endif
                                        </td>
                                        <td>
                                            {{ $coupon->minimum_order_amount ? number_format((float) $coupon->minimum_order_amount, 2) : 'بدون حد' }}
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info">
                                                {{ $coupon->active_usages_count }} / {{ $coupon->usage_limit ?? '∞' }}
                                            </span>
                                            @if($coupon->per_customer_limit)
                                                <small class="text-muted d-block">({{ $coupon->per_customer_limit }} لكل عميل)</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="d-block text-muted">من: {{ $coupon->starts_at ? $coupon->starts_at->format('Y-m-d H:i') : 'مفتوح' }}</small>
                                            <small class="d-block text-muted">إلى: {{ $coupon->ends_at ? $coupon->ends_at->format('Y-m-d H:i') : 'مفتوح' }}</small>
                                        </td>
                                        <td>
                                            @if($coupon->is_active)
                                                <span class="badge bg-success-subtle text-success">نشط</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">معطل</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('admin.marketing.coupons.usages', $coupon) }}" class="btn btn-sm btn-soft-info" title="سجل الاستخدامات">
                                                    <i class="ri-history-line"></i> الاستخدامات
                                                </a>
                                                @if(auth()->user()->hasPermission('admin.coupons.update'))
                                                    <a href="{{ route('admin.marketing.coupons.edit', $coupon) }}" class="btn btn-sm btn-soft-primary">
                                                        <i class="ri-pencil-line"></i> تعديل
                                                    </a>
                                                @endif
                                                @if(auth()->user()->hasPermission('admin.coupons.activate') || auth()->user()->hasPermission('admin.coupons.deactivate'))
                                                    <form method="POST" action="{{ route('admin.marketing.coupons.toggle', $coupon) }}" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-sm {{ $coupon->is_active ? 'btn-soft-danger' : 'btn-soft-success' }}">
                                                            {{ $coupon->is_active ? 'تعطيل' : 'تفعيل' }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="text-muted">لا توجد كوبونات خصم مسجلة تطابق معايير البحث.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                        {{ $coupons->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
