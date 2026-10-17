@extends('layouts.admin')
@section('title', 'تسويات عهد المناديب (COD) | لمّة')
@section('page-title', 'تسويات عهد المناديب النقدية')

@section('content')
<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">إجمالي المبالغ المسواة (المرحلة)</p>
                        <h4 class="fs-20 fw-semibold mb-0 mt-2 text-success">{{ number_format($totalPostedAmount, 2) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                            <i class="ri-hand-coin-line"></i>
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
                        <p class="text-uppercase fw-medium text-muted mb-0">تسويات المسودة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-warning">{{ number_format($countDraft) }}</h4>
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
                        <p class="text-uppercase fw-medium text-muted mb-0">تسويات معتمدة ومرحلة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-primary">{{ number_format($countPosted) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                            <i class="ri-check-double-line"></i>
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
                        <p class="text-uppercase fw-medium text-muted mb-0">تسويات ملغاة / معكوسة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-danger">{{ number_format($countReversed) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle text-danger rounded fs-3">
                            <i class="ri-close-circle-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="card-title mb-0">سجل تسويات الدفع عند الاستلام (COD)</h5>
        <div class="d-flex gap-2">
            @if(auth()->user()->hasPermission('admin.driver_cod_settlements.create'))
                <a href="{{ route('admin.finance.cod-settlements.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> تسوية عهدة جديدة
                </a>
            @endif
            @if(auth()->user()->hasPermission('admin.driver_cod_statements.view'))
                <a href="{{ route('admin.finance.cod-settlements.statements') }}" class="btn btn-subtle-info btn-sm">
                    <i class="ri-file-user-line align-bottom me-1"></i> كشف عهدة المندوب
                </a>
            @endif
            @if(auth()->user()->hasPermission('admin.driver_cod_reconciliation.view'))
                <a href="{{ route('admin.finance.cod-settlements.reconciliation') }}" class="btn btn-subtle-success btn-sm">
                    <i class="ri-shield-check-line align-bottom me-1"></i> مطابقة العهد
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.finance.cod-settlements.index') }}" class="row g-3 mb-4 filter-row">
            <div class="col-md-3">
                <label class="form-label text-muted fs-12">بحث</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ri-search-line"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="رقم التسوية، الملاحظات..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted fs-12">المندوب</label>
                <select name="driver_id" class="form-select">
                    <option value="">جميع المناديب</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}" {{ request('driver_id') == $driver->id ? 'selected' : '' }}>{{ $driver->name }} ({{ $driver->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted fs-12">الحالة</label>
                <select name="status" class="form-select">
                    <option value="">كافة الحالات</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}" {{ request('status') == $st->value ? 'selected' : '' }}>{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted fs-12">العملة</label>
                <select name="currency_id" class="form-select">
                    <option value="">كل العملات</option>
                    @foreach($currencies as $curr)
                        <option value="{{ $curr->id }}" {{ request('currency_id') == $curr->id ? 'selected' : '' }}>{{ $curr->code }} - {{ $curr->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-filter-2-line me-1"></i> تصفية</button>
                <a href="{{ route('admin.finance.cod-settlements.index') }}" class="btn btn-light" title="إعادة تعيين"><i class="ri-refresh-line"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-nowrap mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">رقم التسوية</th>
                        <th scope="col">المندوب</th>
                        <th scope="col">العملة</th>
                        <th scope="col">الخزينة المستلمة</th>
                        <th scope="col">إجمالي المسوى</th>
                        <th scope="col">القيد المحاسبي</th>
                        <th scope="col">الحالة</th>
                        <th scope="col">تاريخ الترحيل</th>
                        <th scope="col" class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($settlements as $settlement)
                        <tr>
                            <td>
                                <a href="{{ route('admin.finance.cod-settlements.show', $settlement) }}" class="fw-semibold text-primary">
                                    {{ $settlement->settlement_number }}
                                </a>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-xs me-2">
                                        <span class="avatar-title rounded-circle bg-secondary-subtle text-secondary fs-12">
                                            <i class="ri-user-line"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fs-13">{{ $settlement->driver?->name }}</h6>
                                        <small class="text-muted">{{ $settlement->driver?->code }}</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-body border">{{ $settlement->currency?->code }}</span></td>
                            <td>
                                <span class="fs-13">
                                    <i class="ri-safe-2-line text-muted me-1"></i>
                                    {{ $settlement->treasuryAccount?->name_ar ?? $settlement->treasuryAccount?->name ?? 'غير محدد' }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-success fs-14">
                                    {{ number_format((float) $settlement->total_settled, 2) }}
                                </span>
                            </td>
                            <td>
                                @if($settlement->journalEntry)
                                    <a href="{{ route('admin.finance.journal-entries.show', $settlement->journalEntry) }}" class="badge bg-info-subtle text-info border border-info-subtle">
                                        <i class="ri-file-list-3-line me-1"></i> {{ $settlement->journalEntry->entry_number }}
                                    </a>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $settlement->status->badgeClass() }}">
                                    {{ $settlement->status->label() }}
                                </span>
                            </td>
                            <td>
                                @if($settlement->posted_at)
                                    <span class="text-muted fs-12">{{ $settlement->posted_at->format('d-m-Y H:i') }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.finance.cod-settlements.show', $settlement) }}" class="btn btn-sm btn-subtle-primary" title="عرض التفاصيل">
                                    <i class="ri-eye-line"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="avatar-lg mx-auto mb-3">
                                    <div class="avatar-title bg-light text-muted rounded-circle fs-24">
                                        <i class="ri-hand-coin-line"></i>
                                    </div>
                                </div>
                                <h5 class="text-muted">لا توجد تسويات عهد مطابقة</h5>
                                <p class="text-muted mb-3">يمكنك إنشاء تسوية جديدة لترحيل المبالغ المحصلة من المندوب إلى الخزينة.</p>
                                @if(auth()->user()->hasPermission('admin.driver_cod_settlements.create'))
                                    <a href="{{ route('admin.finance.cod-settlements.create') }}" class="btn btn-primary btn-sm">
                                        <i class="ri-add-line me-1"></i> إنشاء تسوية عهدة جديدة
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end mt-4">
            {{ $settlements->links() }}
        </div>
    </div>
</div>
@endsection
