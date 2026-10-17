@extends('layouts.admin')
@section('title', 'تقرير التوصيل | الوفاء استور')
@section('page-title', 'تقرير التوصيل')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0"><h5 class="card-title mb-0">أداء عمليات التوصيل</h5></div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">تم التسليم</p>
                    <h4 class="fs-20 mb-0 text-success">{{ $report['status_counts']['delivered'] }}</h4>
                </div></div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">تعذّر التسليم</p>
                    <h4 class="fs-20 mb-0 text-danger">{{ $report['status_counts']['failed'] }}</h4>
                </div></div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">مرتجع للمستودع</p>
                    <h4 class="fs-20 mb-0">{{ $report['status_counts']['returned_to_warehouse'] }}</h4>
                </div></div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">نسبة النجاح</p>
                    <h4 class="fs-20 mb-0">{{ $report['success_rate'] }}%</h4>
                </div></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>رقم الشحنة</th><th>الطلب</th><th>المندوب</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                    @forelse($report['drivers'] as $delivery)
                        <tr>
                            <td>{{ $delivery->delivery_number }}</td>
                            <td>{{ $delivery->order->order_number ?? '-' }}</td>
                            <td>{{ $delivery->driver->name ?? '-' }}</td>
                            <td><span class="badge {{ $delivery->status->badgeClass() }}">{{ $delivery->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">لا توجد نتائج مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $report['drivers']->links() }}</div>
    </div>
</div>
@endsection
