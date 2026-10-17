@extends('layouts.admin')
@section('title', 'تفاصيل القيد اليومي ' . $entry->entry_number . ' | الوفاء استور')
@section('page-title', 'تفاصيل القيد المحاسبي')

@section('content')
<div class="row">
    <div class="col-lg-12">
        @if($entry->isReversed())
            <div class="alert alert-danger d-flex align-items-center mb-3">
                <i class="ri-error-warning-fill fs-20 me-2"></i>
                <div class="flex-grow-1">
                    <strong>تنبيه: هذا القيد معكوس مالياً.</strong>
                    <span>تم عكسه بواسطة قيد التسوية العكسي: </span>
                    @if($entry->reversalEntry)
                        <a href="{{ route('admin.finance.journal-entries.show', $entry->reversalEntry) }}" class="fw-bold text-decoration-underline text-danger font-monospace">
                            {{ $entry->reversalEntry->entry_number }}
                        </a>
                    @endif
                    <div class="mt-1 small">سبب العكس: {{ $entry->reversal_reason }} (بتاريخ: {{ $entry->reversed_at?->format('Y-m-d H:i') }})</div>
                </div>
            </div>
        @elseif($entry->reversal_of_id)
            <div class="alert alert-info d-flex align-items-center mb-3">
                <i class="ri-information-fill fs-20 me-2"></i>
                <div>
                    <strong>قيد عكسي:</strong> هذا القيد تم إنشاؤه لعكس القيد الأصلي رقم:
                    @if($entry->reversalOf)
                        <a href="{{ route('admin.finance.journal-entries.show', $entry->reversalOf) }}" class="fw-bold text-decoration-underline font-monospace">
                            {{ $entry->reversalOf->entry_number }}
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h4 class="card-title mb-1 font-monospace">{{ $entry->entry_number }}</h4>
                        <span class="badge {{ $entry->status->badgeClass() }} fs-12">{{ $entry->status->label() }}</span>
                        @if($entry->source_type)
                            <span class="badge bg-light text-body border ms-2">
                                مصدر آلي: {{ class_basename($entry->source_type) }} #{{ $entry->source_id }} ({{ $entry->source_event }})
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary ms-2">قيد محاسبي يدوي</span>
                        @endif
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.finance.journal-entries.index') }}" class="btn btn-light btn-sm">
                            <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                        </a>
                        @if($entry->isDraft())
                            @if(auth()->user()->hasPermission('admin.journal_entries.update'))
                                <a href="{{ route('admin.finance.journal-entries.edit', $entry) }}" class="btn btn-primary btn-sm">
                                    <i class="ri-pencil-line me-1"></i> تعديل المسودة
                                </a>
                            @endif
                            @if(auth()->user()->hasPermission('admin.journal_entries.post'))
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#postEntryModal">
                                    <i class="ri-check-double-line me-1"></i> ترحيل القيد
                                </button>
                            @endif
                        @elseif($entry->isPosted() && !$entry->reversed_at && !$entry->reversal_of_id)
                            @if(auth()->user()->hasPermission('admin.journal_entries.reverse'))
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reverseEntryModal">
                                    <i class="ri-arrow-go-back-line me-1"></i> عكس القيد
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body border-top">
                <div class="row g-3">
                    <div class="col-md-3">
                        <span class="text-muted d-block small">تاريخ القيد</span>
                        <span class="fw-semibold">{{ $entry->entry_date->format('d-m-Y') }}</span>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted d-block small">الفترة المحاسبية</span>
                        <span class="fw-semibold">{{ $entry->accountingPeriod?->name }}</span>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted d-block small">العملة وسعر الصرف</span>
                        <span class="fw-semibold">{{ $entry->currency?->code }} (سعر التحويل: {{ number_format((float) $entry->exchange_rate_snapshot, 4) }})</span>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted d-block small">المرحل بواسطة</span>
                        <span class="fw-semibold">{{ $entry->postedBy?->name ?? 'قيد غير مرحل بعد' }}</span>
                    </div>
                    <div class="col-12">
                        <span class="text-muted d-block small">البيان / الوصف</span>
                        <p class="mb-0 text-dark bg-light p-2 rounded border">{{ $entry->description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card">
            <div class="card-header border-0">
                <h5 class="card-title mb-0">أسطر القيد المزدوج</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 20%;">رمز الحساب</th>
                                <th style="width: 25%;">اسم الحساب</th>
                                <th style="width: 20%;">البيان التفصيلي للسطر</th>
                                <th style="width: 15%;" class="text-end">مدين (Debit)</th>
                                <th style="width: 15%;" class="text-end">دائن (Credit)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entry->lines as $line)
                                <tr>
                                    <td>{{ $line->line_order }}</td>
                                    <td>
                                        <a href="{{ route('admin.finance.reports.general-ledger', ['account_id' => $line->account_id]) }}" class="fw-bold font-monospace text-primary">
                                            {{ $line->account?->code }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fw-medium">{{ $line->account?->name_ar }}</span>
                                        <span class="badge {{ $line->account?->type?->badgeClass() }} ms-1">{{ $line->account?->type?->label() }}</span>
                                        @if($line->party_type && $line->party_id)
                                            <small class="badge bg-light text-muted d-block mt-1">الجهة: {{ $line->party?->name ?? class_basename($line->party_type) }} #{{ $line->party_id }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $line->description ?: '-' }}
                                    </td>
                                    <td class="text-end font-monospace fw-semibold {{ bccomp((string) $line->debit, '0', 4) > 0 ? 'text-primary' : 'text-muted' }}">
                                        {{ number_format((float) $line->debit, 4) }}
                                    </td>
                                    <td class="text-end font-monospace fw-semibold {{ bccomp((string) $line->credit, '0', 4) > 0 ? 'text-success' : 'text-muted' }}">
                                        {{ number_format((float) $line->credit, 4) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold fs-15">
                            <tr>
                                <td colspan="4" class="text-end">الإجمالي بالعملة الأساسية:</td>
                                <td class="text-end text-primary font-monospace">{{ number_format((float) $entry->total_debit, 4) }}</td>
                                <td class="text-end text-success font-monospace">{{ number_format((float) $entry->total_credit, 4) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Post Confirmation --}}
@if($entry->isDraft() && auth()->user()->hasPermission('admin.journal_entries.post'))
<div class="modal fade" id="postEntryModal" tabindex="-1" aria-labelledby="postEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.finance.journal-entries.post', $entry) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-success" id="postEntryModalLabel"><i class="ri-check-double-line me-1"></i> ترحيل القيد المحاسبي</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>تنبيه هام وغير قابل للتراجع:</strong>
                        <p class="mb-0 mt-1">سيتم ترحيل هذا القيد نهائياً إلى دفتر الأستاذ العام، وبعد الترحيل <strong>لا يمكن تعديل أو حذف القيد مباشرة</strong>، بل يلزم إجراء قيد عكسي للتصحيح.</p>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li><strong>رقم القيد:</strong> <span class="font-monospace">{{ $entry->entry_number }}</span></li>
                        <li><strong>إجمالي المدين:</strong> <span class="font-monospace text-primary">{{ number_format((float) $entry->total_debit, 4) }}</span></li>
                        <li><strong>إجمالي الدائن:</strong> <span class="font-monospace text-success">{{ number_format((float) $entry->total_credit, 4) }}</span></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success px-4">تأكيد الترحيل الآن</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Modal Reverse Confirmation --}}
@if($entry->isPosted() && !$entry->reversed_at && !$entry->reversal_of_id && auth()->user()->hasPermission('admin.journal_entries.reverse'))
<div class="modal fade" id="reverseEntryModal" tabindex="-1" aria-labelledby="reverseEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.finance.journal-entries.reverse', $entry) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="reverseEntryModalLabel"><i class="ri-arrow-go-back-line me-1"></i> عكس القيد المحاسبي</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>إجراء مالي دقيق:</strong>
                        <p class="mb-0 mt-1">سيتم إنشاء قيد عكسي جديد متطابق تماماً بعكس طرفي المدين والدائن، مع وسم هذا القيد كـ "معكوس". لن يتم حذف القيد الأصلي من السجلات المحاسبية لضمان النزاهة التاريخية.</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">سبب العكس</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="وضح سبب عكس هذا القيد بالتفصيل..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">تاريخ سريان القيد العكسي</label>
                        <input type="text" name="reversal_date" id="reversal_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ date('d-m-Y') }}" placeholder="يوم-شهر-سنة" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الفترة المحاسبية المستهدفة للقيد العكسي</label>
                        <select name="accounting_period_id" class="form-select">
                            <option value="">-- نفس فترة القيد الأصلي --</option>
                            @foreach($openPeriods as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger px-4">تأكيد عكس القيد</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
