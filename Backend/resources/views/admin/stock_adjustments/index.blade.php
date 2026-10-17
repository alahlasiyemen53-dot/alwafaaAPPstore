@extends('layouts.admin')

@section('title', 'التسويات الجردية وشطب التالف | لمّة')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h4 class="card-title mb-0">
                        <i class="ri-equalizer-line me-1 text-primary"></i> أوامر التسويات الجردية ({{ $adjustments->total() }})
                    </h4>
                    @if(auth()->user()->hasPermission('admin.stock_adjustments.create'))
                        <a href="{{ route('admin.stock-adjustments.create') }}" class="btn btn-primary">
                            <i class="ri-add-line me-1"></i> أمر تسوية جديد
                        </a>
                    @endif
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.stock-adjustments.index') }}" class="row g-3 filter-row">
                    <div class="col-md-3">
                        <label class="form-label fs-12 text-muted">البحث (رقم التسوية)</label>
                        <input type="text" name="search" class="form-control" placeholder="ابحث برقم التسوية..." value="{{ request('search') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fs-12 text-muted">المستودع</label>
                        <select name="warehouse_id" class="form-control" data-choices data-choices-search-false>
                            <option value="">جميع المستودعات</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">سبب التسوية</label>
                        <select name="reason_code" class="form-control" data-choices data-choices-search-false>
                            <option value="">الكل</option>
                            @foreach($reasons as $reason)
                                <option value="{{ $reason->value }}" @selected(request('reason_code') === $reason->value)>{{ $reason->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">الحالة</label>
                        <select name="status" class="form-control" data-choices data-choices-search-false>
                            <option value="">الكل</option>
                            <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
                            <option value="posted" @selected(request('status') === 'posted')>مرحّل</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-search-line me-1"></i> تصفية
                        </button>
                        @if(request()->hasAny(['search', 'warehouse_id', 'reason_code', 'status']))
                            <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-light" title="إعادة ضبط">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>رقم التسوية</th>
                                <th>المستودع</th>
                                <th>سبب التسوية</th>
                                <th class="text-center">عدد البنود</th>
                                <th>الحالة</th>
                                <th>تاريخ الترحيل</th>
                                <th>المسؤول</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($adjustments as $adj)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.stock-adjustments.show', $adj) }}" class="fw-bold text-primary">
                                            {{ $adj->adjustment_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-body border">
                                            <i class="ri-building-4-line me-1"></i>{{ $adj->warehouse?->name_ar ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($adj->reason_code === \App\Enums\StockAdjustmentReason::Damaged)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                <i class="ri-forbid-line me-1"></i>{{ $adj->reason_code->label() }}
                                            </span>
                                        @elseif($adj->reason_code === \App\Enums\StockAdjustmentReason::PhysicalCountGain)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="ri-add-line me-1"></i>{{ $adj->reason_code->label() }}
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                {{ $adj->reason_code->label() }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center fw-bold">{{ $adj->items->count() }}</td>
                                    <td>
                                        <span class="badge {{ $adj->status->badgeClass() }}">
                                            {{ $adj->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-muted fs-12">
                                        {{ $adj->posted_at?->format('d-m-Y H:i') ?? $adj->created_at->format('d-m-Y H:i') }}
                                    </td>
                                    <td>{{ $adj->postedBy?->name ?? $adj->createdBy?->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if(auth()->user()->hasPermission('admin.stock_adjustments.view'))
                                                <a href="{{ route('admin.stock-adjustments.show', $adj) }}" class="btn btn-sm btn-soft-info" title="عرض">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                            @endif

                                            @if($adj->status === \App\Enums\StockAdjustmentStatus::Draft && auth()->user()->hasPermission('admin.stock_adjustments.update'))
                                                <a href="{{ route('admin.stock-adjustments.edit', $adj) }}" class="btn btn-sm btn-soft-primary" title="تعديل">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            @endif

                                            @if($adj->status === \App\Enums\StockAdjustmentStatus::Draft && auth()->user()->hasPermission('admin.stock_adjustments.post'))
                                                <form method="POST" action="{{ route('admin.stock-adjustments.post', $adj) }}" onsubmit="return confirm('تنبيه أمني عالي الأهمية: هل أنت متأكد من ترحيل أمر التسوية الجردية؟ سيتم تعديل كميات المخزون فوراً.')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-success" title="ترحيل التسوية">
                                                        <i class="ri-check-line"></i> ترحيل
                                                    </button>
                                                </form>
                                            @endif

                                            @if($adj->status === \App\Enums\StockAdjustmentStatus::Draft && auth()->user()->hasPermission('admin.stock_adjustments.delete'))
                                                <form method="POST" action="{{ route('admin.stock-adjustments.destroy', $adj) }}" onsubmit="return confirm('هل أنت متأكد من حذف أمر التسوية؟')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-soft-danger" title="حذف">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="text-center py-5">
                                            <div class="avatar-md mx-auto mb-3">
                                                <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                                    <i class="ri-equalizer-line"></i>
                                                </div>
                                            </div>
                                            <h5 class="fs-16 mb-1">لا توجد أوامر تسوية مسجلة</h5>
                                            <p class="text-muted mb-0">أنشئ أمر تسوية جديد أو غيّر معايير التصفية.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($adjustments->hasPages())
                    <div class="d-flex justify-content-end mt-3">
                        {{ $adjustments->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
