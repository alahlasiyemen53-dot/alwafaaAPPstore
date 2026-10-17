@extends('layouts.admin')
@section('title', 'فواتير الشراء | الوفاء استور')
@section('page-title', 'فواتير الشراء')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0"><i class="ri-file-list-3-line me-1 text-primary"></i> قائمة فواتير الشراء ({{ $invoices->total() }})</h4>
                @if(auth()->user()->hasPermission('admin.purchases.create'))
                    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary">
                        <i class="ri-add-line me-1"></i> إنشاء فاتورة شراء جديدة
                    </a>
                @endif
            </div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.purchases.index') }}" class="row g-3 align-items-end filter-row" id="purchasesFilterForm">
                    <div class="col-xxl-3 col-md-4">
                        <div>
                            <label for="filter_search" class="form-label mb-1">بحث سريع</label>
                            <input type="text" class="form-control" id="filter_search" name="search" value="{{ request('search') }}" placeholder="رقم الفاتورة أو اسم المورد...">
                        </div>
                    </div>
                    <div class="col-xxl-2 col-md-4">
                        <div>
                            <label for="filter_supplier_id" class="form-label mb-1">المورد</label>
                            <select class="form-control" id="filter_supplier_id" data-choices name="supplier_id">
                                <option value="">جميع الموردين</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}" @selected((string) request('supplier_id') === (string) $sup->id)>{{ $sup->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xxl-2 col-md-4">
                        <div>
                            <label for="filter_warehouse_id" class="form-label mb-1">المستودع</label>
                            <select class="form-control" id="filter_warehouse_id" data-choices data-choices-search-false name="warehouse_id">
                                <option value="">جميع المستودعات</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected((string) request('warehouse_id') === (string) $wh->id)>{{ $wh->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xxl-1 col-md-3">
                        <div>
                            <label for="filter_status" class="form-label mb-1">الحالة</label>
                            <select class="form-control" id="filter_status" data-choices data-choices-search-false name="status">
                                <option value="">الكل</option>
                                <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
                                <option value="posted" @selected(request('status') === 'posted')>مرحّلة</option>
                                <option value="cancelled" @selected(request('status') === 'cancelled')>ملغاة</option>
                            </select>
                        </div>
                    </div>
@php
    $formattedDateFrom = '';
    if (request('date_from')) {
        try {
            $rawFrom = trim((string) request('date_from'));
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $rawFrom)) {
                $formattedDateFrom = $rawFrom;
            } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rawFrom)) {
                $formattedDateFrom = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $rawFrom)->format('d-m-Y');
            } else {
                $formattedDateFrom = \Illuminate\Support\Carbon::parse($rawFrom)->format('d-m-Y');
            }
        } catch (\Throwable) {
            $formattedDateFrom = request('date_from');
        }
    }
    $formattedDateTo = '';
    if (request('date_to')) {
        try {
            $rawTo = trim((string) request('date_to'));
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $rawTo)) {
                $formattedDateTo = $rawTo;
            } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rawTo)) {
                $formattedDateTo = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $rawTo)->format('d-m-Y');
            } else {
                $formattedDateTo = \Illuminate\Support\Carbon::parse($rawTo)->format('d-m-Y');
            }
        } catch (\Throwable) {
            $formattedDateTo = request('date_to');
        }
    }
@endphp
                    <div class="col-xxl-2 col-md-3">
                        <div>
                            <label for="filter_date_from" class="form-label mb-1">من تاريخ</label>
                            <input type="text" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" id="filter_date_from" name="date_from" value="{{ $formattedDateFrom }}" placeholder="يوم-شهر-سنة">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-xxl-2 col-md-3">
                        <div>
                            <label for="filter_date_to" class="form-label mb-1">إلى تاريخ</label>
                            <input type="text" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" id="filter_date_to" name="date_to" value="{{ $formattedDateTo }}" placeholder="يوم-شهر-سنة">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-xxl-auto col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-filter-2-line align-bottom me-1"></i> تصفية
                        </button>
                        @if(request()->hasAny(['search', 'supplier_id', 'warehouse_id', 'status', 'date_from', 'date_to']))
                            <a href="{{ route('admin.purchases.index') }}" class="btn btn-soft-danger" title="إعادة ضبط الفلاتر">
                                <i class="ri-refresh-line align-bottom me-1"></i> إعادة ضبط
                            </a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>تاريخ الفاتورة</th>
                                <th>المورد</th>
                                <th>مستودع الاستلام</th>
                                <th>عدد الأصناف</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $invoice)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.purchases.show', $invoice) }}" class="fw-bold text-primary fs-13">
                                             {{ $invoice->invoice_number }}
                                        </a>
                                        @if($invoice->supplier_invoice_number)
                                            <div class="text-muted fs-11" dir="ltr">رقم المورد: {{ $invoice->supplier_invoice_number }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span>{{ $invoice->invoice_date?->format('d-m-Y') }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $invoice->supplier?->name }}</span>
                                    </td>
                                    <td>{{ $invoice->warehouse?->name_ar }}</td>
                                    <td>
                                        <span class="badge bg-light text-body border">{{ $invoice->items_count }} أصناف</span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark fs-14" dir="ltr">
                                            {{ number_format((float) $invoice->total, $invoice->currency?->decimal_places ?? 2) }} {{ $invoice->currency?->symbol ?? $invoice->currency?->code }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $invoice->status->badgeClass() }}">
                                            {{ $invoice->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('admin.purchases.show', $invoice) }}" class="btn btn-sm btn-soft-info" title="عرض التفاصيل">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @if(auth()->user()->hasPermission('admin.purchases.update'))
                                                <a href="{{ route('admin.purchases.edit', $invoice) }}" class="btn btn-sm btn-soft-primary" title="تعديل">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('admin.purchases.delete'))
                                                <form method="POST" action="{{ route('admin.purchases.destroy', $invoice) }}" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف فاتورة الشراء {{ $invoice->invoice_number }}؟ {{ $invoice->isPosted() ? 'سيتم أيضاً حذف قيد الالتزام المالي المرتبط بها من دفتر أستاذ المورد.' : '' }}')">
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
                                                    <i class="ri-file-list-3-line"></i>
                                                </div>
                                            </div>
                                            <h5 class="fs-16 mb-1">لا توجد فواتير شراء مطابقة</h5>
                                            <p class="text-muted mb-0">غيّر معايير البحث أو أنشئ فاتورة شراء جديدة.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($invoices->hasPages())
                    <div class="card-footer py-2">
                        {{ $invoices->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
