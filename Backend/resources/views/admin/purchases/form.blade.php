@extends('layouts.admin')
@section('title', ($invoice ? 'تعديل فاتورة شراء #'.$invoice->invoice_number : 'فاتورة شراء جديدة').' | الوفاء استور')
@section('page-title', $invoice ? 'تعديل فاتورة شراء' : 'فاتورة شراء جديدة')

@section('content')
<div class="row">
    <div class="col-12">
        <form method="POST" action="{{ $invoice ? route('admin.purchases.update', $invoice) : route('admin.purchases.store') }}" id="purchaseInvoiceForm">
            @csrf
            @if($invoice) @method('PUT') @endif

            <!-- Informational Alert for Credit Purchase -->
            <div class="alert alert-info d-flex align-items-center mb-3">
                <i class="ri-information-line fs-20 me-2"></i>
                <div>
                    <strong>قاعدة التعامل المالي:</strong> فواتير الشراء تعتبر <strong>آجلة بالكامل (Credit)</strong>. ترحيل الفاتورة يثبت التزاماً مالياً مباشراً في كشف حساب المورد بعملة الفاتورة دون أي تدفق دفع فوري.
                    @if($invoice && $invoice->isPosted())
                        <div class="mt-1 text-warning fw-bold">
                            <i class="ri-alert-line me-1"></i> تنبيه: هذه الفاتورة مرحّلة حالياً؛ حفظ أي تعديلات عليها سيقوم بتحديث قيد الالتزام المالي في دفتر أستاذ المورد تلقائياً ليتطابق مع الإجمالي الجديد.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Invoice Header Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="ri-file-text-line me-1 text-primary"></i> بيانات الفاتورة الأساسية</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="supplier_id" class="form-label required">المورد</label>
                            <select class="form-control @error('supplier_id') is-invalid @enderror" id="supplier_id" name="supplier_id" data-choices required>
                                <option value="">اختر المورد...</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}" @selected((string) old('supplier_id', $invoice?->supplier_id) === (string) $sup->id)>
                                        {{ $sup->name }} ({{ $sup->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="warehouse_id" class="form-label required">مستودع الاستلام</label>
                            <select class="form-control @error('warehouse_id') is-invalid @enderror" id="warehouse_id" name="warehouse_id" data-choices required>
                                <option value="">اختر المستودع...</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected((string) old('warehouse_id', $invoice?->warehouse_id) === (string) $wh->id)>
                                        {{ $wh->name_ar }} ({{ $wh->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="currency_id" class="form-label required">عملة الفاتورة</label>
                            <select class="form-control @error('currency_id') is-invalid @enderror" id="currency_id" name="currency_id" data-choices data-choices-search-false required>
                                @foreach($currencies as $curr)
                                    <option value="{{ $curr->id }}" @selected((string) old('currency_id', $invoice?->currency_id ?? 1) === (string) $curr->id) data-symbol="{{ $curr->symbol ?? $curr->code }}">
                                        {{ $curr->name_ar }} ({{ $curr->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="invoice_date" class="form-label required">تاريخ الفاتورة (يوم-شهر-سنة)</label>
                            <input type="text" class="form-control @error('invoice_date') is-invalid @enderror" id="invoice_date" name="invoice_date" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ old('invoice_date', $invoice?->invoice_date?->format('d-m-Y') ?? date('d-m-Y')) }}" placeholder="يوم-شهر-سنة" required>
                            @error('invoice_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="supplier_invoice_number" class="form-label">رقم فاتورة المورد (الورقية / المرجعية)</label>
                            <input type="text" class="form-control @error('supplier_invoice_number') is-invalid @enderror" id="supplier_invoice_number" name="supplier_invoice_number" value="{{ old('supplier_invoice_number', $invoice?->supplier_invoice_number) }}" dir="ltr" placeholder="مثال: INV-8921">
                            @error('supplier_invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="invoice_number" class="form-label">رقم الفاتورة في النظام</label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="{{ old('invoice_number', $invoice?->invoice_number) }}" placeholder="يتم التوليد تلقائياً (مثال: PI-2026-00001)" dir="ltr" {{ $invoice ? 'readonly' : '' }}>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">ملاحظات الفاتورة</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" placeholder="أي ملاحظات إضافية حول الشحنة أو الاستلام...">{{ old('notes', $invoice?->notes) }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Items Card -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="ri-list-check-2 me-1 text-primary"></i> بنود وأصناف الفاتورة</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-item-btn">
                        <i class="ri-add-line me-1"></i> إضافة صنف
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0" id="items-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 250px;">المنتج والمتغير (SKU) <span class="text-danger">*</span></th>
                                    <th style="width: 140px;">الوحدة <span class="text-danger">*</span></th>
                                    <th style="width: 120px;">الكمية المشراة <span class="text-danger">*</span></th>
                                    <th style="width: 110px;">كمية مجانية (بونص)</th>
                                    <th style="width: 140px;">سعر شراء الوحدة <span class="text-danger">*</span></th>
                                    <th style="width: 120px;">خصم البند</th>
                                    <th style="width: 140px;">إجمالي البند</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="items-tbody">
                                @php
                                    $oldItems = old('items', $invoice?->items->toArray() ?? []);
                                    if (empty($oldItems)) {
                                        $oldItems = [['product_variant_id' => '', 'unit_id' => '', 'quantity' => 1, 'free_quantity' => 0, 'unit_cost' => 0, 'discount_amount' => 0]];
                                    }
                                @endphp

                                @foreach($oldItems as $index => $item)
                                    <tr class="item-row" data-index="{{ $index }}">
                                        <td>
                                            <select class="form-select form-select-sm variant-select" name="items[{{ $index }}][product_variant_id]" required>
                                                <option value="">اختر المنتج / المتغير...</option>
                                                @foreach($variants as $variant)
                                                    @php
                                                        $attrLabel = $variant->attributeValues->map(fn($v) => $v->attribute?->name_ar.': '.$v->value_ar)->join(' | ');
                                                    @endphp
                                                    <option value="{{ $variant->id }}" @selected((string) ($item['product_variant_id'] ?? '') === (string) $variant->id)>
                                                        {{ $variant->product?->name_ar }} ({{ $variant->sku }}) {{ $attrLabel ? "[$attrLabel]" : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="items[{{ $index }}][unit_id]" required>
                                                @foreach($units as $u)
                                                    <option value="{{ $u->id }}" @selected((string) ($item['unit_id'] ?? '') === (string) $u->id)>
                                                        {{ $u->name_ar }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0.0001" class="form-control form-control-sm text-center qty-input" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" required>
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0" class="form-control form-control-sm text-center free-qty-input" name="items[{{ $index }}][free_quantity]" value="{{ $item['free_quantity'] ?? 0 }}">
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0" class="form-control form-control-sm text-center cost-input" name="items[{{ $index }}][unit_cost]" value="{{ $item['unit_cost'] ?? 0 }}" required>
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0" class="form-control form-control-sm text-center discount-input" name="items[{{ $index }}][discount_amount]" value="{{ $item['discount_amount'] ?? 0 }}">
                                        </td>
                                        <td class="text-end fw-bold text-dark line-total-cell" dir="ltr">
                                            0.00
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-ghost-danger remove-item-btn" title="حذف السطر">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Totals & Summary Card -->
            <div class="row justify-content-end">
                <div class="col-lg-5 col-md-7">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0"><i class="ri-calculator-line me-1 text-primary"></i> ملخص مجاميع الفاتورة</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">المجموع قبل الخصم (Subtotal):</span>
                                <span class="fw-semibold" id="summary-subtotal" dir="ltr">0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">خصومات البنود:</span>
                                <span class="fw-semibold text-danger" id="summary-line-discounts" dir="ltr">- 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="discount_amount" class="text-muted mb-0">خصم إضافي على الفاتورة:</label>
                                <div style="width: 140px;">
                                    <input type="number" step="any" min="0" class="form-control form-control-sm text-end" id="discount_amount" name="discount_amount" value="{{ old('discount_amount', $invoice?->discount_amount ?? 0) }}" dir="ltr">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="other_costs" class="text-muted mb-0">تكاليف إضافية (شحن/نقل):</label>
                                <div style="width: 140px;">
                                    <input type="number" step="any" min="0" class="form-control form-control-sm text-end" id="other_costs" name="other_costs" value="{{ old('other_costs', $invoice?->other_costs ?? 0) }}" dir="ltr">
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold fs-15 text-dark">صافي إجمالي الفاتورة:</span>
                                <span class="fw-bold fs-17 text-primary" id="summary-total" dir="ltr">0.00</span>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <a href="{{ $invoice ? route('admin.purchases.show', $invoice) : route('admin.purchases.index') }}" class="btn btn-light">إلغاء</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="ri-save-line me-1"></i> {{ $invoice ? 'حفظ تعديلات الفاتورة' : 'حفظ الفاتورة كمسودة' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tbody = document.getElementById('items-tbody');
        const addBtn = document.getElementById('add-item-btn');
        const discountInput = document.getElementById('discount_amount');
        const otherCostsInput = document.getElementById('other_costs');

        function recalculate() {
            let subtotal = 0;
            let lineDiscounts = 0;

            const rows = tbody.querySelectorAll('.item-row');
            rows.forEach(function (row) {
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
                const disc = parseFloat(row.querySelector('.discount-input').value) || 0;

                const lineSub = qty * cost;
                const actualDisc = Math.min(lineSub, disc);
                const lineTot = Math.max(0, lineSub - actualDisc);

                subtotal += lineSub;
                lineDiscounts += actualDisc;

                row.querySelector('.line-total-cell').textContent = lineTot.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            });

            const invoiceDiscount = parseFloat(discountInput.value) || 0;
            const otherCosts = parseFloat(otherCostsInput.value) || 0;
            const grandTotal = Math.max(0, subtotal - lineDiscounts - invoiceDiscount + otherCosts);

            document.getElementById('summary-subtotal').textContent = subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('summary-line-discounts').textContent = '- ' + lineDiscounts.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('summary-total').textContent = grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // Event delegation for table inputs
        tbody.addEventListener('input', function (e) {
            if (e.target.matches('.qty-input, .cost-input, .discount-input')) {
                recalculate();
            }
        });

        tbody.addEventListener('click', function (e) {
            const btn = e.target.closest('.remove-item-btn');
            if (btn) {
                const rows = tbody.querySelectorAll('.item-row');
                if (rows.length > 1) {
                    btn.closest('.item-row').remove();
                    recalculate();
                } else {
                    alert('يجب أن تحتوي الفاتورة على سطر صنف واحد على الأقل.');
                }
            }
        });

        addBtn.addEventListener('click', function () {
            const rows = tbody.querySelectorAll('.item-row');
            const newIndex = rows.length ? parseInt(rows[rows.length - 1].getAttribute('data-index')) + 1 : 0;
            const firstRow = rows[0];
            const clone = firstRow.cloneNode(true);

            clone.setAttribute('data-index', newIndex);
            clone.querySelectorAll('input, select').forEach(function (input) {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/\[\d+\]/, '[' + newIndex + ']'));
                }
                if (input.classList.contains('qty-input')) input.value = '1';
                else if (input.classList.contains('free-qty-input')) input.value = '0';
                else if (input.classList.contains('cost-input')) input.value = '0';
                else if (input.classList.contains('discount-input')) input.value = '0';
                else if (input.tagName === 'SELECT') input.selectedIndex = 0;
            });

            clone.querySelector('.line-total-cell').textContent = '0.00';
            tbody.appendChild(clone);
            recalculate();
        });

        discountInput.addEventListener('input', recalculate);
        otherCostsInput.addEventListener('input', recalculate);

        // Initial calculation
        recalculate();
    });
</script>
@endpush
