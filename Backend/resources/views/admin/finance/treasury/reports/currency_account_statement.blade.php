@extends('layouts.admin')

@section('title', 'كشف حساب العملة | ' . $currencyAccount->displayName)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">كشف حساب العملة: {{ $currencyAccount->displayName }}</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.finance.treasury.reports.ledger') }}">تقارير الخزينة</a></li>
                        <li class="breadcrumb-item active">كشف حساب عملة</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-white text-primary rounded-circle fs-3">
                                <i class="ri-wallet-3-line"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-uppercase fw-medium text-white-50 mb-1">كيان الخزينة / الحساب</p>
                            <h4 class="mb-0 text-white">{{ $currencyAccount->displayName }}</h4>
                            <small class="text-white-50">حساب الأستاذ العام: <code>{{ $currencyAccount->glAccount?->code }}</code> - {{ $currencyAccount->glAccount?->name_ar }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3">
                                <i class="ri-safe-2-line"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-uppercase fw-medium text-muted mb-1">الرصيد الدفتري المشتق (بالعملة المحددة فقط)</p>
                            <h4 class="mb-0 {{ bccomp($currentBalance, '0', 4) < 0 ? 'text-danger' : 'text-success' }} fs-22">
                                {{ number_format((float)$currentBalance, 2) }} {{ $currencyAccount->currency?->code }}
                            </h4>
                            <small class="text-muted fs-11">مشتق حصرياً من قيود الأستاذ العام المرحلة لحساب هذه العملة.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form action="{{ route('admin.finance.treasury.currency-accounts.statement', $currencyAccount) }}" method="GET" class="row g-3 filter-row">
                <div class="col-md-4">
                    <label class="form-label">من تاريخ</label>
                    <input type="text" name="date_from" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('date_from', date('01-m-Y'))) }}" placeholder="يوم-شهر-سنة">
                </div>
                <div class="col-md-4">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="text" name="date_to" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('date_to', date('d-m-Y'))) }}" placeholder="يوم-شهر-سنة">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="ri-search-line me-1"></i> تصفية الحركات</button>
                    <a href="{{ route('admin.finance.treasury.currency-accounts.statement', $currencyAccount) }}" class="btn btn-light"><i class="ri-refresh-line"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">
                <i class="ri-history-line me-1"></i> حركات كشف الحساب ({{ $currencyAccount->currency?->code }})
            </h5>
            <span class="badge bg-light text-dark fs-12">
                عدد الحركات: {{ $movements->count() }}
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">التاريخ</th>
                            <th scope="col">رقم القيد</th>
                            <th scope="col">رقم الحركة</th>
                            <th scope="col">البيان / الوصف</th>
                            <th scope="col" class="text-end text-success">وارد (مدين)</th>
                            <th scope="col" class="text-end text-danger">صادر (دائن)</th>
                            <th scope="col" class="text-end">الرصيد الجاري ({{ $currencyAccount->currency?->code }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row->entry_date)->format('Y-m-d') }}</td>
                                <td><code>{{ $row->entry_number }}</code></td>
                                <td>
                                    @if($row->transaction_number)
                                        <span class="badge bg-light text-dark">{{ $row->transaction_number }}</span>
                                    @else
                                        <span class="text-muted fs-11">قيد مباشر</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 320px;" title="{{ $row->line_description ?? $row->journal_description }}">
                                        {{ $row->line_description ?? $row->journal_description }}
                                    </div>
                                </td>
                                <td class="text-end fw-semibold text-success">
                                    {{ (float)$row->incoming > 0 ? number_format((float)$row->incoming, 2) : '-' }}
                                </td>
                                <td class="text-end fw-semibold text-danger">
                                    {{ (float)$row->outgoing > 0 ? number_format((float)$row->outgoing, 2) : '-' }}
                                </td>
                                <td class="text-end fw-bold {{ bccomp($row->running_balance, '0', 4) < 0 ? 'text-danger' : 'text-primary' }}">
                                    {{ number_format((float)$row->running_balance, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ri-inbox-line fs-32 d-block mb-2 text-secondary"></i>
                                    لا توجد حركات مسجلة لهذا الحساب بالعملة المحددة في هذه الفترة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($movements->isNotEmpty())
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end">الرصيد النهائي:</td>
                                <td colspan="3" class="text-end fs-15 text-primary">
                                    {{ number_format((float)$currentBalance, 2) }} {{ $currencyAccount->currency?->code }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
