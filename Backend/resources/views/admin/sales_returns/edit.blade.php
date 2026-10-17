@extends('layouts.admin')

@section('title', 'تعديل مسودة مرتجع المبيعات | لمّة')

@section('content')
<form action="{{ route('admin.sales-returns.update', $salesReturn) }}" method="POST" id="sales-return-form">
    @csrf
    @method('PUT')
    <div class="row">
        <div class="col-lg-8">
            <!-- Header Info Card -->
            <div class="card mb-3">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-edit-line me-1 text-primary"></i> تعديل مسودة المرتجع: {{ $salesReturn->return_number }}
                    </h5>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">مسودة</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">الطلب الأصلي</label>
                            <input type="text" class="form-control bg-light" readonly value="{{ $salesReturn->order?->order_number }} - {{ $salesReturn->order?->customer_name_snapshot ?? $salesReturn->order?->customer?->name }}">
                            <input type="hidden" name="order_id" id="order_id" value="{{ $salesReturn->order_id }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">مستودع الاستلام الفعلي</label>
                            <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('warehouse_id', $salesReturn->warehouse_id) == $wh->id)>{{ $wh->name_ar }}</option>
                                @endforeach
                            </select>
                            @error('warehouse_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">سبب الإرجاع الرئيسي</label>
                            <select name="reason_code" id="reason_code" class="form-select @error('reason_code') is-invalid @enderror" required>
                                @foreach($reasons as $reason)
                                    <option value="{{ $reason->value }}" @selected(old('reason_code', $salesReturn->reason_code?->value) === $reason->value)>
                                        {{ $reason->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('reason_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <div class="p-2 rounded bg-light border h-100">
                                <div class="fs-12 text-muted mb-1">العميل:</div>
                                <div class="fw-semibold fs-13 text-dark">{{ $salesReturn->order?->customer_name_snapshot ?? $salesReturn->order?->customer?->name }}</div>
                                <div class="fs-12 text-muted">العملة: <span class="fw-medium text-dark">{{ $salesReturn->currency?->symbol ?? $salesReturn->currency?->code }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Return Items Card -->
            <div class="card mb-3">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-list-check-2 me-1 text-primary"></i> بنود المرتجع
                    </h5>
                    <span class="badge bg-secondary-subtle text-secondary" id="items-count-badge">{{ $salesReturn->items->count() }} بنود</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" id="items-table-container">
                        <table class="table table-bordered align-middle mb-0" id="items-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th>الصنف / المتغير (SKU)</th>
                                    <th style="width: 130px;" class="text-center">كمية المرتجع</th>
                                    <th style="width: 140px;">سعر الوحدة</th>
                                    <th style="width: 140px;">قيمة السطر</th>
                                </tr>
                            </thead>
                            <tbody id="items-container">
                                @foreach($salesReturn->items as $index => $item)
                                    <tr class="item-row">
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input item-check" checked>
                                            <input type="hidden" name="items[{{ $index }}][order_item_id]" value="{{ $item->order_item_id }}">
                                            <input type="hidden" name="items[{{ $index }}][product_variant_id]" value="{{ $item->product_variant_id }}">
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $item->product_name_snapshot }}</div>
                                            <small class="text-muted">{{ $item->sku_snapshot }} {{ $item->variant_name_snapshot ? '['.$item->variant_name_snapshot.']' : '' }}</small>
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0.0001"
                                                name="items[{{ $index }}][return_quantity]"
                                                class="form-control form-control-sm return-quantity-input"
                                                value="{{ (float) $item->return_quantity }}"
                                                data-price="{{ (float) $item->unit_price_snapshot }}">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm bg-light unit-price-display" readonly value="{{ number_format((float) $item->unit_price_snapshot, 2) }}">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm bg-light fw-bold line-total-display" readonly value="{{ number_format((float) $item->line_total, 2) }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('items')
                        <div class="p-3 text-danger fs-13"><i class="ri-error-warning-line me-1"></i> {{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Notes Card -->
            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label">ملاحظات المرتجع</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="أدخل أي ملاحظات خاصة بالبضاعة المرتجعة...">{{ old('notes', $salesReturn->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Return Summary Card -->
            <div class="card sticky-top" style="top: 80px;">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="ri-file-text-line me-1 text-primary"></i> ملخص المرتجع
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">المجموع الفرعي:</span>
                        <span class="fw-semibold" id="summary-subtotal">{{ number_format((float) $salesReturn->subtotal, 2) }} {{ $salesReturn->currency?->symbol ?? $salesReturn->currency?->code }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fs-16 fw-bold">إجمالي قيمة المرتجع:</span>
                        <span class="fs-18 fw-bold text-primary" id="summary-total">{{ number_format((float) $salesReturn->total, 2) }} {{ $salesReturn->currency?->symbol ?? $salesReturn->currency?->code }}</span>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg" id="btn-submit">
                            <i class="ri-save-line me-1"></i> حفظ تعديلات المسودة
                        </button>
                        <a href="{{ route('admin.sales-returns.show', $salesReturn) }}" class="btn btn-light">
                            <i class="ri-close-line me-1"></i> إلغاء
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currencySymbol = "{{ $salesReturn->currency?->symbol ?? $salesReturn->currency?->code }}";
    const summarySubtotal = document.getElementById('summary-subtotal');
    const summaryTotal = document.getElementById('summary-total');
    const btnSubmit = document.getElementById('btn-submit');

    function bindRowEvents(row) {
        const checkbox = row.querySelector('.item-check');
        const qtyInput = row.querySelector('.return-quantity-input');

        checkbox.addEventListener('change', function() {
            qtyInput.disabled = !this.checked;
            updateRowPrice(row);
            recalcTotals();
        });

        qtyInput.addEventListener('input', function() {
            updateRowPrice(row);
            recalcTotals();
        });
    }

    function updateRowPrice(row) {
        const checkbox = row.querySelector('.item-check');
        const qtyInput = row.querySelector('.return-quantity-input');
        const lineTotalDisplay = row.querySelector('.line-total-display');

        if (!checkbox.checked || qtyInput.disabled) {
            lineTotalDisplay.value = '0.00';
            return;
        }

        const price = parseFloat(qtyInput.dataset.price || 0);
        const qty = parseFloat(qtyInput.value || 0);
        lineTotalDisplay.value = (price * qty).toFixed(2);
    }

    function recalcTotals() {
        let subtotal = 0;
        let checkedCount = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            const checkbox = row.querySelector('.item-check');
            const qtyInput = row.querySelector('.return-quantity-input');
            if (checkbox.checked && !qtyInput.disabled) {
                const price = parseFloat(qtyInput.dataset.price || 0);
                const qty = parseFloat(qtyInput.value || 0);
                subtotal += (price * qty);
                checkedCount++;
            }
        });

        summarySubtotal.textContent = subtotal.toFixed(2) + ' ' + currencySymbol;
        summaryTotal.textContent = subtotal.toFixed(2) + ' ' + currencySymbol;

        btnSubmit.disabled = (checkedCount === 0 || subtotal <= 0);
    }

    document.querySelectorAll('.item-row').forEach(row => {
        bindRowEvents(row);
        updateRowPrice(row);
    });

    recalcTotals();
});
</script>
@endpush
