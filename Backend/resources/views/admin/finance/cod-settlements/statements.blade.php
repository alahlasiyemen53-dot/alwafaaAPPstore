@extends('layouts.admin')
@section('title', 'كشف عهدة المندوب (COD Statement) | الوفاء استور')
@section('page-title', 'كشف عهدة المندوب النقدية')

@section('content')
<div class="card mb-4 no-print">
    <div class="card-header border-bottom-dashed">
        <h5 class="card-title mb-0">تحديد معايير كشف العهدة</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.finance.cod-settlements.statements') }}" class="row g-3 filter-row">
            <div class="col-md-3">
                <label class="form-label required">المندوب</label>
                <select name="driver_id" class="form-select" required>
                    <option value="">-- اختر المندوب --</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}" {{ request('driver_id') == $driver->id ? 'selected' : '' }}>
                            {{ $driver->name }} ({{ $driver->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">العملة</label>
                <select name="currency_id" class="form-select">
                    <option value="">كافة العملات</option>
                    @foreach($currencies as $curr)
                        <option value="{{ $curr->id }}" {{ request('currency_id') == $curr->id ? 'selected' : '' }}>
                            {{ $curr->code }} - {{ $curr->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filter_from_date" class="form-label">من تاريخ</label>
                <input type="text" name="from_date" id="filter_from_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('from_date')) }}" placeholder="يوم-شهر-سنة">
            </div>
            <div class="col-md-2">
                <label for="filter_to_date" class="form-label">إلى تاريخ</label>
                <input type="text" name="to_date" id="filter_to_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('to_date')) }}" placeholder="يوم-شهر-سنة">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="ri-search-eye-line me-1"></i> عرض الكشف
                </button>
                @if($statement)
                    <button type="button" class="btn btn-light" onclick="window.print()" title="طباعة">
                        <i class="ri-printer-line"></i>
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>

@if($statement)
    <!-- Statement Print Header / Banner -->
    <div class="card">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4 flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <div class="avatar-md me-3">
                        <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-20">
                            <i class="ri-user-line"></i>
                        </span>
                    </div>
                    <div>
                        <h4 class="mb-1">{{ $statement['driver']->name }}</h4>
                        <p class="text-muted mb-0">كود المندوب: <strong>{{ $statement['driver']->code }}</strong> | الهاتف: {{ $statement['driver']->phone }}</p>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-body border fs-12 mb-1">كشف عهدة تحصيلات COD</span>
                    <p class="text-muted mb-0 fs-12">تاريخ التقرير: {{ now()->format('Y-m-d H:i') }}</p>
                </div>
            </div>

            <!-- Financial Totals Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="p-3 border rounded text-center bg-light">
                        <span class="text-muted fs-13 d-block mb-1">إجمالي التحصيلات (مدين)</span>
                        <h4 class="text-primary fw-bold mb-0">{{ number_format($statement['total_debit'], 2) }}</h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 border rounded text-center bg-light">
                        <span class="text-muted fs-13 d-block mb-1">إجمالي التوريدات بالخزينة (دائن)</span>
                        <h4 class="text-success fw-bold mb-0">{{ number_format($statement['total_credit'], 2) }}</h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 border rounded text-center {{ $statement['ending_balance'] > 0 ? 'bg-danger-subtle' : 'bg-light' }}">
                        <span class="text-muted fs-13 d-block mb-1">الرصيد المستحق الحالي المتبقي</span>
                        <h4 class="{{ $statement['ending_balance'] > 0 ? 'text-danger' : 'text-success' }} fw-bold mb-0">
                            {{ number_format($statement['ending_balance'], 2) }}
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Statement Lines Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 150px;">التاريخ</th>
                            <th style="width: 140px;">نوع الحركة</th>
                            <th>المرجع والبيان</th>
                            <th class="text-end" style="width: 130px;">تحصيل عهدة (مدين +)</th>
                            <th class="text-end" style="width: 130px;">توريد خزينة (دائن -)</th>
                            <th class="text-end" style="width: 140px;">الرصيد التراكمي</th>
                            <th style="width: 100px;">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($statement['lines'] as $line)
                            <tr>
                                <td><small class="text-muted">{{ $line['date'] }}</small></td>
                                <td>
                                    @if($line['type'] === 'delivery')
                                        <span class="badge bg-primary-subtle text-primary">تحصيل COD</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">توريد تسوية</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $line['reference'] }}</span>
                                    @if($line['notes'])
                                        <small class="text-muted d-block">{{ $line['notes'] }}</small>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">
                                    {{ $line['debit'] > 0 ? number_format($line['debit'], 2) : '—' }}
                                </td>
                                <td class="text-end fw-semibold text-success">
                                    {{ $line['credit'] > 0 ? number_format($line['credit'], 2) : '—' }}
                                </td>
                                <td class="text-end fw-bold {{ $line['balance'] > 0 ? 'text-danger' : 'text-dark' }}">
                                    {{ number_format($line['balance'], 2) }}
                                </td>
                                <td>
                                    <span class="{{ $line['status_badge'] }}">{{ $line['status'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    لا توجد حركات عهدة أو تسويات مسجلة لهذا المندوب خلال الفترة المحددة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">الإجمالي:</td>
                            <td class="text-end text-primary fs-14">{{ number_format($statement['total_debit'], 2) }}</td>
                            <td class="text-end text-success fs-14">{{ number_format($statement['total_credit'], 2) }}</td>
                            <td class="text-end fs-15 {{ $statement['ending_balance'] > 0 ? 'text-danger' : 'text-dark' }}">
                                {{ number_format($statement['ending_balance'], 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@elseif(request('driver_id'))
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="ri-file-warning-line text-warning display-4 mb-3"></i>
            <h5>لا توجد بيانات متاحة</h5>
            <p class="text-muted">لم يتم العثور على حركات عهدة للمندوب المحدد وفق المعايير المدخلة.</p>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="avatar-lg mx-auto mb-3">
                <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                    <i class="ri-file-user-line"></i>
                </div>
            </div>
            <h5>اختر المندوب لعرض كشف العهدة</h5>
            <p class="text-muted mb-0">يمكنك استعراض كافة حركات التحصيل النقدي للشحنات المسلمة ومقابلتها مع توريدات الخزينة والرصيد المتبقي.</p>
        </div>
    </div>
@endif
@endsection
