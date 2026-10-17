@extends('layouts.admin')
@section('title', 'تفاصيل حركة الخزينة ' . $transaction->transaction_number . ' | لمّة')
@section('page-title', 'تفاصيل حركة الخزينة')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0">سند رقم: <span class="text-primary">{{ $transaction->transaction_number }}</span></h5>
                    <small class="text-muted">تاريخ الإنشاء: {{ $transaction->created_at->format('Y-m-d H:i') }}</small>
                </div>
                <div class="d-flex gap-2">
                    <span class="{{ $transaction->status->badgeClass() }} fs-13 py-1 px-2">
                        {{ $transaction->status->label() }}
                    </span>
                    <span class="{{ $transaction->transaction_type->badgeClass() }} fs-13 py-1 px-2">
                        {{ $transaction->transaction_type->label() }}
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <p class="text-muted mb-1">حساب الخزينة (المصدر)</p>
                        <h6 class="fs-15 mb-0 fw-semibold">
                            {{ $transaction->account?->displayName ?? 'غير محدد' }}
                        </h6>
                        @if($transaction->account?->glAccount)
                            <small class="text-muted">الأستاذ العام: <code>{{ $transaction->account->glAccount->code }}</code> - {{ $transaction->account->glAccount->name_ar }}</small>
                        @endif
                    </div>
                    <div class="col-md-6">
                        @if($transaction->isTransfer())
                            <p class="text-muted mb-1">حساب الخزينة (الوجهة)</p>
                            <h6 class="fs-15 mb-0 fw-semibold text-primary">
                                <i class="ri-arrow-left-line"></i> {{ $transaction->destinationAccount?->displayName ?? 'غير محدد' }}
                            </h6>
                            @if($transaction->destinationAccount?->glAccount)
                                <small class="text-muted">الأستاذ العام: <code>{{ $transaction->destinationAccount->glAccount->code }}</code> - {{ $transaction->destinationAccount->glAccount->name_ar }}</small>
                            @endif
                        @else
                            <p class="text-muted mb-1">الحساب المقابل في الأستاذ العام</p>
                            <h6 class="fs-15 mb-0 fw-semibold">
                                @if($transaction->offsetAccount)
                                    <code>{{ $transaction->offsetAccount->code }}</code> - {{ $transaction->offsetAccount->name_ar }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </h6>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted mb-1">المبلغ المحاسبي</p>
                        <h4 class="fs-20 mb-0 fw-bold {{ $transaction->transaction_type->isIncoming() ? 'text-success' : 'text-danger' }}">
                            {{ $transaction->transaction_type->isIncoming() ? '+' : '-' }} {{ number_format((float)$transaction->amount, 2) }} {{ $transaction->currency?->code }}
                        </h4>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted mb-1">سعر الصرف المعتمد</p>
                        <h6 class="fs-15 mb-0">{{ number_format((float)$transaction->exchange_rate_snapshot, 8) }}</h6>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted mb-1">المعادل بالعملة الأساسية</p>
                        <h6 class="fs-15 mb-0 fw-semibold">{{ number_format((float)$transaction->base_currency_amount, 2) }}</h6>
                    </div>
                    <div class="col-12">
                        <p class="text-muted mb-1">البيان / الوصف</p>
                        <p class="fs-14 bg-light p-3 rounded mb-0">{{ $transaction->description }}</p>
                    </div>
                    @if($transaction->reference_number)
                        <div class="col-md-6">
                            <p class="text-muted mb-1">رقم المرجع الخارجي</p>
                            <code>{{ $transaction->reference_number }}</code>
                        </div>
                    @endif
                </div>

                @if($transaction->journalEntry)
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fs-15 mb-0">
                                <i class="ri-file-list-3-line text-primary me-1"></i> القيد اليومي المرتبط:
                                <a href="{{ route('admin.finance.journal-entries.show', $transaction->journalEntry) }}" class="fw-bold">
                                    {{ $transaction->journalEntry->entry_number }}
                                </a>
                            </h6>
                            <span class="badge bg-success-subtle text-success">مرحل للأستاذ العام</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>الحساب المحاسبي</th>
                                        <th>البيان</th>
                                        <th class="text-end">مدين (Base)</th>
                                        <th class="text-end">دائن (Base)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transaction->journalEntry->lines as $line)
                                        <tr>
                                            <td><code>{{ $line->account?->code }}</code> - {{ $line->account?->name_ar }}</td>
                                            <td>{{ $line->description }}</td>
                                            <td class="text-end fw-semibold text-success">{{ bccomp((string)$line->debit, '0', 4) > 0 ? number_format((float)$line->debit, 2) : '-' }}</td>
                                            <td class="text-end fw-semibold text-danger">{{ bccomp((string)$line->credit, '0', 4) > 0 ? number_format((float)$line->credit, 2) : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if($transaction->isReversed())
                    <div class="alert alert-danger mt-4 mb-0">
                        <h6 class="text-danger mb-1"><i class="ri-error-warning-line me-1"></i> تم عكس هذه الحركة المحاسبية</h6>
                        <p class="mb-1 fs-13">تاريخ العكس: {{ $transaction->reversed_at?->format('Y-m-d H:i') }} بواسطة {{ $transaction->reversedBy?->name ?? 'المستخدم' }}</p>
                        @if($transaction->reversal_reason)
                            <p class="mb-0 fs-13"><strong>سبب العكس:</strong> {{ $transaction->reversal_reason }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header border-bottom-dashed">
                <h5 class="card-title mb-0">الإجراءات والعمليات</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    @if($transaction->isDraft())
                        @if(auth()->user()->hasPermission('admin.treasury_transactions.post'))
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#postModal">
                                <i class="ri-check-double-line me-1"></i> ترحيل الحركة ماليًا
                            </button>
                        @endif
                        @if(auth()->user()->hasPermission('admin.treasury_transactions.update'))
                            <a href="{{ route('admin.finance.treasury.transactions.edit', $transaction) }}" class="btn btn-outline-primary">
                                <i class="ri-edit-line me-1"></i> تعديل بيانات المسودة
                            </a>
                        @endif
                    @elseif($transaction->isPosted() && auth()->user()->hasPermission('admin.treasury_transactions.reverse'))
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#reverseModal">
                            <i class="ri-arrow-go-back-line me-1"></i> عكس الحركة والقيد المحاسبي
                        </button>
                    @endif

                    <a href="{{ route('admin.finance.treasury.transactions.index') }}" class="btn btn-light">
                        <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                    </a>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <h6 class="fs-13 text-muted text-uppercase mb-2">معلومات إضافية</h6>
                    <ul class="list-unstyled mb-0 vstack gap-2 fs-13">
                        <li><strong>المُنشئ:</strong> {{ $transaction->createdBy?->name ?? '-' }}</li>
                        <li><strong>تاريخ الإنشاء:</strong> {{ $transaction->created_at->format('Y-m-d H:i') }}</li>
                        @if($transaction->posted_at)
                            <li><strong>تاريخ الترحيل:</strong> {{ $transaction->posted_at->format('Y-m-d H:i') }}</li>
                            <li><strong>المُرحّل:</strong> {{ $transaction->postedBy?->name ?? '-' }}</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Post Confirmation Modal --}}
@if($transaction->isDraft() && auth()->user()->hasPermission('admin.treasury_transactions.post'))
<div class="modal fade" id="postModal" tabindex="-1" aria-labelledby="postModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="postModalLabel">تأكيد الترحيل المالي</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="ri-alert-line me-1"></i>
                    سيتم ترحيل الحركة ماليًا وإنشاء قيد محاسبي في الأستاذ العام. بعد الترحيل لا يمكن تعديل الحركة مباشرة.
                </div>
                <p class="mb-0">هل أنت متأكد من رغبتك في ترحيل سند الخزينة رقم <strong>{{ $transaction->transaction_number }}</strong> بمبلغ <strong>{{ number_format((float)$transaction->amount, 2) }} {{ $transaction->currency?->code }}</strong>؟</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <form action="{{ route('admin.finance.treasury.transactions.post', $transaction) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success">تأكيد الترحيل الآن</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Reverse Confirmation Modal --}}
@if($transaction->isPosted() && auth()->user()->hasPermission('admin.treasury_transactions.reverse'))
<div class="modal fade" id="reverseModal" tabindex="-1" aria-labelledby="reverseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="reverseModalLabel">عكس الحركة المحاسبية</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.finance.treasury.transactions.reverse', $transaction) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-danger mb-3">
                        <i class="ri-error-warning-line me-1"></i>
                        سيتم إنشاء قيد محاسبي عكسي في الأستاذ العام وتعيين حالة الحركة كـ "معكوسة". هذه العملية غير قابلة للتراجع.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">سبب العكس <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="اذكر سبب عكس هذه الحركة بالتفصيل..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">تأكيد العكس المحاسبي</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
