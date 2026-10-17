@extends('layouts.admin')
@section('title', 'تقرير نقاط الولاء | الوفاء استور')
@section('page-title', 'تقرير نقاط الولاء')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0"><h5 class="card-title mb-0">نشاط نقاط الولاء</h5></div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        <div class="row mb-4">
            @foreach(['earn' => 'اكتساب', 'redeem' => 'استبدال', 'clawback' => 'استرداد لمرتجع', 'adjustment_credit' => 'تسوية إضافة', 'adjustment_debit' => 'تسوية خصم', 'reversal' => 'عكس'] as $key => $label)
                <div class="col-md-4 col-xl-2 mb-3">
                    <div class="card h-100"><div class="card-body">
                        <p class="text-muted mb-1 fs-13">{{ $label }}</p>
                        <h5 class="fs-18 mb-0">{{ $report['activity'][$key] ?? 0 }}</h5>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>العميل</th><th class="text-end">الرصيد المتاح</th></tr></thead>
                <tbody>
                    @forelse($report['customers'] as $account)
                        <tr>
                            <td>{{ $account->customer->name ?? '-' }}</td>
                            <td class="text-end fw-semibold">{{ $account->dynamic_balance }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">لا توجد نتائج مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $report['customers']->links() }}</div>
    </div>
</div>
@endsection
