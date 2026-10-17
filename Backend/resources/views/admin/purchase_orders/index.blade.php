@extends('layouts.admin')
@section('title', 'طلبات الشراء حسب المورد | لمّة')
@section('page-title', 'طلبات الشراء (مجمّعة حسب المورد)')

@section('content')
<div class="row">
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-start border-warning border-3"><div class="card-body py-3">
            <p class="text-muted mb-1 fs-12">بانتظار الاستلام</p>
            <h4 class="mb-0 text-warning">{{ number_format($metrics['pending']) }}</h4>
        </div></div>
    </div>
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-start border-info border-3"><div class="card-body py-3">
            <p class="text-muted mb-1 fs-12">مسند للمندوب</p>
            <h4 class="mb-0 text-info">{{ number_format($metrics['assigned']) }}</h4>
        </div></div>
    </div>
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-start border-success border-3"><div class="card-body py-3">
            <p class="text-muted mb-1 fs-12">تم الاستلام</p>
            <h4 class="mb-0 text-success">{{ number_format($metrics['collected']) }}</h4>
        </div></div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start border-secondary border-3"><div class="card-body py-3">
            <p class="text-muted mb-1 fs-12">معلّق (صنف ناقص)</p>
            <h4 class="mb-0">{{ number_format($metrics['on_hold']) }}</h4>
        </div></div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start border-danger border-3"><div class="card-body py-3">
            <p class="text-muted mb-1 fs-12">تعذر الاستلام</p>
            <h4 class="mb-0 text-danger">{{ number_format($metrics['failed']) }}</h4>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0"><i class="ri-file-list-3-line me-1 text-primary"></i> طلبات الشراء ({{ $purchaseOrders->total() }})</h4>
        <span class="text-muted fs-12">تُنشأ تلقائياً عند تأكيد الطلب — طلب شراء واحد لكل مورد</span>
    </div>
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('admin.purchase-orders.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3"><input type="text" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث برقم PO، الطلب، المورد..."></div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">كل الحالات</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="supplier_id">
                    <option value="">كل الموردين</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string)($filters['supplier_id'] ?? '') === (string)$supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="driver_id">
                    <option value="">كل المناديب</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}" @selected((string)($filters['driver_id'] ?? '') === (string)$driver->id)>{{ $driver->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="ri-filter-3-line"></i> تصفية</button></div>
            <div class="col-md-1"><a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-light w-100"><i class="ri-close-line"></i></a></div>
        </form>
        @if($dateError)<div class="alert alert-danger mt-2 mb-0">{{ $dateError }}</div>@endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>رقم طلب الشراء</th>
                    <th>طلب العميل</th>
                    <th>المورد</th>
                    <th>البنود</th>
                    <th>الحالة</th>
                    <th>المندوب</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseOrders as $po)
                    <tr>
                        <td><a href="{{ route('admin.purchase-orders.show', $po) }}" class="fw-semibold">{{ $po->po_number }}</a></td>
                        <td><a href="{{ route('admin.orders.show', $po->order) }}">{{ $po->order?->order_number }}</a></td>
                        <td>{{ $po->supplier?->name }}</td>
                        <td>{{ $po->active_items_count }}</td>
                        <td><span class="badge {{ $po->status->badgeClass() }}">{{ $po->status->label() }}</span></td>
                        <td>{{ $po->assignedDriver?->name ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.purchase-orders.show', $po) }}" class="btn btn-sm btn-subtle-primary"><i class="ri-eye-line"></i></a>
                            @if($po->canCollectFromSupplier() && auth()->user()->hasPermission('admin.sourcing.manage'))
                                <form method="POST" action="{{ route('admin.purchase-orders.confirm-collected', $po) }}" class="d-inline" data-confirm="تأكيد استلام كل بنود طلب الشراء من المورد؟">
                                    @csrf
                                    <button class="btn btn-sm btn-success" type="submit"><i class="ri-checkbox-circle-line"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">لا توجد طلبات شراء.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($purchaseOrders->hasPages())
        <div class="card-footer">{{ $purchaseOrders->links() }}</div>
    @endif
</div>
@endsection
