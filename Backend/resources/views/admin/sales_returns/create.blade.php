@extends('layouts.admin')

@section('title', 'إنشاء مرتجع مبيعات جديد | لمّة')

@section('content')
<form action="{{ route('admin.sales-returns.store') }}" method="POST" id="sales-return-form">
    @csrf
    <div class="row">
        <div class="col-lg-8">
            <!-- Header Info Card -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="ri-file-list-3-line me-1 text-primary"></i> بيانات الطلب الأصلي والاستلام
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">الطلب الأصلي المراد الإرجاع منه</label>
                            <select name="order_id" id="order_id" class="form-select @error('order_id') is-invalid @enderror" required>
                                <option value="">-- اختر طلب المبيعات --</option>
                                @foreach($orders as $ord)
                                    <option value="{{ $ord->id }}" @selected(old('order_id', $selectedOrderId) == $ord->id)>
                                        {{ $ord->order_number }} - {{ $ord->customer_name_snapshot ?? $ord->customer?->name }} ({{ $ord->total }} {{ $ord->currency?->symbol ?? $ord->currency?->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('order_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div id="order-loading-indicator" class="fs-12 text-primary mt-1 d-none">
                                <i class="ri-loader-4-line ri-spin me-1"></i> جاري تحميل بنود الطلب وفحص الكميات المنصرفة...
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">مستودع الاستلام الفعلي</label>
                            <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                                <option value="">-- اختر مستودع الاستلام --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('warehouse_id') == $wh->id)>{{ $wh->name_ar }}</option>
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
                                    <option value="{{ $reason->value }}" @selected(old('reason_code') === $reason->value)>
                                        {{ $reason->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('reason_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <div id="order-info-box" class="p-2 rounded bg-light border d-none h-100">
                                <div class="fs-12 text-muted mb-1">بيانات الطلب المختار:</div>
                                <div class="fw-semibold fs-13 text-dark" id="info-customer-name">—</div>
                                <div class="fs-12 text-muted">العملة: <span id="info-currency-symbol" class="fw-medium text-dark">—</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Return Items Card -->
            <div class="card mb-3">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-list-check-2 me-1 text-primary"></i> بنود الطلب والكميات المراد إرجاعها
                    </h5>
                    <span class="badge bg-secondary-subtle text-secondary" id="items-count-badge">0 بنود متاحة</span>
                </div>
                <div class="card-body p-0">
                    <div id="no-order-alert" class="p-4 text-center text-muted">
                        <i class="ri-arrow-up-circle-line fs-32 text-primary d-block mb-2"></i>
                        يرجى اختيار الطلب الأصلي أولاً لتحميل أصنافه والكميات المنصرفة المتاحة للإرجاع.
                    </div>

                    <div id="no-returnable-alert" class="p-4 text-center text-danger d-none">
                        <i class="ri-error-warning-line fs-32 text-danger d-block mb-2"></i>
                        <strong>تنبيه:</strong> هذا الطلب ليس له أي كميات منصرفة فعلياً من المخزون (أو تم إرجاعها بالكامل سابقاً).<br>
                        <span class="fs-12 text-muted">الطلبات التي تم تأكيدها فقط ولم تخرج بضاعتها فعلياً من المستودع لا يمكن عمل مرتجع مخزني عليها.</span>
                    </div>

                    <div class="table-responsive d-none" id="items-table-container">
                        <table class="table table-bordered align-middle mb-0" id="items-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th>الصنف / المتغير (SKU)</th>
                                    <th style="width: 100px;" class="text-center">المنصرف</th>
                                    <th style="width: 100px;" class="text-center">المرتجع سابقاً</th>
                                    <th style="width: 110px;" class="text-center">المتبقي للإرجاع</th>
                                    <th style="width: 120px;" class="text-center">كمية المرتجع</th>
                                    <th style="width: 120px;">سعر الوحدة</th>
                                    <th style="width: 120px;">قيمة السطر</th>
                                </tr>
                            </thead>
                            <tbody id="items-container">
                                <!-- Loaded dynamically via JS -->
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
                    <textarea name="notes" class="form-control" rows="3" placeholder="أدخل أي ملاحظات خاصة بالبضاعة المرتجعة أو أسباب فحصها...">{{ old('notes') }}</textarea>
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
                        <span class="fw-semibold" id="summary-subtotal">0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fs-16 fw-bold">إجمالي قيمة المرتجع:</span>
                        <span class="fs-18 fw-bold text-primary" id="summary-total">0.00</span>
                    </div>

                    <div class="alert alert-info d-flex align-items-start gap-2 p-2 mb-3">
                        <i class="ri-information-line fs-18"></i>
                        <div class="fs-12">
                            <strong>الأثر المخزني:</strong> عند <u>الاعتماد والاستلام</u>، سيتم فوراً إضافة الكميات المرتجعة إلى المخزون الفعلي للمستودع المحدد عبر حركة واردة <code>SALE_RETURN</code>، بينما الحفظ كمسودة لا يغير أي أرصدة.
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="action" value="post" class="btn btn-success btn-lg" id="btn-submit-post" disabled>
                            <i class="ri-checkbox-circle-line me-1"></i> حفظ واستلام واعتماد المرتجع فوراً
                        </button>
                        <button type="submit" name="action" value="draft" class="btn btn-secondary" id="btn-submit-draft" disabled>
                            <i class="ri-save-line me-1"></i> حفظ كمسودة فقط
                        </button>
                        <a href="{{ route('admin.sales-returns.index') }}" class="btn btn-light">
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
    const orderSelect = document.getElementById('order_id');
    const warehouseSelect = document.getElementById('warehouse_id');
    const loadingIndicator = document.getElementById('order-loading-indicator');
    const orderInfoBox = document.getElementById('order-info-box');
    const infoCustomerName = document.getElementById('info-customer-name');
    const infoCurrencySymbol = document.getElementById('info-currency-symbol');
    const noOrderAlert = document.getElementById('no-order-alert');
    const noReturnableAlert = document.getElementById('no-returnable-alert');
    const itemsTableContainer = document.getElementById('items-table-container');
    const itemsContainer = document.getElementById('items-container');
    const itemsCountBadge = document.getElementById('items-count-badge');
    const summarySubtotal = document.getElementById('summary-subtotal');
    const summaryTotal = document.getElementById('summary-total');
    const btnSubmitPost = document.getElementById('btn-submit-post');
    const btnSubmitDraft = document.getElementById('btn-submit-draft');

    let currentCurrencySymbol = '';

    function loadOrderData(orderId) {
        if (!orderId) {
            noOrderAlert.classList.remove('d-none');
            noReturnableAlert.classList.add('d-none');
            itemsTableContainer.classList.add('d-none');
            orderInfoBox.classList.add('d-none');
            btnSubmitPost.disabled = true;
            btnSubmitDraft.disabled = true;
            itemsContainer.innerHTML = '';
            recalcTotals();
            return;
        }

        loadingIndicator.classList.remove('d-none');

        fetch(`/admin/sales-returns-data/order-items/${orderId}`)
            .then(res => res.json())
            .then(data => {
                loadingIndicator.classList.add('d-none');
                if (data.success) {
                    currentCurrencySymbol = data.order.currency_symbol || '';
                    infoCustomerName.textContent = data.order.customer_name || 'عميل';
                    infoCurrencySymbol.textContent = currentCurrencySymbol;
                    orderInfoBox.classList.remove('d-none');

                    // Default warehouse to order's warehouse if not selected
                    if (!warehouseSelect.value && data.order.warehouse_id) {
                        warehouseSelect.value = data.order.warehouse_id;
                    }

                    if (data.items.length === 0 || data.order.total_returnable <= 0) {
                        noOrderAlert.classList.add('d-none');
                        noReturnableAlert.classList.remove('d-none');
                        itemsTableContainer.classList.add('d-none');
                        itemsContainer.innerHTML = '';
                        btnSubmitPost.disabled = true;
                        btnSubmitDraft.disabled = true;
                        itemsCountBadge.textContent = '0 بنود قابلة للإرجاع';
                    } else {
                        noOrderAlert.classList.add('d-none');
                        noReturnableAlert.classList.add('d-none');
                        itemsTableContainer.classList.remove('d-none');
                        renderItems(data.items);
                        btnSubmitPost.disabled = false;
                        btnSubmitDraft.disabled = false;
                    }
                }
            })
            .catch(() => {
                loadingIndicator.classList.add('d-none');
                alert('حدث خطأ أثناء تحميل بيانات الطلب.');
            });
    }

    function renderItems(items) {
        itemsContainer.innerHTML = '';
        let returnableCount = 0;

        items.forEach((item, index) => {
            if (item.returnable_quantity > 0) returnableCount++;

            const tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML = `
                <td class="text-center">
                    <input type="checkbox" class="form-check-input item-check" ${item.returnable_quantity > 0 ? 'checked' : 'disabled'}>
                    <input type="hidden" name="items[${index}][order_item_id]" value="${item.order_item_id}">
                    <input type="hidden" name="items[${index}][product_variant_id]" value="${item.product_variant_id}">
                </td>
                <td>
                    <div class="fw-semibold text-dark">${item.product_name}</div>
                    <small class="text-muted">${item.sku} ${item.variant_name ? '['+item.variant_name+']' : ''}</small>
                </td>
                <td class="text-center"><span class="badge bg-light text-dark border">${item.issued_quantity}</span></td>
                <td class="text-center"><span class="badge bg-light text-muted border">${item.already_returned_quantity}</span></td>
                <td class="text-center"><span class="badge bg-primary-subtle text-primary border border-primary-subtle">${item.returnable_quantity}</span></td>
                <td>
                    <input type="number" step="0.0001" min="0.0001" max="${item.returnable_quantity}"
                        name="items[${index}][return_quantity]"
                        class="form-control form-control-sm return-quantity-input"
                        value="${item.returnable_quantity > 0 ? item.returnable_quantity : 0}"
                        data-price="${item.unit_price}"
                        data-max="${item.returnable_quantity}"
                        ${item.returnable_quantity <= 0 ? 'disabled' : ''}>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm bg-light unit-price-display" readonly value="${item.unit_price.toFixed(2)}">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm bg-light fw-bold line-total-display" readonly value="0.00">
                </td>
            `;

            itemsContainer.appendChild(tr);
            bindRowEvents(tr);
            updateRowPrice(tr);
        });

        itemsCountBadge.textContent = `${returnableCount} بنود قابلة للإرجاع`;
        recalcTotals();
    }

    function bindRowEvents(row) {
        const checkbox = row.querySelector('.item-check');
        const qtyInput = row.querySelector('.return-quantity-input');

        checkbox.addEventListener('change', function() {
            qtyInput.disabled = !this.checked;
            updateRowPrice(row);
            recalcTotals();
        });

        qtyInput.addEventListener('input', function() {
            const max = parseFloat(this.dataset.max || 0);
            const val = parseFloat(this.value || 0);
            if (val > max) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
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
        const total = price * qty;
        lineTotalDisplay.value = total.toFixed(2);
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

        summarySubtotal.textContent = subtotal.toFixed(2) + ' ' + currentCurrencySymbol;
        summaryTotal.textContent = subtotal.toFixed(2) + ' ' + currentCurrencySymbol;

        const canSubmit = checkedCount > 0 && subtotal > 0;
        btnSubmitPost.disabled = !canSubmit;
        btnSubmitDraft.disabled = !canSubmit;
    }

    orderSelect.addEventListener('change', function() {
        loadOrderData(this.value);
    });

    if (orderSelect.value) {
        loadOrderData(orderSelect.value);
    }

    // Double submit prevention
    document.getElementById('sales-return-form').addEventListener('submit', function() {
        btnSubmitPost.disabled = true;
        btnSubmitDraft.disabled = true;
    });
});
</script>
@endpush
