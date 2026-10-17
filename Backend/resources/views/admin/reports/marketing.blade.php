@extends('layouts.admin')
@section('title', 'تقرير التسويق | الوفاء استور')
@section('page-title', 'تقرير التسويق')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0"><h5 class="card-title mb-0">خصومات العروض والكوبونات والباقات</h5></div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        <div class="row mb-3">
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">استخدامات الكوبونات</p>
                    <h4 class="fs-20 mb-0">{{ $report['coupon_usage']['used'] }}</h4>
                    <p class="text-muted mb-0 fs-12">محررة: {{ $report['coupon_usage']['released'] }}</p>
                </div></div>
            </div>
        </div>

        @if(empty($report['discounts']))
            <div class="text-center py-5 text-muted"><i class="ri-inbox-line fs-1 d-block mb-2"></i> لا توجد خصومات تسويقية خلال هذه الفترة.</div>
        @else
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>العملة</th><th class="text-end">خصم العروض</th><th class="text-end">خصم الكوبونات</th><th class="text-end">خصم الباقات</th><th class="text-end">المؤهل</th><th class="text-end">الصافي</th></tr>
                    </thead>
                    <tbody>
                        @foreach($report['discounts'] as $row)
                            <tr>
                                <td>{{ $row['currency_code'] }}</td>
                                <td class="text-end">{{ number_format((float) $row['promotion_discount_total'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $row['coupon_discount_total'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $row['bundle_discount_total'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $row['gross_eligible_amount'], 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) $row['net_amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>رقم الطلب</th><th>العميل</th><th>العملة</th><th class="text-end">خصم العروض</th><th class="text-end">خصم الكوبون</th><th class="text-end">خصم الباقة</th></tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ $order->customer->name ?? '-' }}</td>
                            <td>{{ $order->currency->code ?? '-' }}</td>
                            <td class="text-end">{{ number_format((float) $order->promotion_discount_total, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $order->coupon_discount_total, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $order->bundle_discount_total, 2) }}</td>
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
