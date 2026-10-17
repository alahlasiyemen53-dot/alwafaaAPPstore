@extends('layouts.admin')
@section('title', 'إدارة مندوبي التوصيل | لمّة')
@section('page-title', 'مندوبو التوصيل')

@section('content')
<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">إجمالي المناديب</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2">{{ number_format($metrics['total']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                            <i class="ri-user-star-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">المناديب النشطون</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-success">{{ number_format($metrics['active']) }}</h4>
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
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">في مهمة توصيل حالياً</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-warning">{{ number_format($metrics['delivering']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                            <i class="ri-e-bike-2-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">المناديب المعطلون</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-secondary">{{ number_format($metrics['inactive']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-secondary-subtle text-secondary rounded fs-3">
                            <i class="ri-pause-circle-line"></i>
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
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0"><i class="ri-user-star-line me-1 text-primary"></i> قائمة مندوبي التوصيل ({{ $drivers->total() }})</h4>
                @if(auth()->user()->hasPermission('admin.drivers.create'))
                    <a href="{{ route('admin.drivers.create') }}" class="btn btn-primary">
                        <i class="ri-add-line me-1"></i> إضافة مندوب جديد
                    </a>
                @endif
            </div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.drivers.index') }}" class="row g-2 filter-row">
                    <div class="col-md-5">
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="البحث بالاسم، الكود، رقم الهاتف، أو لوحة المركبة...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">جميع الحالات</option>
                            <option value="active" @selected(request('status') === 'active')>نشط فقط</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>معطل فقط</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i> تصفية</button>
                        @if(request()->hasAny(['search', 'status', 'vehicle_type']))
                            <a href="{{ route('admin.drivers.index') }}" class="btn btn-light"><i class="ri-close-line me-1"></i> إعادة ضبط</a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>الرمز</th>
                                <th>اسم المندوب</th>
                                <th>الهاتف</th>
                                <th>المركبة</th>
                                <th>رقم اللوحة</th>
                                <th>التوصيلات</th>
                                <th>الحالة</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($drivers as $driver)
                                <tr>
                                    <td><code class="fw-bold text-dark fs-13">{{ $driver->driver_code }}</code></td>
                                    <td>
                                        <span class="fw-semibold text-dark fs-14">{{ $driver->name }}</span>
                                        @if($driver->email)
                                            <div class="text-muted fs-12" dir="ltr">{{ $driver->email }}</div>
                                        @endif
                                    </td>
                                    <td><span dir="ltr" class="fw-medium">{{ $driver->phone }}</span></td>
                                    <td>{{ $driver->vehicle_type ?: 'غير محدد' }}</td>
                                    <td><code>{{ $driver->vehicle_plate_number ?: '-' }}</code></td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info fs-12">
                                            {{ $driver->deliveries_count }} شحنة
                                        </span>
                                    </td>
                                    <td>
                                        @if($driver->is_active)
                                            <span class="badge bg-success-subtle text-success">نشط</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger">معطل</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if(auth()->user()->hasPermission('admin.drivers.update'))
                                                <a href="{{ route('admin.drivers.edit', $driver) }}" class="btn btn-sm btn-subtle-primary" title="تعديل">
                                                    <i class="ri-pencil-line"></i>
                                                </a>
                                            @endif
                                            @if($driver->is_active && auth()->user()->hasPermission('admin.drivers.deactivate'))
                                                <form method="POST" action="{{ route('admin.drivers.toggle-status', $driver) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من تعطيل هذا المندوب؟ لن يتم إسناد شحنات جديدة له.');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-subtle-danger" title="تعطيل">
                                                        <i class="ri-pause-circle-line"></i>
                                                    </button>
                                                </form>
                                            @elseif(!$driver->is_active && auth()->user()->hasPermission('admin.drivers.activate'))
                                                <form method="POST" action="{{ route('admin.drivers.toggle-status', $driver) }}" class="d-inline" onsubmit="return confirm('هل تريد إعادة تفعيل هذا المندوب؟');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-subtle-success" title="تفعيل">
                                                        <i class="ri-checkbox-circle-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="ri-user-star-line fs-24 mb-2 d-block"></i>
                                            لم يتم العثور على أي مندوبي توصيل مطابقين للبحث.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($drivers->hasPages())
                <div class="card-footer py-2">
                    {{ $drivers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
