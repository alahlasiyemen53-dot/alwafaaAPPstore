@extends('layouts.admin')

@section('title', 'مستويات وتصنيفات العملاء (VIP)')
@section('page-title', 'مستويات وتصنيفات العملاء')

@section('content')
<div class="row">
    <!-- KPI Cards -->
    <div class="col-md-4">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">إجمالي المستويات</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2">{{ number_format($totalTiers) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                            <i class="ri-vip-crown-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">المستويات المفعلة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-success">{{ number_format($activeTiers) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                            <i class="ri-checkbox-circle-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">العملاء المصنفين حالياً</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-info">{{ number_format($totalAssignedCustomers) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle text-info rounded fs-3">
                            <i class="ri-team-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="card-title mb-0">قائمة مستويات العملاء</h5>
                    @if(auth()->user()->hasPermission('admin.customer_tiers.create'))
                        <a href="{{ route('admin.customer-tiers.create') }}" class="btn btn-primary btn-sm">
                            <i class="ri-add-line align-bottom me-1"></i> إضافة مستوى جديد
                        </a>
                    @endif
                </div>
            </div>

            <div class="card-body">
                <!-- Filters -->
                <form method="GET" action="{{ route('admin.customer-tiers.index') }}" class="row g-2 mb-3 filter-row">
                    <div class="col-md-6 col-lg-4">
                        <div class="search-box">
                            <input type="text" name="search" class="form-control" placeholder="البحث بالاسم أو الكود..." value="{{ request('search') }}">
                            <i class="ri-search-line search-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <select name="status" class="form-select">
                            <option value="">جميع الحالات</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>مفعل فقط</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>معطل فقط</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-secondary w-100"><i class="ri-filter-2-line me-1"></i> تصفية</button>
                        @if(request()->hasAny(['search', 'status']))
                            <a href="{{ route('admin.customer-tiers.index') }}" class="btn btn-light" title="إلغاء التصفية"><i class="ri-refresh-line"></i></a>
                        @endif
                    </div>
                </form>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" style="width: 50px;">#</th>
                                <th scope="col">اسم المستوى</th>
                                <th scope="col">الكود</th>
                                <th scope="col">النوع</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">العملاء الحاليين</th>
                                <th scope="col">ترتيب العرض</th>
                                <th scope="col">تاريخ الإنشاء</th>
                                <th scope="col" class="text-center" style="width: 150px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tiers as $tier)
                                <tr>
                                    <td>{{ $tier->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-2">
                                                <span class="avatar-title {{ $tier->is_default ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary' }} rounded-circle fs-13">
                                                    <i class="{{ $tier->is_default ? 'ri-shield-user-line' : 'ri-vip-crown-line' }}"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <h6 class="fs-14 mb-0 fw-semibold">{{ $tier->name_ar }}</h6>
                                                @if($tier->name_en)
                                                    <small class="text-muted">{{ $tier->name_en }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark font-monospace fs-12">{{ $tier->code }}</span>
                                    </td>
                                    <td>
                                        @if($tier->is_default)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 fs-12">
                                                <i class="ri-check-double-line me-1"></i> الافتراضي للنظام
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border px-2 py-1 fs-12">مستوى إضافي</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($tier->is_active)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-12">مفعل</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-12">معطل</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.customers.index', ['tier_id' => $tier->id]) }}" class="badge bg-primary-subtle text-primary fs-12 fw-semibold px-2 py-1">
                                            <i class="ri-user-line me-1"></i> {{ number_format($tier->active_customers_count) }} عميل
                                        </a>
                                    </td>
                                    <td>{{ $tier->sort_order }}</td>
                                    <td>{{ $tier->created_at->format('Y-m-d') }}</td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-center">
                                            @if(auth()->user()->hasPermission('admin.customer_tiers.update'))
                                                <a href="{{ route('admin.customer-tiers.edit', $tier) }}" class="btn btn-sm btn-ghost-primary" title="تعديل">
                                                    <i class="ri-pencil-line fs-15"></i>
                                                </a>
                                            @endif

                                            @if(auth()->user()->hasPermission('admin.customer_tiers.activate') || auth()->user()->hasPermission('admin.customer_tiers.deactivate'))
                                                @if(! $tier->is_default)
                                                    <form method="POST" action="{{ route('admin.customer-tiers.toggle-status', $tier) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من تغيير حالة تفعيل هذا المستوى؟');">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-sm {{ $tier->is_active ? 'btn-ghost-warning' : 'btn-ghost-success' }}" title="{{ $tier->is_active ? 'تعطيل المستوى' : 'تفعيل المستوى' }}">
                                                            <i class="{{ $tier->is_active ? 'ri-close-circle-line' : 'ri-checkbox-circle-line' }} fs-15"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <button class="btn btn-sm btn-ghost-muted disabled" title="لا يمكن تعطيل المستوى الافتراضي للنظام" disabled>
                                                        <i class="ri-lock-line fs-15"></i>
                                                    </button>
                                                @endif
                                            @endif

                                            @if(auth()->user()->hasPermission('admin.customer_tiers.update'))
                                                @if($tier->canBeDeleted())
                                                    <form method="POST" action="{{ route('admin.customer-tiers.destroy', $tier) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستوى نهائياً؟');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-ghost-danger" title="حذف">
                                                            <i class="ri-delete-bin-line fs-15"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <button class="btn btn-sm btn-ghost-muted disabled" title="لا يمكن حذف المستوى لوجود سجل عضويات عملاء مرتبط به أو لكونه المستوى الافتراضي" disabled>
                                                        <i class="ri-delete-bin-line fs-15 opacity-50"></i>
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-3">
                                            <div class="avatar-title bg-light text-muted rounded-circle fs-24">
                                                <i class="ri-vip-crown-line"></i>
                                            </div>
                                        </div>
                                        <h5 class="text-muted mb-1">لا توجد مستويات مطابقة</h5>
                                        <p class="text-muted mb-0">لم يتم العثور على أي مستويات عملاء مطابقة للبحث أو التصفية الحالية.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($tiers->hasPages())
                    <div class="mt-3 d-flex justify-content-end">
                        {{ $tiers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
