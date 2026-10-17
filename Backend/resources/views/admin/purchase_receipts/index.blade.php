@extends('layouts.admin')

@section('title', 'سندات استلام المشتريات | الوفاء استور')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h4 class="card-title mb-0">
                        <i class="ri-inbox-archive-line me-1 text-primary"></i> سندات استلام المشتريات ({{ $receipts->total() }})
                    </h4>
                    @if(auth()->user()->hasPermission('admin.purchase_receipts.create'))
                        <a href="{{ route('admin.purchase-receipts.create') }}" class="btn btn-primary">
                            <i class="ri-add-line me-1"></i> استلام مشتريات جديد
                        </a>
                    @endif
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.purchase-receipts.index') }}" class="row g-3 align-items-end filter-row" id="purchaseReceiptsFilterForm">
                    <div class="col-md-3">
                        <div>
                            <label for="filter_search" class="form-label mb-1">البحث (سند / فاتورة / مورد)</label>
                            <input type="text" id="filter_search" name="search" class="form-control" placeholder="ابحث..." value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div>
                            <label for="filter_warehouse_id" class="form-label mb-1">المستودع</label>
                            <select id="filter_warehouse_id" name="warehouse_id" class="form-control" data-choices data-choices-search-false>
                                <option value="">جميع المستودعات</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>
                                        {{ $warehouse->name_ar }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-1">
                        <div>
                            <label for="filter_status" class="form-label mb-1">الحالة</label>
                            <select id="filter_status" name="status" class="form-control" data-choices data-choices-search-false>
                                <option value="">الكل</option>
                                <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
                                <option value="posted" @selected(request('status') === 'posted')>مرحّل</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div>
                            <label for="filter_from_date" class="form-label mb-1">من تاريخ</label>
                            <input type="text" id="filter_from_date" name="from_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('from_date')) }}" placeholder="يوم-شهر-سنة">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div>
                            <label for="filter_to_date" class="form-label mb-1">إلى تاريخ</label>
                            <input type="text" id="filter_to_date" name="to_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('to_date')) }}" placeholder="يوم-شهر-سنة">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ri-search-line me-1"></i> تصفية
                        </button>
                        @if(request()->hasAny(['search', 'warehouse_id', 'status', 'from_date', 'to_date']))
                            <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-soft-danger" title="إعادة ضبط">
                                <i class="ri-refresh-line"></i>
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
                                <th>رقم السند</th>
                                <th>فاتورة الشراء الأصلية</th>
                                <th>المورد</th>
                                <th>المستودع</th>
                                <th>الحالة</th>
                                <th>تاريخ الاستلام</th>
                                <th>المستلم</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receipts as $receipt)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.purchase-receipts.show', $receipt) }}" class="fw-bold text-primary">
                                            {{ $receipt->receipt_number }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($receipt->purchaseInvoice)
                                            <a href="{{ route('admin.purchases.show', $receipt->purchaseInvoice) }}" class="text-decoration-underline text-secondary">
                                                {{ $receipt->purchaseInvoice->invoice_number }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $receipt->purchaseInvoice?->supplier?->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-light text-body border">
                                            <i class="ri-building-4-line me-1"></i>{{ $receipt->warehouse?->name_ar ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $receipt->status->badgeClass() }}">
                                            {{ $receipt->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-muted fs-12">
                                        {{ ($receipt->received_at ?? $receipt->created_at)->format('d-m-Y H:i') }}
                                    </td>
                                    <td>{{ $receipt->receivedBy?->name ?? $receipt->createdBy?->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if(auth()->user()->hasPermission('admin.purchase_receipts.view'))
                                                <a href="{{ route('admin.purchase-receipts.show', $receipt) }}" class="btn btn-sm btn-outline-info" title="عرض التفاصيل">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                            @endif

                                            @if($receipt->status === \App\Enums\PurchaseReceiptStatus::Draft && auth()->user()->hasPermission('admin.purchase_receipts.update'))
                                                <a href="{{ route('admin.purchase-receipts.edit', $receipt) }}" class="btn btn-sm btn-soft-primary" title="تعديل المسودة">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            @endif

                                            @if($receipt->status === \App\Enums\PurchaseReceiptStatus::Draft && auth()->user()->hasPermission('admin.purchase_receipts.post'))
                                                <form method="POST" action="{{ route('admin.purchase-receipts.post', $receipt) }}" onsubmit="return confirm('هل أنت متأكد من ترحيل سند الاستلام؟ سيتم زيادة المخزون الفعلي في المستودع فوراً ولا يمكن التراجع.')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-success" title="ترحيل وإيداع المخزون">
                                                        <i class="ri-check-line"></i> ترحيل
                                                    </button>
                                                </form>
                                            @endif

                                            @if($receipt->status === \App\Enums\PurchaseReceiptStatus::Draft && auth()->user()->hasPermission('admin.purchase_receipts.delete'))
                                                <form method="POST" action="{{ route('admin.purchase-receipts.destroy', $receipt) }}" onsubmit="return confirm('هل أنت متأكد من حذف مسودة سند الاستلام؟')">
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
                                                    <i class="ri-inbox-2-line"></i>
                                                </div>
                                            </div>
                                            <h5 class="fs-16 mb-1">لا توجد سندات استلام مسجلة</h5>
                                            <p class="text-muted mb-0">ابدأ بإنشاء سند استلام جديد أو غيّر خيارات التصفية.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($receipts->hasPages())
                    <div class="d-flex justify-content-end mt-3">
                        {{ $receipts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
