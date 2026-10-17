@extends('layouts.admin')
@section('title', 'إدارة العملاء | لمّة')
@section('page-title', 'العملاء')

@section('content')
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">إجمالي العملاء</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded fs-3">
                            <i class="ri-team-line text-primary"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0">{{ number_format($metrics['total']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">حسابات نشطة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded fs-3">
                            <i class="ri-checkbox-circle-line text-success"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-success">{{ number_format($metrics['active']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">هواتف موثقة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-info-subtle rounded fs-3">
                            <i class="ri-shield-check-line text-info"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-info">{{ number_format($metrics['verified']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">هواتف غير موثقة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle rounded fs-3">
                            <i class="ri-shield-flash-line text-warning"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-warning">{{ number_format($metrics['unverified']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0"><i class="ri-user-heart-line me-1 text-primary"></i> قائمة العملاء ({{ $customers->total() }})</h4>
                @if(auth()->user()->hasPermission('admin.customers.create'))
                    <a href="{{ route('admin.customers.create') }}" class="btn btn-primary">
                        <i class="ri-add-line me-1"></i> إضافة عميل جديد
                    </a>
                @endif
            </div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.customers.index') }}" class="row g-2 filter-row">
                    <div class="col-md-3">
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="البحث بالاسم، الهاتف، أو البريد...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" data-choices data-choices-search-false name="status">
                            <option value="">جميع الحالات</option>
                            <option value="active" @selected(request('status') === 'active')>نشط فقط</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>معطل فقط</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" data-choices data-choices-search-false name="verification">
                            <option value="">حالة التوثيق</option>
                            <option value="verified" @selected(request('verification') === 'verified')>موثق فقط</option>
                            <option value="unverified" @selected(request('verification') === 'unverified')>غير موثق فقط</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" data-choices data-choices-search-false name="tier_id">
                            <option value="">جميع المستويات</option>
                            @foreach($availableTiers as $tierOption)
                                <option value="{{ $tierOption->id }}" @selected(request('tier_id') == $tierOption->id)>{{ $tierOption->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-search-line me-1"></i> تصفية</button>
                        @if(request()->hasAny(['search', 'status', 'verification', 'method', 'tier_id']))
                            <a href="{{ route('admin.customers.index') }}" class="btn btn-light"><i class="ri-close-line me-1"></i> إعادة ضبط</a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>العميل</th>
                                <th>الهاتف</th>
                                <th>المستوى</th>
                                <th>حالة التوثيق</th>
                                <th>العناوين</th>
                                <th>الحالة</th>
                                <th>تاريخ الانضمام</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customers as $customer)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-2 flex-shrink-0">
                                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fw-bold">
                                                    {{ mb_substr($customer->name, 0, 1) }}
                                                </span>
                                            </div>
                                            <div>
                                                <a href="{{ route('admin.customers.show', $customer) }}" class="fw-semibold text-dark fs-14 text-decoration-none">
                                                    {{ $customer->name }}
                                                </a>
                                                @if($customer->email)
                                                    <div class="text-muted fs-12" dir="ltr">{{ $customer->email }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-medium" dir="ltr">{{ $customer->phone }}</span>
                                        @if($customer->normalized_phone && $customer->normalized_phone !== $customer->phone)
                                            <div class="text-muted fs-11" dir="ltr">{{ $customer->normalized_phone }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($customer->tier)
                                            <span class="badge {{ $customer->tier->is_default ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary' }} fs-12">
                                                <i class="{{ $customer->tier->is_default ? 'ri-shield-user-line' : 'ri-vip-crown-line' }} me-1"></i>
                                                {{ $customer->tier->name_ar }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted">غير محدد</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($customer->isPhoneVerified())
                                            <span class="badge {{ $customer->phone_verification_method?->badgeClass() ?? 'bg-success-subtle text-success' }} fs-12">
                                                <i class="{{ $customer->phone_verification_method?->icon() ?? 'ri-shield-check-line' }} me-1"></i>
                                                {{ $customer->phone_verification_method?->label() ?? 'موثق' }}
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-12">
                                                <i class="ri-shield-flash-line me-1"></i> غير موثق
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fs-12">
                                            <i class="ri-map-pin-line me-1 text-muted"></i> {{ $customer->addresses_count }} عنوان
                                        </span>
                                    </td>
                                    <td>
                                        @if($customer->is_active)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">معطل</span>
                                        @endif
                                    </td>
                                    <td class="text-muted fs-12">
                                        {{ $customer->created_at->format('Y-m-d') }}
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-subtle-primary" title="عرض التفاصيل">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @if(auth()->user()->hasPermission('admin.customers.update'))
                                                <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-sm btn-subtle-info" title="تعديل">
                                                    <i class="ri-pencil-line"></i>
                                                </a>
                                            @endif
                                            @if(auth()->user()->hasPermission($customer->is_active ? 'admin.customers.deactivate' : 'admin.customers.activate'))
                                                <form action="{{ route($customer->is_active ? 'admin.customers.deactivate' : 'admin.customers.activate', $customer) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm {{ $customer->is_active ? 'btn-subtle-warning' : 'btn-subtle-success' }}" title="{{ $customer->is_active ? 'تعطيل الحساب' : 'تفعيل الحساب' }}">
                                                        <i class="{{ $customer->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-3">
                                            <span class="avatar-title bg-light text-muted rounded-circle fs-2">
                                                <i class="ri-user-unfollow-line"></i>
                                            </span>
                                        </div>
                                        <h5 class="text-muted">لا يوجد عملاء</h5>
                                        <p class="text-muted mb-0">لم يتم العثور على أي عملاء مسجلين بهذه المعايير.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($customers->hasPages())
                <div class="card-footer py-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="text-muted fs-13">
                            عرض {{ $customers->firstItem() }} إلى {{ $customers->lastItem() }} من أصل {{ $customers->total() }} عميل
                        </div>
                        <div>
                            {{ $customers->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
