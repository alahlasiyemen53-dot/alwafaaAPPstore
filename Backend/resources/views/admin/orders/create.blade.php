@extends('layouts.admin')

@section('title', 'إنشاء طلب مبيعات جديد | لمّة')

@section('content')
<form action="{{ route('admin.orders.store') }}" method="POST" id="order-form">
    @csrf
    <div class="row">
        <div class="col-lg-8">
            <!-- Customer & Fulfillment Card -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="ri-user-3-line me-1 text-primary"></i> بيانات العميل والتوصيل والمستودع
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">العميل</label>
                            <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                                <option value="">-- اختر العميل --</option>
                                @foreach($customers as $cust)
                                    <option value="{{ $cust->id }}" @selected(old('customer_id') == $cust->id)>
                                        {{ $cust->name }} ({{ $cust->phone }})
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div id="customer-info-box" class="mt-2 d-none p-2 rounded bg-light border">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fs-12 text-muted">حالة توثيق الهاتف:</span>
                                    <span id="phone-badge" class="badge"></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">عنوان التوصيل</label>
                            <select name="customer_address_id" id="customer_address_id" class="form-select @error('customer_address_id') is-invalid @enderror" required>
                                <option value="">-- اختر العميل أولاً لتحميل عناوينه --</option>
                            </select>
                            @error('customer_address_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">مستودع التنفيذ (حجز المخزون)</label>
                            <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                                <option value="">-- اختر المستودع --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('warehouse_id') == $wh->id)>{{ $wh->name_ar }}</option>
                                @endforeach
                            </select>
                            @error('warehouse_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">عملة الطلب</label>
                            <select name="currency_id" id="currency_id" class="form-select @error('currency_id') is-invalid @enderror" required>
                                @foreach($currencies as $curr)
                                    <option value="{{ $curr->id }}" @selected(old('currency_id', $baseCurrency->id) == $curr->id) data-symbol="{{ $curr->symbol ?? $curr->code }}">
                                        {{ $curr->name_ar }} ({{ $curr->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items Card -->
            <div class="card mb-3">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-list-check-2 me-1 text-primary"></i> بنود المنتجات والكميات المطلوبة
                    </h5>
                    <button type="button" class="btn btn-sm btn-primary" id="btn-add-item">
                        <i class="ri-add-line me-1"></i> إضافة منتج
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0" id="items-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 250px;">المنتج / المتغير (SKU)</th>
                                    <th style="width: 130px;">المخزون المتوفر</th>
                                    <th style="width: 120px;">الكمية</th>
                                    <th style="width: 140px;">سعر الوحدة</th>
                                    <th style="width: 140px;">الإجمالي</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="items-container">
                                <!-- Dynamic Item Rows -->
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
                    <label class="form-label">ملاحظات الطلب</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="أدخل أي ملاحظات خاصة بالتوصيل أو تجهيز الطلب...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <!-- Gift Wrapping Card (Sprint 21.11) -->
            @php $giftService = app(\App\Services\GiftWrappingService::class); @endphp
            <div class="card mb-3 @if(!$giftService->isEnabled()) bg-light-subtle @endif">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-gift-line me-1 text-primary"></i> خدمة تغليف الهدايا (Gift Wrapping)
                    </h5>
                    @if($giftService->isEnabled())
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                            خدمة مجانية متاحة (V1)
                        </span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                            معطلة في الإعدادات
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    @if($giftService->isEnabled())
                        <div class="form-check form-switch form-switch-md mb-3">
                            <input type="hidden" name="gift_wrapping_requested" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="gift_wrapping_requested" name="gift_wrapping_requested" value="1" @checked(old('gift_wrapping_requested', false)) onchange="document.getElementById('gift_message_container').style.display = this.checked ? 'block' : 'none';">
                            <label class="form-check-label fw-bold" for="gift_wrapping_requested">تغليف هذا الطلب كهدية (مجاناً)</label>
                        </div>

                        <div id="gift_message_container" style="display: {{ old('gift_wrapping_requested', false) ? 'block' : 'none' }};">
                            @if($giftService->isMessageEnabled())
                                <label for="gift_message" class="form-label fs-13 text-muted">بطاقة رسالة الهدية (الحد الأقصى {{ $giftService->getMaxMessageLength() }} حرف):</label>
                                <textarea name="gift_message" id="gift_message" class="form-control @error('gift_message') is-invalid @enderror" rows="2" maxlength="{{ $giftService->getMaxMessageLength() }}" placeholder="اكتب رسالة الهدية التي ستتم طباعتها على البطاقة المرفقة...">{{ old('gift_message') }}</textarea>
                                @error('gift_message')
                                    <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                                @enderror
                            @else
                                <div class="alert alert-secondary fs-12 mb-0 py-2">
                                    <i class="ri-information-line me-1"></i> كتابة بطاقات الرسائل معطلة حالياً في إعدادات النظام. سيتم تقديم التغليف بدون بطاقة رسالة.
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-muted fs-13 mb-0">
                            خدمة تغليف الهدايا معطلة حالياً من إعدادات النظام. يمكنك تفعيلها من <a href="{{ route('admin.master-data.settings') }}">شاشة الإعدادات</a>.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Order Summary Card -->
            <div class="card sticky-top" style="top: 80px;">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="ri-file-text-line me-1 text-primary"></i> ملخص الطلب
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">المجموع الفرعي:</span>
                        <span class="fw-semibold" id="summary-subtotal">0.00</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-13 text-muted">كود الكوبون (اختياري):</label>
                        <div class="input-group">
                            <input type="text" name="coupon_code" id="coupon_code" class="form-control text-uppercase font-monospace" placeholder="مثال: SAVE20" value="{{ old('coupon_code') }}">
                            <button class="btn btn-outline-primary" type="button" id="btn-validate-coupon">
                                <i class="ri-check-line"></i> فحص
                            </button>
                        </div>
                        <div id="coupon-feedback" class="form-text fs-12 mt-1"></div>
                    </div>
                    <div id="promotion-discount-badge-row" class="d-none d-flex justify-content-between text-info fs-13 mb-2">
                        <span><i class="ri-price-tag-3-line me-1"></i> خصم العروض الترويجية:</span>
                        <span class="fw-bold" id="promotion-discount-display">- 0.00</span>
                    </div>
                    <div id="bundle-discount-badge-row" class="d-none d-flex justify-content-between text-warning fs-13 mb-2">
                        <span><i class="ri-gift-line me-1"></i> خصم باقات العروض:</span>
                        <span class="fw-bold" id="bundle-discount-display">- 0.00</span>
                    </div>
                    <div id="coupon-discount-badge-row" class="d-none d-flex justify-content-between text-success fs-13 mb-2">
                        <span><i class="ri-coupon-3-line me-1"></i> خصم الكوبون المتوقع:</span>
                        <span class="fw-bold" id="coupon-discount-display">- 0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fs-16 fw-bold">إجمالي الطلب:</span>
                        <span class="fs-18 fw-bold text-primary" id="summary-total">0.00</span>
                    </div>

                    <div class="alert alert-info d-flex align-items-start gap-2 p-2 mb-3">
                        <i class="ri-information-line fs-18"></i>
                        <div class="fs-12">
                            <strong>ملاحظة هامة:</strong> تأكيد الطلب سيقوم مباشرة بحجز الكميات في المخزون المتاح، بينما الحفظ كمسودة لا يحجز أي كميات.
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="action" value="confirm" class="btn btn-success btn-lg" id="btn-submit-confirm">
                            <i class="ri-checkbox-circle-line me-1"></i> حفظ وتأكيد الطلب وحجز المخزون
                        </button>
                        <button type="submit" name="action" value="draft" class="btn btn-secondary" id="btn-submit-draft">
                            <i class="ri-save-line me-1"></i> حفظ كمسودة فقط
                        </button>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-light">
                            <i class="ri-close-line me-1"></i> إلغاء
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Hidden Variant Template for JS -->
<template id="item-row-template">
    <tr class="item-row">
        <td>
            <select name="items[INDEX][product_variant_id]" class="form-select variant-select" required>
                <option value="">-- اختر المتغير --</option>
                @foreach($variants as $var)
                    @php
                        $basePrice = $var->prices->firstWhere('currency_id', $baseCurrency->id)?->price ?? 0;
                        $attrs = $var->attributeValues->map(fn($v) => ($v->attribute?->name_ar ? $v->attribute->name_ar.': ' : '').$v->value_ar)->filter()->implode(', ');
                    @endphp
                    <option value="{{ $var->id }}" data-price="{{ $basePrice }}" data-sku="{{ $var->sku }}">
                        {{ $var->product?->name_ar }} ({{ $var->sku }}) {{ $attrs ? '['.$attrs.']' : '' }}
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <div class="stock-badge-container">
                <span class="badge bg-light text-muted border stock-badge">اختر المستودع</span>
            </div>
        </td>
        <td>
            <input type="number" step="0.0001" min="0.0001" name="items[INDEX][quantity]" class="form-control quantity-input" value="1" required>
        </td>
        <td>
            <input type="text" class="form-control bg-light unit-price-display" readonly value="0.00">
        </td>
        <td>
            <input type="text" class="form-control bg-light fw-bold line-total-display" readonly value="0.00">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-subtle-danger btn-remove-item">
                <i class="ri-delete-bin-line"></i>
            </button>
        </td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currencyRates = @json($currencyRates);
    let rowIndex = 0;
    const itemsContainer = document.getElementById('items-container');
    const template = document.getElementById('item-row-template');
    const customerSelect = document.getElementById('customer_id');
    const addressSelect = document.getElementById('customer_address_id');
    const warehouseSelect = document.getElementById('warehouse_id');
    const currencySelect = document.getElementById('currency_id');
    const customerInfoBox = document.getElementById('customer-info-box');
    const phoneBadge = document.getElementById('phone-badge');

    function getCurrentCurrency() {
        const currId = currencySelect.value;
        return currencyRates[currId] || { rate: 1.0, is_available: true, symbol: '', decimal_places: 2 };
    }

    function addItemRow() {
        const clone = template.content.cloneNode(true);
        const tr = clone.querySelector('tr');
        tr.innerHTML = tr.innerHTML.replace(/INDEX/g, rowIndex++);
        itemsContainer.appendChild(tr);
        bindRowEvents(tr);
        recalcTotals();
    }

    function bindRowEvents(row) {
        const variantSelect = row.querySelector('.variant-select');
        const quantityInput = row.querySelector('.quantity-input');
        const btnRemove = row.querySelector('.btn-remove-item');

        variantSelect.addEventListener('change', function() {
            updateRowPrice(row);
            checkRowStock(row);
            recalcTotals();
        });

        quantityInput.addEventListener('input', function() {
            updateRowPrice(row);
            recalcTotals();
        });

        btnRemove.addEventListener('click', function() {
            row.remove();
            recalcTotals();
        });
    }

    function updateRowPrice(row) {
        const variantSelect = row.querySelector('.variant-select');
        const selectedOpt = variantSelect.selectedOptions[0];
        const basePrice = selectedOpt ? parseFloat(selectedOpt.dataset.price || 0) : 0;
        const qty = parseFloat(row.querySelector('.quantity-input').value || 0);

        const curr = getCurrentCurrency();
        const convertedPrice = basePrice * curr.rate;
        const decimals = curr.decimal_places !== undefined ? curr.decimal_places : 2;

        row.querySelector('.unit-price-display').value = convertedPrice.toFixed(decimals);
        row.querySelector('.line-total-display').value = (convertedPrice * qty).toFixed(decimals);
    }

    function checkRowStock(row) {
        const warehouseId = warehouseSelect.value;
        const variantId = row.querySelector('.variant-select').value;
        const badge = row.querySelector('.stock-badge');

        if (!warehouseId || !variantId) {
            badge.className = 'badge bg-light text-muted border stock-badge';
            badge.textContent = warehouseId ? 'اختر المنتج' : 'اختر المستودع';
            return;
        }

        badge.className = 'badge bg-secondary-subtle text-secondary border stock-badge';
        badge.textContent = 'جاري الفحص...';

        fetch(`/admin/orders-data/variant-stock?warehouse_id=${warehouseId}&product_variant_id=${variantId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const avail = data.available_quantity;
                    if (avail <= 0) {
                        badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle stock-badge';
                        badge.textContent = `غير متوفر (0)`;
                    } else {
                        badge.className = 'badge bg-success-subtle text-success border border-success-subtle stock-badge';
                        badge.textContent = `متاح: ${avail}`;
                    }
                }
            })
            .catch(() => {
                badge.className = 'badge bg-light text-muted stock-badge';
                badge.textContent = 'تعذر الفحص';
            });
    }

    const couponInput = document.getElementById('coupon_code');
    const couponBtn = document.getElementById('btn-validate-coupon');
    const couponFeedback = document.getElementById('coupon-feedback');

    function buildPricingPayload() {
        const items = [];
        document.querySelectorAll('.item-row').forEach(row => {
            const variantId = row.querySelector('.variant-select')?.value;
            const qty = parseFloat(row.querySelector('.quantity-input')?.value || 0);
            if (variantId && qty > 0) {
                items.push({
                    product_variant_id: parseInt(variantId),
                    quantity: qty.toFixed(4),
                    unit_id: null,
                });
            }
        });

        return {
            customer_id: customerSelect.value ? parseInt(customerSelect.value) : null,
            currency_id: currencySelect.value ? parseInt(currencySelect.value) : null,
            coupon_code: couponInput && couponInput.value.trim() !== '' ? couponInput.value.trim() : null,
            items: items,
        };
    }

    function renderPricingPreview(data) {
        const curr = getCurrentCurrency();
        const symbol = curr.symbol ? ' ' + curr.symbol : '';
        const decimals = curr.decimal_places !== undefined ? curr.decimal_places : 2;

        document.getElementById('summary-subtotal').textContent = parseFloat(data.subtotal).toFixed(decimals) + symbol;
        document.getElementById('summary-total').textContent = parseFloat(data.total).toFixed(decimals) + symbol;

        const promoRow = document.getElementById('promotion-discount-badge-row');
        const promoDisplay = document.getElementById('promotion-discount-display');
        if (promoRow && promoDisplay) {
            const promoTotal = parseFloat(data.promotion_discount_total || 0);
            if (promoTotal > 0) {
                promoRow.classList.remove('d-none');
                promoDisplay.textContent = '- ' + promoTotal.toFixed(decimals);
            } else {
                promoRow.classList.add('d-none');
            }
        }

        const bundleRow = document.getElementById('bundle-discount-badge-row');
        const bundleDisplay = document.getElementById('bundle-discount-display');
        if (bundleRow && bundleDisplay) {
            const bundleTotal = parseFloat(data.bundle_discount_total || 0);
            if (bundleTotal > 0) {
                bundleRow.classList.remove('d-none');
                bundleDisplay.textContent = '- ' + bundleTotal.toFixed(decimals);
            } else {
                bundleRow.classList.add('d-none');
            }
        }

        const couponRow = document.getElementById('coupon-discount-badge-row');
        const couponDisplay = document.getElementById('coupon-discount-display');
        if (couponRow && couponDisplay) {
            const couponTotal = parseFloat(data.coupon_discount_total || 0);
            if (couponTotal > 0) {
                couponRow.classList.remove('d-none');
                couponDisplay.textContent = '- ' + couponTotal.toFixed(decimals);
            } else {
                couponRow.classList.add('d-none');
            }
        }
    }

    let pricingPreviewTimeout = null;

    async function requestPricingPreview(isExplicitCouponCheck = false) {
        const payload = buildPricingPayload();
        if (!payload.customer_id || !payload.currency_id || payload.items.length === 0) {
            if (isExplicitCouponCheck && couponFeedback) {
                couponFeedback.className = 'form-text fs-12 mt-1 text-danger';
                couponFeedback.textContent = 'يرجى اختيار العميل وإضافة بند واحد على الأقل لفحص الكوبون.';
            }
            return;
        }

        try {
            const response = await fetch(@json(route('admin.orders.pricing-preview')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error_message ?? 'تعذر احتساب تسعير الطلب.');
            }

            renderPricingPreview(data);

            if (payload.coupon_code) {
                if (parseFloat(data.coupon_discount_total || 0) > 0) {
                    if (couponFeedback) {
                        couponFeedback.className = 'form-text fs-12 mt-1 text-success';
                        couponFeedback.innerHTML = '<i class="ri-checkbox-circle-fill me-1"></i> كود الكوبون صالح ومطابق للشروط';
                    }
                } else if (isExplicitCouponCheck && couponFeedback) {
                    couponFeedback.className = 'form-text fs-12 mt-1 text-warning';
                    couponFeedback.innerHTML = '<i class="ri-information-line me-1"></i> الكوبون لم يحقق شروط الخصم للطلب';
                }
            } else if (couponFeedback) {
                couponFeedback.textContent = '';
            }
        } catch (err) {
            if (couponFeedback && (payload.coupon_code || isExplicitCouponCheck)) {
                couponFeedback.className = 'form-text fs-12 mt-1 text-danger';
                couponFeedback.innerHTML = '<i class="ri-close-circle-fill me-1"></i> ' + err.message;
            }
        }
    }

    if (couponBtn) couponBtn.addEventListener('click', () => requestPricingPreview(true));
    if (couponInput) couponInput.addEventListener('change', () => requestPricingPreview(false));

    function recalcTotals() {
        let subtotal = 0;
        document.querySelectorAll('.line-total-display').forEach(input => {
            subtotal += parseFloat(input.value || 0);
        });

        const curr = getCurrentCurrency();
        const decimals = curr.decimal_places !== undefined ? curr.decimal_places : 2;
        const total = subtotal;

        document.getElementById('summary-subtotal').textContent = subtotal.toFixed(decimals) + (curr.symbol ? ' ' + curr.symbol : '');
        document.getElementById('summary-total').textContent = total.toFixed(decimals) + (curr.symbol ? ' ' + curr.symbol : '');

        clearTimeout(pricingPreviewTimeout);
        pricingPreviewTimeout = setTimeout(() => {
            requestPricingPreview(false);
        }, 250);
    }

    // Update all prices and totals when currency selection changes
    currencySelect.addEventListener('change', function() {
        document.querySelectorAll('.item-row').forEach(row => updateRowPrice(row));
        recalcTotals();
    });

    // Customer selection change
    customerSelect.addEventListener('change', function() {
        const custId = this.value;
        addressSelect.innerHTML = '<option value="">-- جاري تحميل العناوين... --</option>';
        customerInfoBox.classList.add('d-none');

        if (!custId) {
            addressSelect.innerHTML = '<option value="">-- اختر العميل أولاً لتحميل عناوينه --</option>';
            return;
        }

        fetch(`/admin/orders-data/customer-addresses/${custId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update Phone Badge
                    customerInfoBox.classList.remove('d-none');
                    if (data.customer.phone_verified) {
                        phoneBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                        phoneBadge.innerHTML = '<i class="ri-checkbox-circle-line me-1"></i> موثق';
                    } else {
                        phoneBadge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle';
                        phoneBadge.innerHTML = '<i class="ri-alert-line me-1"></i> غير موثق';
                    }

                    // Populate addresses
                    addressSelect.innerHTML = '';
                    if (data.addresses.length === 0) {
                        addressSelect.innerHTML = '<option value="">لا توجد عناوين مسجلة لهذا العميل</option>';
                    } else {
                        data.addresses.forEach(addr => {
                            const opt = document.createElement('option');
                            opt.value = addr.id;
                            opt.textContent = `${addr.label}: ${addr.formatted} (${addr.contact_name} - ${addr.contact_phone})`;
                            if (addr.is_default) opt.selected = true;
                            addressSelect.appendChild(opt);
                        });
                    }
                }
            });
    });

    warehouseSelect.addEventListener('change', function() {
        document.querySelectorAll('.item-row').forEach(row => checkRowStock(row));
    });

    document.getElementById('btn-add-item').addEventListener('click', addItemRow);

    // Add initial item row
    addItemRow();

    // Prevent double submission
    const form = document.getElementById('order-form');
    form.addEventListener('submit', function(e) {
        const items = document.querySelectorAll('.item-row');
        if (items.length === 0) {
            e.preventDefault();
            alert('يرجى إضافة بند واحد على الأقل للطلب.');
            return;
        }
        document.getElementById('btn-submit-confirm').disabled = true;
        document.getElementById('btn-submit-draft').disabled = true;
    });
});
</script>
@endpush
