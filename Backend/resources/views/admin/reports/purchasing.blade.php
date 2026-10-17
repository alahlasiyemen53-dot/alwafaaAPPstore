@extends('layouts.admin')
@section('title', 'تقرير المشتريات | لمّة')
@section('page-title', 'تقرير المشتريات')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="card-title mb-0">فواتير الشراء المرحّلة</h5>
        @if(auth()->user()->hasPermission('admin.reports.export'))
            <a href="{{ route('admin.reports.purchasing.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-download-2-line me-1"></i> تصدير CSV</a>
        @endif
    </div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        @if(empty($report['totals_by_currency']))
            <div class="text-center py-5 text-muted"><i class="ri-inbox-line fs-1 d-block mb-2"></i> لا توجد فواتير مرحّلة خلال هذه الفترة.</div>
        @else
            <div class="row mb-4">
                @foreach($report['totals_by_currency'] as $row)
                    <div class="col-md-4 col-xl-3 mb-3">
                        <div class="card card-animate h-100"><div class="card-body">
                            <p class="text-uppercase fw-medium text-muted mb-1 fs-13">{{ $row['currency_code'] }}</p>
                            <h4 class="fs-20 mb-1">{{ number_format((float) $row['total_amount'], 2) }}</h4>
                            <p class="text-muted mb-0 fs-12">{{ $row['invoices_count'] }} فاتورة</p>
                        </div></div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>رقم الفاتورة</th><th>المورد</th><th>المستودع</th><th>العملة</th><th class="text-end">الإجمالي</th><th>تاريخ الفاتورة</th></tr>
                </thead>
                <tbody>
                    @forelse($report['suppliers'] as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->supplier->name ?? '-' }}</td>
                            <td>{{ $invoice->warehouse->name_ar ?? '-' }}</td>
                            <td>{{ $invoice->currency->code ?? '-' }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $invoice->total, 2) }}</td>
                            <td>{{ $invoice->invoice_date?->format('d-m-Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">لا توجد نتائج مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $report['suppliers']->links() }}</div>
    </div>
</div>
@endsection
