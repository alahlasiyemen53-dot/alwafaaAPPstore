@extends('layouts.admin')
@section('title', 'ميزان المراجعة | الوفاء استور')
@section('page-title', 'ميزان المراجعة المالي')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0">ميزان المراجعة (Trial Balance)</h5>
            @if($report['is_balanced'])
                <span class="badge bg-success-subtle text-success fs-13 py-2 px-3">
                    <i class="ri-checkbox-circle-fill me-1"></i> ميزان المراجعة متوازن ومطابق تماماً
                </span>
            @else
                <span class="badge bg-danger-subtle text-danger fs-13 py-2 px-3">
                    <i class="ri-error-warning-fill me-1"></i> تنبيه: اختلال في توازن الميزان بفارق: {{ $report['difference'] }}
                </span>
            @endif
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.finance.reports.trial-balance') }}" class="row g-2 align-items-center filter-row mb-4">
            <div class="col-md-3">
                <select name="period_id" class="form-select">
                    <option value="">جميع الفترات المحاسبية</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" {{ ($filters['period_id'] ?? '') == $period->id ? 'selected' : '' }}>
                            {{ $period->name }} ({{ $period->starts_at->format('d-m-Y') }} إلى {{ $period->ends_at->format('d-m-Y') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="start_date" id="filter_start_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay($filters['start_date'] ?? '') }}" placeholder="يوم-شهر-سنة" title="من تاريخ">
            </div>
            <div class="col-md-2">
                <input type="text" name="end_date" id="filter_end_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay($filters['end_date'] ?? '') }}" placeholder="يوم-شهر-سنة" title="إلى تاريخ">
            </div>
            <div class="col-md-3">
                <select name="account_type" class="form-select">
                    <option value="">جميع أنواع الحسابات</option>
                    @foreach($accountTypes as $type)
                        <option value="{{ $type->value }}" {{ ($filters['account_type'] ?? '') === $type->value ? 'selected' : '' }}>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="ri-filter-2-line me-1"></i> عرض</button>
                @if(!empty($filters['period_id']) || !empty($filters['start_date']) || !empty($filters['end_date']) || !empty($filters['account_type']))
                    <a href="{{ route('admin.finance.reports.trial-balance') }}" class="btn btn-light"><i class="ri-refresh-line"></i></a>
                @endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th rowspan="2" class="align-middle" style="width: 10%;">رمز الحساب</th>
                        <th rowspan="2" class="align-middle" style="width: 24%;">اسم الحساب</th>
                        <th rowspan="2" class="align-middle" style="width: 10%;">النوع</th>
                        <th colspan="2" style="width: 18%;">الرصيد الافتتاحي</th>
                        <th colspan="2" style="width: 18%;">حركة الفترة</th>
                        <th colspan="2" style="width: 20%;">الرصيد الختامي</th>
                    </tr>
                    <tr>
                        <th class="text-end" style="width: 9%;">مدين</th>
                        <th class="text-end" style="width: 9%;">دائن</th>
                        <th class="text-end" style="width: 9%;">مدين</th>
                        <th class="text-end" style="width: 9%;">دائن</th>
                        <th class="text-end" style="width: 10%;">مدين</th>
                        <th class="text-end" style="width: 10%;">دائن</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['accounts'] as $row)
                        @php($acc = $row['account'])
                        <tr class="{{ !$acc->is_postable ? 'table-light fw-semibold' : '' }}">
                            <td>
                                <a href="{{ route('admin.finance.reports.general-ledger', ['account_id' => $acc->id]) }}" class="font-monospace fw-bold text-primary">
                                    {{ $acc->code }}
                                </a>
                            </td>
                            <td>
                                <span class="{{ !$acc->is_postable ? 'fw-bold' : '' }}">{{ $acc->name_ar }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $acc->type->badgeClass() }}">{{ $acc->type->label() }}</span>
                            </td>
                            {{-- Opening --}}
                            <td class="text-end font-monospace {{ bccomp((string)$row['opening_debit'], '0', 4) > 0 ? 'text-primary' : 'text-muted' }}">
                                {{ number_format((float)$row['opening_debit'], 2) }}
                            </td>
                            <td class="text-end font-monospace {{ bccomp((string)$row['opening_credit'], '0', 4) > 0 ? 'text-success' : 'text-muted' }}">
                                {{ number_format((float)$row['opening_credit'], 2) }}
                            </td>
                            {{-- Period --}}
                            <td class="text-end font-monospace {{ bccomp((string)$row['period_debit'], '0', 4) > 0 ? 'text-primary' : 'text-muted' }}">
                                {{ number_format((float)$row['period_debit'], 2) }}
                            </td>
                            <td class="text-end font-monospace {{ bccomp((string)$row['period_credit'], '0', 4) > 0 ? 'text-success' : 'text-muted' }}">
                                {{ number_format((float)$row['period_credit'], 2) }}
                            </td>
                            {{-- Closing --}}
                            <td class="text-end font-monospace fw-bold {{ bccomp((string)$row['closing_debit'], '0', 4) > 0 ? 'text-primary' : 'text-muted' }}">
                                {{ number_format((float)$row['closing_debit'], 2) }}
                            </td>
                            <td class="text-end font-monospace fw-bold {{ bccomp((string)$row['closing_credit'], '0', 4) > 0 ? 'text-success' : 'text-muted' }}">
                                {{ number_format((float)$row['closing_credit'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">لا توجد حركات محاسبية مرحلة ضمن النطاق المحدد.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light fw-bold fs-14">
                    <tr>
                        <td colspan="3" class="text-end">المجاميع الإجمالية:</td>
                        <td class="text-end text-primary font-monospace">{{ number_format((float)$report['total_opening_debit'], 2) }}</td>
                        <td class="text-end text-success font-monospace">{{ number_format((float)$report['total_opening_credit'], 2) }}</td>
                        <td class="text-end text-primary font-monospace">{{ number_format((float)$report['total_period_debit'], 2) }}</td>
                        <td class="text-end text-success font-monospace">{{ number_format((float)$report['total_period_credit'], 2) }}</td>
                        <td class="text-end text-primary font-monospace fs-15">{{ number_format((float)$report['total_closing_debit'], 2) }}</td>
                        <td class="text-end text-success font-monospace fs-15">{{ number_format((float)$report['total_closing_credit'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
