@extends('layouts.admin')

@section('title', 'تحويلات المخزون بين المستودعات | الوفاء استور')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h4 class="card-title mb-0">
                        <i class="ri-arrow-left-right-line me-1 text-primary"></i> أوامر تحويل المخزون ({{ $transfers->total() }})
                    </h4>
                    @if(auth()->user()->hasPermission('admin.stock_transfers.create'))
                        <a href="{{ route('admin.stock-transfers.create') }}" class="btn btn-primary">
                            <i class="ri-add-line me-1"></i> أمر تحويل جديد
                        </a>
                    @endif
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.stock-transfers.index') }}" class="row g-3 filter-row">
                    <div class="col-md-3">
                        <label class="form-label fs-12 text-muted">البحث (رقم التحويل)</label>
                        <input type="text" name="search" class="form-control" placeholder="ابحث برقم التحويل..." value="{{ request('search') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">من مستودع</label>
                        <select name="from_warehouse_id" class="form-control" data-choices data-choices-search-false>
                            <option value="">الكل</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected(request('from_warehouse_id') == $wh->id)>{{ $wh->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">إلى مستودع</label>
                        <select name="to_warehouse_id" class="form-control" data-choices data-choices-search-false>
                            <option value="">الكل</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected(request('to_warehouse_id') == $wh->id)>{{ $wh->name_ar }}</option>
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

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-search-line me-1"></i> تصفية
                        </button>
                        @if(request()->hasAny(['search', 'from_warehouse_id', 'to_warehouse_id', 'status']))
                            <a href="{{ route('admin.stock-transfers.index') }}" class="btn btn-light" title="إعادة ضبط">
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
                                <th>رقم التحويل</th>
                                <th>من مستودع</th>
                                <th>إلى مستودع</th>
                                <th class="text-center">عدد الأصناف</th>
                                <th>الحالة</th>
                                <th>تاريخ الترحيل</th>
                                <th>المسؤول</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transfers as $transfer)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.stock-transfers.show', $transfer) }}" class="fw-bold text-primary">
                                            {{ $transfer->transfer_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            {{ $transfer->fromWarehouse?->name_ar ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            {{ $transfer->toWarehouse?->name_ar ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold">{{ $transfer->items->count() }}</td>
                                    <td>
                                        <span class="badge {{ $transfer->status->badgeClass() }}">
                                            {{ $transfer->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-muted fs-12">
                                        {{ $transfer->posted_at?->format('d-m-Y H:i') ?? $transfer->created_at->format('d-m-Y H:i') }}
                                    </td>
                                    <td>{{ $transfer->postedBy?->name ?? $transfer->createdBy?->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if(auth()->user()->hasPermission('admin.stock_transfers.view'))
                                                <a href="{{ route('admin.stock-transfers.show', $transfer) }}" class="btn btn-sm btn-soft-info" title="عرض">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                            @endif

                                            @if($transfer->status === \App\Enums\StockTransferStatus::Draft && auth()->user()->hasPermission('admin.stock_transfers.update'))
                                                <a href="{{ route('admin.stock-transfers.edit', $transfer) }}" class="btn btn-sm btn-soft-primary" title="تعديل">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            @endif

                                            @if($transfer->status === \App\Enums\StockTransferStatus::Draft && auth()->user()->hasPermission('admin.stock_transfers.post'))
                                                <form method="POST" action="{{ route('admin.stock-transfers.post', $transfer) }}" onsubmit="return confirm('هل أنت متأكد من ترحيل أمر التحويل؟ سيتم خصم الكميات من مستودع المصدر وإضافتها لمستودع الوجهة فوراً.')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-success" title="ترحيل التحويل">
                                                        <i class="ri-check-line"></i> ترحيل
                                                    </button>
                                                </form>
                                            @endif

                                            @if($transfer->status === \App\Enums\StockTransferStatus::Draft && auth()->user()->hasPermission('admin.stock_transfers.delete'))
                                                <form method="POST" action="{{ route('admin.stock-transfers.destroy', $transfer) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا التحويل؟')">
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
                                                    <i class="ri-arrow-left-right-line"></i>
                                                </div>
                                            </div>
                                            <h5 class="fs-16 mb-1">لا توجد أوامر تحويل مسجلة</h5>
                                            <p class="text-muted mb-0">أنشئ أمر تحويل جديد لنقل البضائع بين المستودعات.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($transfers->hasPages())
                    <div class="d-flex justify-content-end mt-3">
                        {{ $transfers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
