@extends('layouts.admin')
@section('title', 'تقرير مرتجعات المبيعات | الوفاء استور')
@section('page-title', 'تقرير مرتجعات المبيعات')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="card-title mb-0">المرتجعات المعتمدة</h5>
        @if(auth()->user()->hasPermission('admin.reports.export'))
            <a href="{{ route('admin.reports.returns.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-download-2-line me-1"></i> تصدير CSV</a>
        @endif
    </div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        @if(empty($report['summary']))
            <div class="text-center py-5 text-muted"><i class="ri-inbox-line fs-1 d-block mb-2"></i> لا توجد مرتجعات معتمدة خلال هذه الفترة.</div>
        @else
            <div class="row mb-4">
                @foreach($report['summary'] as $row)
                    <div class="col-md-4 col-xl-3 mb-3">
                        <div class="card card-animate h-100"><div class="card-body">
                            <p class="text-uppercase fw-medium text-muted mb-1 fs-13">{{ $row['currency_code'] }}</p>
                            <h4 class="fs-20 mb-1 text-danger">{{ number_format((float) $row['total_returned'], 2) }}</h4>
                            <p class="text-muted mb-0 fs-12">{{ $row['returns_count'] }} مرتجع</p>
                        </div></div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>رقم المرتجع</th><th>الطلب</th><th>المستودع</th><th>العملة</th><th class="text-end">الإجمالي</th><th>تاريخ الاعتماد</th></tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $return)
                        <tr>
                            <td>{{ $return->return_number }}</td>
                            <td>{{ $return->order->order_number ?? '-' }}</td>
                            <td>{{ $return->warehouse->name_ar ?? '-' }}</td>
                            <td>{{ $return->currency->code ?? '-' }}</td>
                            <td class="text-end fw-semibold text-danger">{{ number_format((float) $return->total, 2) }}</td>
                            <td>{{ $return->posted_at?->format('d-m-Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">لا توجد نتائج مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $report['rows']->links() }}</div>
    </div>
</div>
@endsection
