@extends('layouts.admin')

@section('title', 'أرصدة المخزون | الوفاء استور')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0">
                        <i class="ri-archive-line me-1 text-primary"></i> أرصدة المخزون ({{ $inventories->total() }})
                    </h4>
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.inventory.index') }}" class="row g-3 filter-row">
                    <div class="col-md-4">
                        <label class="form-label fs-12 text-muted">البحث (الاسم / SKU / الباركود)</label>
                        <input type="text" name="search" class="form-control" placeholder="ابحث عن صنف أو باركود..." value="{{ request('search') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fs-12 text-muted">المستودع</label>
                        <select name="warehouse_id" class="form-control" data-choices data-choices-search-false>
                            <option value="">جميع المستودعات</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>
                                    {{ $warehouse->name_ar }} ({{ $warehouse->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fs-12 text-muted">حالة التوفر</label>
                        <select name="stock_status" class="form-control" data-choices data-choices-search-false>
                            <option value="">الكل</option>
                            <option value="in_stock" @selected(request('stock_status') === 'in_stock')>متوفر بالمخزون</option>
                            <option value="out_of_stock" @selected(request('stock_status') === 'out_of_stock')>نفد من المخزون</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-search-line me-1"></i> تصفية
                        </button>
                        @if(request()->hasAny(['search', 'warehouse_id', 'stock_status']))
                            <a href="{{ route('admin.inventory.index') }}" class="btn btn-light" title="إعادة ضبط">
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
                                <th>الصنف / المتغير</th>
                                <th>رمز الصنف (SKU)</th>
                                <th>المستودع</th>
                                <th class="text-center">الرصيد الفعلي</th>
                                <th class="text-center">المحجوز</th>
                                <th class="text-center">الرصيد المتاح</th>
                                <th>آخر تحديث</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inventories as $inventory)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <h6 class="fs-14 mb-0 fw-semibold">
                                                    {{ $inventory->productVariant?->product?->name_ar ?? 'صنف غير معروف' }}
                                                </h6>
                                                @if($inventory->productVariant?->barcode)
                                                    <small class="text-muted" dir="ltr">
                                                        <i class="ri-barcode-line me-1"></i>{{ $inventory->productVariant->barcode }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-body border" dir="ltr">
                                            {{ $inventory->productVariant?->sku ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <i class="ri-building-4-line me-1"></i>{{ $inventory->warehouse?->name_ar ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold fs-14 text-dark">
                                            {{ number_format((float) $inventory->quantity, 2) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-muted fs-13">
                                            {{ number_format((float) $inventory->reserved_quantity, 2) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if($inventory->available_quantity > 0)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fs-12">
                                                {{ number_format((float) $inventory->available_quantity, 2) }}
                                            </span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-12">
                                                0.00 (نفد)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-muted fs-12">
                                        {{ $inventory->updated_at?->format('Y-m-d H:i') ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="text-center py-5">
                                            <div class="avatar-md mx-auto mb-3">
                                                <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                                    <i class="ri-archive-line"></i>
                                                </div>
                                            </div>
                                            <h5 class="fs-16 mb-1">لا توجد أرصدة مخزنية مطابقة</h5>
                                            <p class="text-muted mb-0">جرّب تغيير خيارات البحث أو المستودع المحدد.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($inventories->hasPages())
                    <div class="d-flex justify-content-end mt-3">
                        {{ $inventories->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
