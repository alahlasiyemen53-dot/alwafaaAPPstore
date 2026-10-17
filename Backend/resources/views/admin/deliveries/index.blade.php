@extends('layouts.admin')
@section('title', 'إدارة عمليات التوصيل | الوفاء استور')
@section('page-title', 'عمليات التوصيل')

@section('content')
<div class="row">
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <p class="text-uppercase fw-medium text-muted mb-0 fs-12">إجمالي الشحنات</p>
                <h4 class="fs-20 fw-semibold mb-0 mt-2">{{ number_format($metrics['total']) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <p class="text-uppercase fw-medium text-muted mb-0 fs-12">قيد التوصيل (مع المندوب)</p>
                <h4 class="fs-20 fw-semibold mb-0 mt-2 text-warning">{{ number_format($metrics['assigned']) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-4 col-sm-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <p class="text-uppercase fw-medium text-muted mb-0 fs-12">تم التسليم بنجاح</p>
                <h4 class="fs-20 fw-semibold mb-0 mt-2 text-success">{{ number_format($metrics['delivered']) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-6 col-sm-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <p class="text-uppercase fw-medium text-muted mb-0 fs-12">تعذر التوصيل (في العهدة)</p>
                <h4 class="fs-20 fw-semibold mb-0 mt-2 text-danger">{{ number_format($metrics['failed']) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-sm-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <p class="text-uppercase fw-medium text-muted mb-0 fs-12">مرتجع للمستودع</p>
                <h4 class="fs-20 fw-semibold mb-0 mt-2 text-info">{{ number_format($metrics['returned']) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0"><i class="ri-e-bike-2-line me-1 text-primary"></i> قائمة عمليات التوصيل ({{ $deliveries->total() }})</h4>
            </div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.deliveries.index') }}" class="row g-2 align-items-center filter-row">
                    <div class="col-md-2">
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="رقم الشحنة، الطلب...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">جميع الحالات</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="driver_id">
                            <option value="">جميع المناديب</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected(request('driver_id') == $driver->id)>
                                    {{ $driver->name }} ({{ $driver->driver_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="ri-calendar-line"></i></span>
                            <input type="text" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" id="filter_from_date" name="from_date" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('from_date')) }}" placeholder="يوم-شهر-سنة" title="من تاريخ">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="ri-calendar-line"></i></span>
                            <input type="text" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" id="filter_to_date" name="to_date" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('to_date')) }}" placeholder="يوم-شهر-سنة" title="إلى تاريخ">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="ri-search-line me-1"></i> تصفية</button>
                        @if(request()->hasAny(['search', 'status', 'driver_id', 'from_date', 'to_date']))
                            <a href="{{ route('admin.deliveries.index') }}" class="btn btn-light"><i class="ri-close-line"></i></a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>رقم الشحنة</th>
                                <th>الطلب</th>
                                <th>المندوب</th>
                                <th>المستلم</th>
                                <th>مبلغ التحصيل (COD)</th>
                                <th>تاريخ الإسناد</th>
                                <th>الحالة</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($deliveries as $delivery)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.deliveries.show', $delivery) }}" class="fw-bold text-primary">
                                            {{ $delivery->delivery_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $delivery->order) }}" class="text-dark">
                                            <code>{{ $delivery->order->order_number }}</code>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fw-medium text-dark">{{ $delivery->driver->name }}</span>
                                        <div class="text-muted fs-12"><span dir="ltr">{{ $delivery->driver->phone }}</span></div>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">{{ $delivery->recipient_name }}</div>
                                        <div class="text-muted fs-12"><span dir="ltr">{{ $delivery->recipient_phone }}</span></div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">
                                            {{ number_format((float)$delivery->cod_amount, 2) }} {{ $delivery->order->currency->code ?? '' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted fs-12">{{ $delivery->assigned_at?->format('d-m-Y H:i') }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $delivery->status->badgeClass() }}-subtle text-{{ $delivery->status->badgeClass() }} fs-12">
                                            {{ $delivery->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.deliveries.show', $delivery) }}" class="btn btn-sm btn-subtle-primary">
                                            <i class="ri-eye-line me-1"></i> التفاصيل
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="ri-e-bike-2-line fs-24 mb-2 d-block"></i>
                                            لم يتم العثور على أي شحنات توصيل مطابقة للبحث.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($deliveries->hasPages())
                <div class="card-footer py-2">
                    {{ $deliveries->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
