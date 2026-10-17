@extends('layouts.admin')
@section('title', 'تفاصيل تسوية COD ' . $settlement->settlement_number . ' | لمّة')
@section('page-title', 'تفاصيل تسوية العهدة النقدية')

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="ri-check-double-line me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="ri-alert-line me-1"></i> {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ri-error-warning-line me-1"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card">
            <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-1 d-flex align-items-center gap-2">
                        <span>تسوية رقم: {{ $settlement->settlement_number }}</span>
                        <span class="{{ $settlement->status->badgeClass() }}">{{ $settlement->status->label() }}</span>
                    </h5>
                    <p class="text-muted mb-0 fs-13">تاريخ الإنشاء: {{ $settlement->created_at->format('Y-m-d H:i') }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.finance.cod-settlements.index') }}" class="btn btn-light btn-sm">
                        <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                    </a>
                    @if($settlement->canBePosted() && auth()->user()->hasPermission('admin.driver_cod_settlements.post'))
                        <form method="POST" action="{{ route('admin.finance.cod-settlements.post', $settlement) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من رغبتك في اعتماد وترحيل هذه التسوية؟ سيتم إنشاء قيد محاسبي وتعديل رصيد الخزينة.')">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="ri-check-double-line me-1"></i> اعتماد وترحيل التسوية
                            </button>
                        </form>
                    @endif
                    @if($settlement->canBeReversed() && auth()->user()->hasPermission('admin.driver_cod_settlements.reverse'))
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reverseSettlementModal">
                            <i class="ri-close-circle-line me-1"></i> عكس / إلغاء التسوية
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <!-- Summary Cards Row -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">المندوب</span>
                            <h6 class="fs-15 mb-1">{{ $settlement->driver?->name }}</h6>
                            <small class="text-muted">كود: {{ $settlement->driver?->code }} | هاتف: {{ $settlement->driver?->phone }}</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">حساب الخزينة المستلم</span>
                            <h6 class="fs-15 mb-1">{{ $settlement->treasuryAccount?->name_ar ?? $settlement->treasuryAccount?->name ?? 'غير محدد' }}</h6>
                            <small class="text-muted">العملة: {{ $settlement->currency?->code }} - {{ $settlement->currency?->name }}</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">إجمالي المبلغ المسوى</span>
                            <h4 class="fs-18 fw-bold text-success mb-0">{{ number_format((float)$settlement->total_settled, 2) }} {{ $settlement->currency?->code }}</h4>
                            <small class="text-muted">عدد الشحنات: {{ $settlement->items->count() }}</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">القيد المحاسبي المرتبط</span>
                            @if($settlement->journalEntry)
                                <a href="{{ route('admin.finance.journal-entries.show', $settlement->journalEntry) }}" class="fw-semibold text-primary fs-14 d-block">
                                    <i class="ri-file-list-3-line me-1"></i> {{ $settlement->journalEntry->entry_number }}
                                </a>
                                <small class="text-muted">الحالة: {{ $settlement->journalEntry->status->label() }}</small>
                            @else
                                <span class="text-muted fs-13">لا يوجد قيد (مسودة)</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($settlement->isReversed())
                    <div class="alert alert-danger mb-4" role="alert">
                        <h6 class="alert-heading mb-1"><i class="ri-alert-line me-1"></i> هذه التسوية تم عكسها وإلغاء أثرها المالي</h6>
                        <p class="mb-0 fs-13">
                            <strong>تاريخ العكس:</strong> {{ $settlement->reversed_at?->format('Y-m-d H:i') }} | 
                            <strong>بواسطة:</strong> {{ $settlement->reversedBy?->name ?? 'مستخدم' }} | 
                            <strong>سبب العكس:</strong> {{ $settlement->reversal_reason }}
                        </p>
                    </div>
                @endif

                @if($settlement->notes)
                    <div class="alert alert-light border mb-4">
                        <strong>ملاحظات التسوية:</strong> {{ $settlement->notes }}
                    </div>
                @endif

                <!-- Items Table -->
                <h6 class="fw-bold mb-3">بنود الشحنات المشمولة في التسوية</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>رقم الشحنة</th>
                                <th>رقم الطلب</th>
                                <th>العميل</th>
                                <th>المبلغ المحصل</th>
                                <th>المسوى سابقاً</th>
                                <th>المسوى بهذه العملية</th>
                                <th>المتبقي بعد التسوية</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlement->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <span class="fw-semibold text-primary">{{ $item->delivery?->delivery_number }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-body border">{{ $item->order?->order_number }}</span>
                                    </td>
                                    <td>
                                        {{ $item->delivery?->recipient_name ?? $item->order?->customer?->name ?? 'عميل' }}
                                    </td>
                                    <td>{{ number_format((float)$item->collected_amount, 2) }}</td>
                                    <td>{{ number_format((float)$item->previously_settled_amount, 2) }}</td>
                                    <td>
                                        <strong class="text-success">{{ number_format((float)$item->settlement_amount, 2) }}</strong>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ number_format((float)$item->remaining_amount, 2) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="6" class="text-end">الإجمالي:</td>
                                <td class="text-success fs-15">{{ number_format((float)$settlement->total_settled, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Audit Information -->
                <div class="border-top pt-3 text-muted fs-12 d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <span>أنشئت بواسطة: <strong>{{ $settlement->creator?->name ?? 'النظام' }}</strong> في {{ $settlement->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    @if($settlement->posted_at)
                        <div>
                            <span>تم الاعتماد والترحيل بواسطة: <strong>{{ $settlement->postedBy?->name ?? 'النظام' }}</strong> في {{ $settlement->posted_at->format('Y-m-d H:i') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reversal Modal -->
@if($settlement->canBeReversed())
<div class="modal fade" id="reverseSettlementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.finance.cod-settlements.reverse', $settlement) }}">
                @csrf
                <div class="modal-header bg-danger-subtle">
                    <h5 class="modal-title text-danger"><i class="ri-close-circle-line me-1"></i> عكس وإلغاء تسوية العهدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-14">سيؤدي هذا الإجراء إلى إلغاء تسوية العهدة وعكس القيد المحاسبي المرتبط وإعادة مبالغ الشحنات المسواة كعهد مستحقة على المندوب.</p>
                    <div class="mb-3">
                        <label class="form-label required">سبب العكس / الإلغاء</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="اكتب سبب عكس التسوية بالتفصيل (مطلوب)..." required minlength="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">تراجع</button>
                    <button type="submit" class="btn btn-danger">تأكيد عكس التسوية</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
