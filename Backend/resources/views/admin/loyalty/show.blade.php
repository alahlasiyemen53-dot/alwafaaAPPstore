@extends('layouts.admin')
@section('title', 'كشف حساب نقاط الولاء | لمّة')
@section('page-title', 'كشف حساب نقاط الولاء')

@section('content')
<div class="row">
    <!-- Account Information Card -->
    <div class="col-12 mb-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-md bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fs-24">
                            <i class="ri-copper-coin-line"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1">{{ $account->customer?->name }}</h4>
                            <div class="text-muted fs-13">
                                <span class="me-3"><i class="ri-phone-line me-1"></i>{{ $account->customer?->phone }}</span>
                                <span class="me-3"><i class="ri-calendar-line me-1"></i>تاريخ فتح الحساب: {{ $account->created_at->format('Y-m-d') }}</span>
                                <span class="{{ $account->status->badgeClass() }}">{{ $account->status->label() }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-light p-3 rounded text-center border">
                            <div class="text-muted fs-12 mb-1">الرصيد الفعلي الحالي</div>
                            <div class="fs-20 fw-bold text-primary">{{ number_format($currentBalance) }} نقطة</div>
                        </div>
                        @if(auth()->user()->hasPermission('admin.loyalty.adjust'))
                        <button type="button" class="btn btn-primary h-100" data-bs-toggle="modal" data-bs-target="#adjustLoyaltyModal">
                            <i class="ri-add-circle-line me-1"></i> تسوية يدوية
                        </button>
                        @endif
                        <a href="{{ route('admin.loyalty.index') }}" class="btn btn-light h-100">
                            <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Filter & Summary -->
    <div class="col-12 mb-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.loyalty.show', $account) }}" class="row g-3 align-items-end filter-row">
                    <div class="col-md-4">
                        <label for="filter_start_date" class="form-label fs-12 text-muted">من تاريخ</label>
                        <input type="text" name="start_date" id="filter_start_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay($startDate) }}" placeholder="يوم-شهر-سنة">
                    </div>
                    <div class="col-md-4">
                        <label for="filter_end_date" class="form-label fs-12 text-muted">إلى تاريخ</label>
                        <input type="text" name="end_date" id="filter_end_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay($endDate) }}" placeholder="يوم-شهر-سنة">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">تطبيق الفترة</button>
                        <a href="{{ route('admin.loyalty.show', $account) }}" class="btn btn-light">إعادة ضبط</a>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light border-top d-flex justify-content-between align-items-center py-2 fs-13">
                <span>رصيد أول المدة: <strong>{{ number_format($statement['opening_points']) }} نقطة</strong></span>
                <span>رصيد آخر المدة: <strong class="text-primary">{{ number_format($statement['closing_points']) }} نقطة</strong></span>
            </div>
        </div>
    </div>

    <!-- Statement Entries Table -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold">سجل حركات نقاط الولاء (Ledger)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th># الحركة</th>
                                <th>التاريخ والوقت</th>
                                <th>نوع الحركة</th>
                                <th>البيان والتفاصيل</th>
                                <th class="text-center text-success">مكتسب (+)</th>
                                <th class="text-center text-danger">مخصوم (-)</th>
                                <th class="text-end">الرصيد التراكمي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($statement['entries'] as $entry)
                            <tr>
                                <td class="fw-semibold">{{ $entry['entry_number'] }}</td>
                                <td class="text-muted fs-12">{{ $entry['occurred_at']?->format('d-m-Y H:i') }}</td>
                                <td>
                                    <span class="{{ $entry['type']->badgeClass() }}">
                                        {{ $entry['type']->label() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="text-dark">{{ $entry['description'] }}</div>
                                    <small class="text-muted">المصدر: {{ $entry['source_type'] }} #{{ $entry['source_id'] }} ({{ $entry['source_event'] }})</small>
                                </td>
                                <td class="text-center text-success fw-bold">
                                    {{ $entry['earned_points'] > 0 ? '+' . number_format($entry['earned_points']) : '-' }}
                                </td>
                                <td class="text-center text-danger fw-bold">
                                    {{ $entry['deducted_points'] > 0 ? '-' . number_format($entry['deducted_points']) : '-' }}
                                </td>
                                <td class="text-end fw-bold text-dark fs-14">
                                    {{ number_format($entry['running_points']) }} نقطة
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ri-inbox-line fs-36 text-muted mb-2 d-block"></i>
                                    لا توجد حركات نقاط ولاء مسجلة في هذا الحساب ضمن الفترة المحددة.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Adjust Loyalty Points -->
@if(auth()->user()->hasPermission('admin.loyalty.adjust'))
<div class="modal fade" id="adjustLoyaltyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.loyalty.adjust') }}" method="POST">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $account->customer_id }}">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="ri-copper-coin-line me-1 text-primary"></i> تسوية نقاط للعميل: {{ $account->customer?->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">نوع التسوية</label>
                        <select name="type" class="form-select" required>
                            <option value="credit">إضافة نقاط (Credit)</option>
                            <option value="debit">خصم نقاط (Debit)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">عدد النقاط</label>
                        <input type="number" name="points" min="1" class="form-control" placeholder="مثال: 50" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">سبب التسوية الإدارية</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="اكتب مبرر التسوية الإدارية بالتفصيل..." required minlength="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تنفيذ التسوية</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
