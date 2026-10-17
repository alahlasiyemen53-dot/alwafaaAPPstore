@extends('layouts.admin')

@section('title', 'سجل حركات المخزون | لمّة')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0">
                        <i class="ri-history-line me-1 text-primary"></i> سجل حركات المخزون غير القابل للتعديل ({{ $movements->total() }})
                    </h4>
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.stock-movements.index') }}" class="row g-3 filter-row">
                    <div class="col-md-3">
                        <label class="form-label fs-12 text-muted">البحث (الصنف / SKU / الباركود)</label>
                        <input type="text" name="search" class="form-control" placeholder="ابحث..." value="{{ request('search') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">المستودع</label>
                        <select name="warehouse_id" class="form-control" data-choices data-choices-search-false>
                            <option value="">جميع المستودعات</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">نوع الحركة</label>
                        <select name="movement_type" class="form-control" data-choices data-choices-search-false>
                            <option value="">الكل</option>
                            @foreach($movementTypes as $mt)
                                <option value="{{ $mt->value }}" @selected(request('movement_type') === $mt->value)>{{ $mt->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">الاتجاه</label>
                        <select name="direction" class="form-control" data-choices data-choices-search-false>
                            <option value="">الكل</option>
                            @foreach($directions as $dir)
                                <option value="{{ $dir->value }}" @selected(request('direction') === $dir->value)>{{ $dir->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-search-line me-1"></i> تصفية
                        </button>
                        @if(request()->hasAny(['search', 'warehouse_id', 'movement_type', 'direction', 'from_date', 'to_date']))
                            <a href="{{ route('admin.stock-movements.index') }}" class="btn btn-light" title="إعادة ضبط">
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
                                <th>التاريخ والوقت</th>
                                <th>المستودع</th>
                                <th>الصنف / المتغير</th>
                                <th>رمز الصنف (SKU)</th>
                                <th>نوع الحركة</th>
                                <th class="text-center">الاتجاه</th>
                                <th class="text-center">الكمية</th>
                                <th>المستند المرجعي</th>
                                <th>المستخدم</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                                <tr>
                                    <td class="text-muted fs-12">
                                        {{ $movement->occurred_at?->format('d-m-Y H:i') ?? $movement->created_at->format('d-m-Y H:i') }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-body border">
                                            <i class="ri-building-4-line me-1"></i>{{ $movement->warehouse?->name_ar ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{{ $movement->productVariant?->product?->name_ar ?? '—' }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-body border" dir="ltr">
                                            {{ $movement->productVariant?->sku ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                            {{ $movement->movement_type->label() }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $movement->direction->badgeClass() }}">
                                            {{ $movement->direction->label() }}
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold fs-14 {{ $movement->direction->value === 'in' ? 'text-success' : 'text-danger' }}">
                                        {{ $movement->direction->value === 'in' ? '+' : '-' }}{{ number_format((float) $movement->quantity, 2) }}
                                    </td>
                                    <td>
                                        @if($movement->reference instanceof \App\Models\PurchaseReceipt)
                                            <a href="{{ route('admin.purchase-receipts.show', $movement->reference) }}" class="text-primary text-decoration-underline">
                                                <i class="ri-inbox-archive-line me-1"></i>{{ $movement->reference->receipt_number }}
                                            </a>
                                        @elseif($movement->reference instanceof \App\Models\StockTransfer)
                                            <a href="{{ route('admin.stock-transfers.show', $movement->reference) }}" class="text-primary text-decoration-underline">
                                                <i class="ri-arrow-left-right-line me-1"></i>{{ $movement->reference->transfer_number }}
                                            </a>
                                        @elseif($movement->reference instanceof \App\Models\StockAdjustment)
                                            <a href="{{ route('admin.stock-adjustments.show', $movement->reference) }}" class="text-primary text-decoration-underline">
                                                <i class="ri-equalizer-line me-1"></i>{{ $movement->reference->adjustment_number }}
                                            </a>
                                        @else
                                            <span class="text-muted">{{ class_basename($movement->reference_type) }} #{{ $movement->reference_id }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $movement->createdBy?->name ?? 'النظام' }}</td>
                                    <td class="text-muted fs-12">{{ $movement->notes ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <div class="text-center py-5">
                                            <div class="avatar-md mx-auto mb-3">
                                                <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                                    <i class="ri-history-line"></i>
                                                </div>
                                            </div>
                                            <h5 class="fs-16 mb-1">لا توجد حركات مخزنية مطابقة</h5>
                                            <p class="text-muted mb-0">جرّب تغيير خيارات التصفية أو البحث عن صنف آخر.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($movements->hasPages())
                    <div class="d-flex justify-content-end mt-3">
                        {{ $movements->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
