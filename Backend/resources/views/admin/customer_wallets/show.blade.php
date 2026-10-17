@extends('layouts.admin')
@section('title', 'كشف حساب محفظة العميل | الوفاء استور')
@section('page-title', 'كشف حساب محفظة العميل')

@section('content')
<div class="row">
    <!-- Account Information Card -->
    <div class="col-12 mb-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-md bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center fs-24">
                            <i class="ri-wallet-3-line"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1">{{ $account->customer?->name }}</h4>
                            <div class="text-muted fs-13">
                                <span class="me-3"><i class="ri-phone-line me-1"></i>{{ $account->customer?->phone }}</span>
                                <span class="badge bg-light text-dark border me-3">
                                    العملة: {{ $account->currency?->name_ar }} ({{ $account->currency?->code }})
                                </span>
                                <span class="{{ $account->status->badgeClass() }}">{{ $account->status->label() }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-light p-3 rounded text-center border">
                            <div class="text-muted fs-12 mb-1">الرصيد الفعلي المتاح</div>
                            <div class="fs-20 fw-bold text-success">{{ number_format((float) $currentBalance, 2) }} {{ $account->currency?->symbol }}</div>
                        </div>
                        @if(auth()->user()->hasPermission('admin.customer_wallets.adjust'))
                        <button type="button" class="btn btn-primary h-100" data-bs-toggle="modal" data-bs-target="#adjustWalletModal">
                            <i class="ri-add-circle-line me-1"></i> تسوية يدوية
                        </button>
                        @endif
                        <a href="{{ route('admin.customer-wallets.index') }}" class="btn btn-light h-100">
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
                <form method="GET" action="{{ route('admin.customer-wallets.show', $account) }}" class="row g-3 align-items-end filter-row">
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
                        <a href="{{ route('admin.customer-wallets.show', $account) }}" class="btn btn-light">إعادة ضبط</a>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light border-top d-flex justify-content-between align-items-center py-2 fs-13">
                <span>رصيد أول المدة: <strong>{{ number_format((float) $statement['opening_balance'], 2) }} {{ $account->currency?->symbol }}</strong></span>
                <span>رصيد آخر المدة: <strong class="text-success">{{ number_format((float) $statement['closing_balance'], 2) }} {{ $account->currency?->symbol }}</strong></span>
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold">سجل حركات المحفظة والأستاذ العام (Ledger)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th># الحركة</th>
                                <th>التاريخ والوقت</th>
                                <th>نوع الحركة</th>
                                <th>البيان والمصدر</th>
                                <th class="text-center text-success">إيداع / دائن (+)</th>
                                <th class="text-center text-danger">خصم / مدين (-)</th>
                                <th class="text-end">الرصيد التراكمي</th>
                                <th>قيد اليومية</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($statement['entries'] as $entry)
                            @php
                                $isReversed = !empty($entry['reversal_of_id']) || \App\Models\CustomerWalletEntry::where('reversal_of_id', $entry['id'])->exists();
                                $canReverse = !$isReversed && $entry['type'] !== \App\Enums\CustomerWalletEntryType::Reversal && auth()->user()->hasPermission('admin.customer_wallets.reverse');
                            @endphp
                            <tr class="{{ $isReversed ? 'table-light text-muted' : '' }}">
                                <td class="fw-semibold">{{ $entry['entry_number'] }}</td>
                                <td class="text-muted fs-12">{{ $entry['posted_at']?->format('d-m-Y H:i') }}</td>
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
                                    {{ bccomp($entry['credit'], '0.0000', 4) > 0 ? '+' . number_format((float) $entry['credit'], 2) : '-' }}
                                </td>
                                <td class="text-center text-danger fw-bold">
                                    {{ bccomp($entry['debit'], '0.0000', 4) > 0 ? '-' . number_format((float) $entry['debit'], 2) : '-' }}
                                </td>
                                <td class="text-end fw-bold text-dark fs-14">
                                    {{ number_format((float) $entry['running_balance'], 2) }} {{ $account->currency?->symbol }}
                                </td>
                                <td>
                                    @if($entry['journal_entry_id'])
                                        <a href="{{ route('admin.finance.journal-entries.show', $entry['journal_entry_id']) }}" class="badge bg-secondary-subtle text-secondary" target="_blank">
                                            <i class="ri-book-open-line me-1"></i> قيد #{{ $entry['journal_entry_id'] }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($canReverse)
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseModal{{ $entry['id'] }}">
                                        <i class="ri-arrow-go-back-line me-1"></i> عكس الحركة
                                    </button>

                                    <!-- Modal Reverse -->
                                    <div class="modal fade" id="reverseModal{{ $entry['id'] }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow text-start">
                                                <form action="{{ route('admin.customer-wallets.reverse', $entry['id']) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header bg-danger-subtle">
                                                        <h5 class="modal-title fw-bold text-danger"><i class="ri-error-warning-line me-1"></i> تأكيد عكس حركة المحفظة</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <p class="mb-2">أنت على وشك عكس الحركة <strong>{{ $entry['entry_number'] }}</strong> بمبلغ <strong>{{ number_format((float) $entry['amount'], 2) }} {{ $account->currency?->symbol }}</strong>.</p>
                                                        <p class="text-muted fs-12 mb-3">سيتم إصدار قيد يومية عكسي مطابق في الأستاذ العام وتعديل الرصيد التراكمي للمحفظة فوراً.</p>
                                                        <div class="mb-3">
                                                            <label class="form-label required">سبب العكس الإداري / المالي</label>
                                                            <textarea name="reason" rows="3" class="form-control" placeholder="يرجى توضيح سبب عكس الحركة لتوثيقه في القيد العكسي..." required minlength="3"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                        <button type="submit" class="btn btn-danger">تأكيد عكس الحركة</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    @elseif($isReversed)
                                        <span class="badge bg-warning-subtle text-warning">معكوسة</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="ri-inbox-line fs-36 text-muted mb-2 d-block"></i>
                                    لا توجد حركات مالية مسجلة في محفظة هذا العميل ضمن الفترة المحددة.
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

<!-- Modal: Adjust Customer Wallet -->
@if(auth()->user()->hasPermission('admin.customer_wallets.adjust'))
<div class="modal fade" id="adjustWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.customer-wallets.adjust') }}" method="POST">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $account->customer_id }}">
                <input type="hidden" name="currency_id" value="{{ $account->currency_id }}">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="ri-wallet-3-line me-1 text-primary"></i> تسوية رصيد محفظة العميل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 fs-12 mb-3">
                        العميل: <strong>{{ $account->customer?->name }}</strong> | العملة: <strong>{{ $account->currency?->code }}</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">نوع العملية</label>
                        <select name="type" class="form-select" required>
                            <option value="credit">إيداع دائن (Credit) - زيادة رصيد العميل</option>
                            <option value="debit">خصم مدين (Debit) - تخفيض رصيد العميل</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">المبلغ</label>
                        <input type="number" step="0.0001" min="0.0001" name="amount" class="form-control" placeholder="مثال: 5000.0000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">سبب التسوية المالية</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="اكتب المبرر المالي للتسوية لتوثيقه في قيد اليومية العام..." required minlength="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تنفيذ وترحيل القيد</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
