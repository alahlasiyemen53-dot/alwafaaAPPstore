@extends('layouts.admin')
@section('title', 'تقرير المخزون | لمّة')
@section('page-title', 'تقرير المخزون')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="card-title mb-0">أرصدة المخزون الحالية</h5>
        @if(auth()->user()->hasPermission('admin.reports.export'))
            <a href="{{ route('admin.reports.inventory.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-download-2-line me-1"></i> تصدير CSV</a>
        @endif
    </div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">عدد المستودعات</p>
                    <h4 class="fs-20 mb-0">{{ $report['summary']['warehouses_count'] }}</h4>
                </div></div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">مواقف المخزون</p>
                    <h4 class="fs-20 mb-0">{{ $report['summary']['positions_count'] }}</h4>
                </div></div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <p class="text-muted mb-1 fs-13">نفاد المخزون</p>
                    <h4 class="fs-20 mb-0 {{ $report['summary']['out_of_stock_count'] > 0 ? 'text-danger' : '' }}">{{ $report['summary']['out_of_stock_count'] }}</h4>
                </div></div>
            </div>
        </div>

        @if(!empty($report['warehouses']))
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light"><tr><th>المستودع</th><th class="text-end">الكمية</th><th class="text-end">المحجوز</th><th class="text-end">المتاح</th></tr></thead>
                <tbody>
                    @foreach($report['warehouses'] as $row)
                        <tr>
                            <td>{{ $row['warehouse_name'] }}</td>
                            <td class="text-end">{{ number_format((float) $row['on_hand'], 2) }}</td>
                            <td class="text-end">{{ number_format((float) $row['reserved'], 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $row['available'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>المستودع</th><th>المنتج</th><th>SKU</th><th class="text-end">الكمية</th><th class="text-end">المحجوز</th><th class="text-end">المتاح</th></tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $inventory)
                        @php($available = max(0, (float) $inventory->quantity - (float) $inventory->reserved_quantity))
                        <tr class="{{ $available <= 0 ? 'table-danger' : '' }}">
                            <td>{{ $inventory->warehouse->name_ar ?? '-' }}</td>
                            <td>{{ $inventory->productVariant->product->name_ar ?? '-' }}</td>
                            <td><code>{{ $inventory->productVariant->sku ?? '-' }}</code></td>
                            <td class="text-end">{{ number_format((float) $inventory->quantity, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $inventory->reserved_quantity, 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($available, 2) }}</td>
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
