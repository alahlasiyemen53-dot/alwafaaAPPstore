@extends('layouts.admin')
@section('title', 'كشف حساب مورد: '.$supplier->name.' | لمّة')
@section('page-title', 'كشف حساب المورد')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="card-title mb-1"><i class="ri-file-list-line me-1 text-primary"></i> كشف الحساب: {{ $supplier->name }}</h4>
                    <span class="badge bg-light text-body border" dir="ltr">الكود: {{ $supplier->code }}</span>
                    @if($supplier->phone)
                        <span class="text-muted ms-2"><i class="ri-phone-line me-1"></i>{{ $supplier->phone }}</span>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="ri-printer-line me-1"></i> طباعة كشف الحساب
                    </button>
                    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light">
                        <i class="ri-arrow-right-line me-1"></i> العودة للموردين
                    </a>
                </div>
            </div>

            <!-- Balances Summary Cards -->
            <div class="card-body border-bottom bg-light bg-opacity-40">
                <h6 class="fw-bold mb-3"><i class="ri-wallet-3-line text-primary me-1"></i> الأرصدة المستحقة الحالية للمورد (مفصولة بالعملات):</h6>
                <div class="row g-3">
                    @forelse($balances as $code => $item)
                        <div class="col-md-4 col-lg-3">
                            <div class="card border border-primary-subtle shadow-sm mb-0">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted fs-12">{{ $item['currency']->name_ar }}</span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $code }}</span>
                                    </div>
                                    <h4 class="fw-bold text-danger mb-0" dir="ltr">
                                        {{ $item['formatted'] }}
                                    </h4>
                                    <small class="text-muted fs-11">التزام مستحق للمورد</small>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="alert alert-secondary py-2 mb-0 fs-13">
                                <i class="ri-information-line me-1"></i> لا توجد أي التزامات أو حركات مالية مسجلة لهذا المورد حالياً (الرصيد: 0.00).
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Filters -->
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.suppliers.statement', $supplier) }}" class="row g-3 align-items-end filter-row" id="supplierStatementFilterForm">
                    <div class="col-md-3">
                        <div>
                            <label for="filter_currency_id" class="form-label mb-1">العملة</label>
                            <select class="form-control" id="filter_currency_id" data-choices data-choices-search-false name="currency_id">
                                <option value="">جميع العملات</option>
                                @foreach($currencies as $curr)
                                    <option value="{{ $curr->id }}" @selected((string) request('currency_id') === (string) $curr->id)>
                                        {{ $curr->name_ar }} ({{ $curr->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div>
                            <label for="filter_date_from" class="form-label mb-1">من تاريخ</label>
                            <input type="text" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" placeholder="يوم-شهر-سنة" id="filter_date_from" name="date_from" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('date_from')) }}">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div>
                            <label for="filter_date_to" class="form-label mb-1">إلى تاريخ</label>
                            <input type="text" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" placeholder="يوم-شهر-سنة" id="filter_date_to" name="date_to" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('date_to')) }}">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="ri-filter-line me-1"></i> تصفية</button>
                        @if(request()->hasAny(['currency_id', 'date_from', 'date_to']))
                            <a href="{{ route('admin.suppliers.statement', $supplier) }}" class="btn btn-soft-danger">إعادة ضبط</a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Statement Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>التاريخ والوقت</th>
                                <th>نوع القيد</th>
                                <th>المرجع</th>
                                <th>البيان / الوصف</th>
                                <th>العملة</th>
                                <th>المبلغ الدائن (لنا)</th>
                                <th>المسؤول</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($entries as $entry)
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $entry->posted_at?->format('d-m-Y') }}</span>
                                        <div class="text-muted fs-11">{{ $entry->posted_at?->format('H:i') }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            فاتورة شراء
                                        </span>
                                    </td>
                                    <td>
                                        @if($entry->reference_type === \App\Models\PurchaseInvoice::class)
                                            <a href="{{ route('admin.purchases.show', $entry->reference_id) }}" class="fw-bold text-primary">
                                                {{ $entry->description ?: 'فاتورة #'.$entry->reference_id }}
                                            </a>
                                        @else
                                            <span class="text-dark">#{{ $entry->reference_id }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $entry->description ?: '—' }}</td>
                                    <td>
                                        <span class="badge bg-light text-body border">{{ $entry->currency?->code }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-danger fs-14" dir="ltr">
                                            + {{ number_format((float) $entry->amount, $entry->currency?->decimal_places ?? 2) }} {{ $entry->currency?->symbol }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $entry->createdBy?->name ?: 'النظام' }}</small>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        لا توجد حركات في كشف الحساب تطابق الفترة المحددة.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($entries->hasPages())
                    <div class="card-footer py-2">
                        {{ $entries->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
