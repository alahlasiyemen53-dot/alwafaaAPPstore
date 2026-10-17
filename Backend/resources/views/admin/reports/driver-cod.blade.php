@extends('layouts.admin')
@section('title', 'تقرير عهدة مندوبي التوصيل | لمّة')
@section('page-title', 'عهدة مندوبي التوصيل')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="card-title mb-0">المحصل والمسوى والمتبقي</h5>
        @if(auth()->user()->hasPermission('admin.reports.export'))
            <a href="{{ route('admin.reports.driver-cod.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-download-2-line me-1"></i> تصدير CSV</a>
        @endif
    </div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        @if(empty($report['totals_by_currency']))
            <div class="text-center py-5 text-muted"><i class="ri-inbox-line fs-1 d-block mb-2"></i> لا توجد عهد نشطة حتى نهاية هذه الفترة.</div>
        @else
            <div class="row mb-4">
                @foreach($report['totals_by_currency'] as $row)
                    <div class="col-md-4 mb-3">
                        <div class="card h-100"><div class="card-body">
                            <p class="text-uppercase fw-medium text-muted mb-1 fs-13">{{ $row['currency_code'] }}</p>
                            <h4 class="fs-20 mb-1">{{ number_format((float) $row['outstanding'], 2) }}</h4>
                            <p class="text-muted mb-0 fs-12">محصل {{ number_format((float) $row['collected'], 2) }} — مسوى {{ number_format((float) $row['settled'], 2) }}</p>
                        </div></div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>المندوب</th><th>العملة</th><th class="text-end">المحصل</th><th class="text-end">المسوى</th><th class="text-end">المتبقي</th></tr></thead>
                <tbody>
                    @forelse($report['drivers'] as $row)
                        <tr>
                            <td>{{ $row['driver_name'] }}</td>
                            <td>{{ $row['currency_code'] }}</td>
                            <td class="text-end">{{ number_format((float) $row['collected'], 2) }}</td>
                            <td class="text-end">{{ number_format((float) $row['settled'], 2) }}</td>
                            <td class="text-end fw-semibold {{ (float) $row['outstanding'] > 0 ? 'text-warning' : '' }}">{{ number_format((float) $row['outstanding'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">لا توجد نتائج مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $report['drivers']->links() }}</div>
    </div>
</div>
@endsection
