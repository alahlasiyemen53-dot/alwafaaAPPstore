@extends('layouts.admin')
@section('title', 'تقرير العملاء | لمّة')
@section('page-title', 'تقرير العملاء')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="card-title mb-0">العملاء الجدد والموثقون</h5>
        @if(auth()->user()->hasPermission('admin.reports.export'))
            <a href="{{ route('admin.reports.customers.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-download-2-line me-1"></i> تصدير CSV</a>
        @endif
    </div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">عملاء جدد</p>
                    <h4 class="fs-20 mb-0">{{ $report['summary']['new_customers'] }}</h4>
                </div></div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">هواتف موثقة</p>
                    <h4 class="fs-20 mb-0 text-success">{{ $report['summary']['verified_customers'] }}</h4>
                </div></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>الاسم</th><th>الهاتف</th><th class="text-end">عدد الطلبات المؤكدة</th><th>آخر طلب</th><th>تاريخ التسجيل</th></tr></thead>
                <tbody>
                    @forelse($report['customers'] as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->phone }}</td>
                            <td class="text-end">{{ $customer->orders_count }}</td>
                            <td>{{ $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d-m-Y') : '-' }}</td>
                            <td>{{ $customer->created_at->format('d-m-Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">لا توجد نتائج مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $report['customers']->links() }}</div>
    </div>
</div>
@endsection
