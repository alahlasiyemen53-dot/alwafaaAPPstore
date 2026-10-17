@extends('layouts.admin')
@section('title', 'القيود اليومية | الوفاء استور')
@section('page-title', 'دفتر القيود اليومية')

@section('content')
<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">إجمالي القيود</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2">{{ number_format($metrics['total']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                            <i class="ri-file-list-3-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">قيود مرحلة (معتمدة)</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-success">{{ number_format($metrics['posted']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                            <i class="ri-checkbox-circle-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">مسودات قيود</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-warning">{{ number_format($metrics['draft']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                            <i class="ri-draft-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">قيود معكوسة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-danger">{{ number_format($metrics['reversed']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle text-danger rounded fs-3">
                            <i class="ri-arrow-go-back-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pb-0">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0">سجل القيود اليومية</h5>
            @if(auth()->user()->hasPermission('admin.journal_entries.create'))
                <a href="{{ route('admin.finance.journal-entries.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-middle me-1"></i> إنشاء قيد يومي جديد
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.finance.journal-entries.index') }}" class="row g-2 align-items-center filter-row mb-4">
            <div class="col-md-3">
                <div class="search-box">
                    <input type="text" name="search" class="form-control" placeholder="بحث برقم القيد أو البيان..." value="{{ $filters['search'] ?? '' }}">
                    <i class="ri-search-line search-icon"></i>
                </div>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">جميع الحالات</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" {{ ($filters['status'] ?? '') === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="period_id" class="form-select">
                    <option value="">جميع الفترات</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" {{ ($filters['period_id'] ?? '') == $period->id ? 'selected' : '' }}>{{ $period->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="from_date" id="filter_from_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay($filters['from_date'] ?? '') }}" placeholder="يوم-شهر-سنة" title="من تاريخ">
            </div>
            <div class="col-md-2">
                <input type="text" name="to_date" id="filter_to_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay($filters['to_date'] ?? '') }}" placeholder="يوم-شهر-سنة" title="إلى تاريخ">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-secondary w-100" title="فلترة"><i class="ri-filter-2-line"></i></button>
                @if(!empty($filters['search']) || !empty($filters['status']) || !empty($filters['period_id']) || !empty($filters['from_date']) || !empty($filters['to_date']))
                    <a href="{{ route('admin.finance.journal-entries.index') }}" class="btn btn-light" title="إعادة ضبط"><i class="ri-refresh-line"></i></a>
                @endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-nowrap mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">رقم القيد</th>
                        <th scope="col">التاريخ</th>
                        <th scope="col">البيان</th>
                        <th scope="col">الفترة</th>
                        <th scope="col">العملة</th>
                        <th scope="col" class="text-end">إجمالي المدين</th>
                        <th scope="col" class="text-end">إجمالي الدائن</th>
                        <th scope="col">الحالة</th>
                        <th scope="col" class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td>
                                <a href="{{ route('admin.finance.journal-entries.show', $entry) }}" class="fw-bold font-monospace text-primary">
                                    {{ $entry->entry_number }}
                                </a>
                                @if($entry->source_type)
                                    <small class="badge bg-light text-muted d-block mt-1 font-monospace">آلي: {{ class_basename($entry->source_type) }} #{{ $entry->source_id }}</small>
                                @endif
                            </td>
                            <td>{{ $entry->entry_date->format('d-m-Y') }}</td>
                            <td>
                                <span class="d-inline-block text-truncate" style="max-width: 250px;" title="{{ $entry->description }}">
                                    {{ $entry->description }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-body border">{{ $entry->accountingPeriod?->name }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">{{ $entry->currency?->code }}</span>
                            </td>
                            <td class="text-end font-monospace fw-semibold text-primary">
                                {{ number_format((float) $entry->total_debit, 2) }}
                            </td>
                            <td class="text-end font-monospace fw-semibold text-success">
                                {{ number_format((float) $entry->total_credit, 2) }}
                            </td>
                            <td>
                                <span class="badge {{ $entry->status->badgeClass() }}">{{ $entry->status->label() }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('admin.finance.journal-entries.show', $entry) }}" class="btn btn-sm btn-subtle-info" title="عرض التفاصيل">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                    @if($entry->isDraft() && auth()->user()->hasPermission('admin.journal_entries.update'))
                                        <a href="{{ route('admin.finance.journal-entries.edit', $entry) }}" class="btn btn-sm btn-subtle-primary" title="تعديل المسودة">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="ri-file-list-3-line fs-36 d-block mb-2 text-secondary"></i>
                                لا توجد قيود يومية مسجلة مطابقة للبحث
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $entries->links() }}
        </div>
    </div>
</div>
@endsection
