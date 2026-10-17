@extends('layouts.admin')
@section('title', 'دفتر الأستاذ العام | لمّة')
@section('page-title', 'دفتر الأستاذ العام (General Ledger)')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0">كشف حساب الأستاذ العام</h5>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.finance.reports.supplier-reconciliation') }}" class="btn btn-outline-info btn-sm">
                    <i class="ri-scales-line me-1"></i> مطابقة ذمم الموردين مع الأستاذ العام
                </a>
                <a href="{{ route('admin.finance.reports.trial-balance') }}" class="btn btn-outline-primary btn-sm">
                    <i class="ri-scales-3-line me-1"></i> الانتقال لميزان المراجعة
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.finance.reports.general-ledger') }}" class="row g-3 mb-4 filter-row">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">الحساب المالي</label>
                <select name="account_id" class="form-select" required onchange="this.form.submit()">
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ $selectedAccountId == $acc->id ? 'selected' : '' }}>
                            {{ $acc->code }} - {{ $acc->name_ar }} ({{ $acc->type->label() }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">الفترة المحاسبية</label>
                <select name="period_id" class="form-select">
                    <option value="">جميع الفترات</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" {{ ($filters['period_id'] ?? '') == $period->id ? 'selected' : '' }}>
                            {{ $period->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="filter_start_date" class="form-label small fw-semibold">من تاريخ</label>
                <input type="text" name="start_date" id="filter_start_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay($filters['start_date'] ?? '') }}" placeholder="يوم-شهر-سنة">
            </div>
            <div class="col-md-2">
                <label for="filter_end_date" class="form-label small fw-semibold">إلى تاريخ</label>
                <input type="text" name="end_date" id="filter_end_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay($filters['end_date'] ?? '') }}" placeholder="يوم-شهر-سنة">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100"><i class="ri-filter-2-line"></i></button>
            </div>
        </form>

        @if($report)
            @php($acc = $report['account'])
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-2">
                    <div class="card card-animate bg-light border-0 mb-0">
                        <div class="card-body py-3">
                            <span class="text-muted small">الرصيد الافتتاحي (قبل الفترة)</span>
                            <h4 class="fs-18 fw-bold mb-0 mt-1 font-monospace">
                                {{ number_format((float)$report['opening_balance'], 2) }}
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-2">
                    <div class="card card-animate bg-light border-0 mb-0">
                        <div class="card-body py-3">
                            <span class="text-muted small">إجمالي حركات المدين</span>
                            <h4 class="fs-18 fw-bold mb-0 mt-1 text-primary font-monospace">
                                {{ number_format((float)$report['total_debit'], 2) }}
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-2">
                    <div class="card card-animate bg-light border-0 mb-0">
                        <div class="card-body py-3">
                            <span class="text-muted small">إجمالي حركات الدائن</span>
                            <h4 class="fs-18 fw-bold mb-0 mt-1 text-success font-monospace">
                                {{ number_format((float)$report['total_credit'], 2) }}
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-2">
                    <div class="card card-animate bg-light border-0 mb-0">
                        <div class="card-body py-3">
                            <span class="text-muted small">الرصيد الختامي للحساب</span>
                            <h4 class="fs-18 fw-bold mb-0 mt-1 text-dark font-monospace">
                                {{ number_format((float)$report['closing_balance'], 2) }}
                                <small class="text-muted fs-12 fw-normal">({{ $acc->normal_balance->label() }})</small>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>التاريخ</th>
                            <th>رقم القيد</th>
                            <th>البيان والتفاصيل</th>
                            <th class="text-end">مدين (Debit)</th>
                            <th class="text-end">دائن (Credit)</th>
                            <th class="text-end">الرصيد التراكمي المتحرك</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Row for Opening Balance --}}
                        <tr class="table-light">
                            <td colspan="3" class="fw-semibold text-muted">
                                <i class="ri-history-line me-1"></i> رصيد افتتاحي منقول من فترات سابقة
                            </td>
                            <td class="text-end font-monospace text-muted">-</td>
                            <td class="text-end font-monospace text-muted">-</td>
                            <td class="text-end font-monospace fw-bold fs-14">
                                {{ number_format((float)$report['opening_balance'], 2) }}
                            </td>
                        </tr>

                        @forelse($report['rows'] as $row)
                            <tr>
                                <td>{{ \App\Support\DateFilterNormalizer::formatDisplay($row['entry_date']) }}</td>
                                <td>
                                    <a href="{{ route('admin.finance.journal-entries.show', $row['entry']) }}" class="font-monospace fw-bold text-primary">
                                        {{ $row['entry_number'] }}
                                    </a>
                                </td>
                                <td>
                                    <div>
                                        <span>{{ $row['description'] }}</span>
                                        @if($row['source_type'])
                                            <small class="badge bg-light text-muted ms-1 font-monospace">مصدر: {{ class_basename($row['source_type']) }} #{{ $row['source_id'] }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end font-monospace {{ bccomp((string)$row['debit'], '0', 4) > 0 ? 'text-primary fw-semibold' : 'text-muted' }}">
                                    {{ number_format((float)$row['debit'], 2) }}
                                </td>
                                <td class="text-end font-monospace {{ bccomp((string)$row['credit'], '0', 4) > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                                    {{ number_format((float)$row['credit'], 2) }}
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark fs-14">
                                    {{ number_format((float)$row['running_balance'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    لا توجد حركات مسجلة لهذا الحساب ضمن النطاق الزمني المحدد.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-bold fs-14">
                        <tr>
                            <td colspan="3" class="text-end">المجموع الإجمالي للحركات:</td>
                            <td class="text-end text-primary font-monospace">{{ number_format((float)$report['total_debit'], 2) }}</td>
                            <td class="text-end text-success font-monospace">{{ number_format((float)$report['total_credit'], 2) }}</td>
                            <td class="text-end text-dark font-monospace fs-15">{{ number_format((float)$report['closing_balance'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="ri-book-read-line fs-36 d-block mb-2 text-secondary"></i>
                يرجى اختيار حساب مالي لعرض دفتر الأستاذ الخاص به.
            </div>
        @endif
    </div>
</div>
@endsection
