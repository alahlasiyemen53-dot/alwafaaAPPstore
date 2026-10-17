@extends('layouts.admin')
@section('title', 'تقرير محافظ العملاء | لمّة')
@section('page-title', 'تقرير محافظ العملاء')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0"><h5 class="card-title mb-0">التزام محافظ العملاء</h5></div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        @if(empty($report['liability_by_currency']))
            <div class="text-center py-5 text-muted"><i class="ri-inbox-line fs-1 d-block mb-2"></i> لا توجد حركات محافظ خلال هذه الفترة.</div>
        @else
            <div class="row mb-4">
                @foreach($report['liability_by_currency'] as $row)
                    <div class="col-md-4 mb-3">
                        <div class="card h-100"><div class="card-body">
                            <p class="text-uppercase fw-medium text-muted mb-1 fs-13">{{ $row['currency_code'] }}</p>
                            <h4 class="fs-20 mb-1">{{ number_format((float) $row['liability'], 2) }}</h4>
                            <p class="text-muted mb-0 fs-12">إيداع {{ number_format((float) $row['credits'], 2) }} — سحب {{ number_format((float) $row['debits'], 2) }}</p>
                        </div></div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>العميل</th><th>العملة</th><th class="text-end">الرصيد</th></tr></thead>
                <tbody>
                    @forelse($report['customers'] as $account)
                        <tr>
                            <td>{{ $account->customer->name ?? '-' }}</td>
                            <td>{{ $account->currency->code ?? '-' }}</td>
                            <td class="text-end fw-semibold">{{ $account->dynamic_balance }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">لا توجد نتائج مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $report['customers']->links() }}</div>
    </div>
</div>
@endsection
