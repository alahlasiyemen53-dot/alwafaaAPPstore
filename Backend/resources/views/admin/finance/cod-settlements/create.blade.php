@extends('layouts.admin')
@section('title', 'إنشاء تسوية عهدة COD جديدة | الوفاء استور')
@section('page-title', 'إنشاء تسوية عهدة نقدية لمندوب')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">بيانات تسوية التحصيل النقدي (COD)</h5>
                <a href="{{ route('admin.finance.cod-settlements.index') }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                </a>
            </div>
            <div class="card-body">
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="ri-error-warning-line me-1"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form id="settlementForm" method="POST" action="{{ route('admin.finance.cod-settlements.store') }}">
                    @csrf
                    <input type="hidden" name="action_type" id="action_type" value="draft">

                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label required">المندوب</label>
                            <select name="driver_id" id="driver_id" class="form-select @error('driver_id') is-invalid @enderror" required>
                                <option value="">-- اختر المندوب --</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" {{ (old('driver_id', $preselectedDriverId) == $driver->id) ? 'selected' : '' }}>
                                        {{ $driver->name }} ({{ $driver->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('driver_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label required">عملة التسوية</label>
                            <select name="currency_id" id="currency_id" class="form-select @error('currency_id') is-invalid @enderror" required>
                                <option value="">-- اختر العملة --</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}" {{ (old('currency_id', $preselectedCurrencyId) == $currency->id) ? 'selected' : '' }}>
                                        {{ $currency->name }} ({{ $currency->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label required">نوع حساب الخزينة المستلمة</label>
                            <select name="treasury_account_type" id="treasury_account_type" class="form-select @error('treasury_account_type') is-invalid @enderror" required>
                                <option value="">-- اختر نوع الحساب --</option>
                                <option value="cashbox" {{ old('treasury_account_type') == 'cashbox' ? 'selected' : '' }}>صندوق كاش (خزينة نقدية)</option>
                                <option value="bank_account" {{ old('treasury_account_type') == 'bank_account' ? 'selected' : '' }}>حساب بنكي</option>
                                <option value="electronic_wallet" {{ old('treasury_account_type') == 'electronic_wallet' ? 'selected' : '' }}>محفظة إلكترونية</option>
                            </select>
                            @error('treasury_account_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label required">الحساب المالي المستلم</label>
                            <select name="treasury_account_id" id="treasury_account_id" class="form-select @error('treasury_account_id') is-invalid @enderror" required>
                                <option value="">-- اختر الحساب أولاً --</option>
                            </select>
                            @error('treasury_account_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">ملاحظات التسوية</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات توضيحية حول توريد العهدة أو رقم إيصال الإيداع...">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <!-- Driver COD Outstanding Stats Banner -->
                    <div id="driverStatsBanner" class="alert alert-subtle-primary d-none mb-4" role="alert">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3">
                                    <span class="avatar-title bg-primary rounded-circle fs-18">
                                        <i class="ri-user-shared-line"></i>
                                    </span>
                                </div>
                                <div>
                                    <h6 class="alert-heading mb-1" id="statDriverName">ملخص عهدة المندوب</h6>
                                    <div class="text-muted fs-13">
                                        إجمالي المحصل: <span class="fw-bold text-dark" id="statTotalCollected">0.00</span> | 
                                        المسوى سابقاً: <span class="fw-bold text-success" id="statTotalSettled">0.00</span> | 
                                        <span class="text-danger fw-bold">المتبقي المستحق: <span id="statTotalOutstanding">0.00</span></span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span class="badge bg-primary-subtle text-primary fs-12" id="statEligibleCount">0 شحنة مؤهلة</span>
                            </div>
                        </div>
                    </div>

                    <!-- Eligible Deliveries Section -->
                    <div class="border rounded p-3 mb-4 bg-light-subtle">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="fw-bold mb-0">
                                <i class="ri-truck-line me-1 text-primary"></i> الشحنات المؤهلة للتسوية المالية
                            </h6>
                            <div class="d-flex gap-2 align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelectAll">
                                    <i class="ri-checkbox-line me-1"></i> تحديد الكل
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDeselectAll">
                                    <i class="ri-checkbox-blank-line me-1"></i> إلغاء التحديد
                                </button>
                            </div>
                        </div>

                        <div id="deliveriesLoading" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                            <p class="text-muted mt-2 mb-0">جاري جلب الشحنات المؤهلة للتسوية من واقع التسليمات...</p>
                        </div>

                        <div id="noDeliveriesAlert" class="alert alert-info d-none mb-0">
                            <i class="ri-information-line me-1"></i> لا توجد شحنات مؤهلة للتسوية لهذا المندوب بالعملة المحددة (جميع الشحنات مسواة أو لا يوجد تسليمات COD ناجحة).
                        </div>

                        <div class="table-responsive" id="deliveriesTableContainer">
                            <table class="table table-bordered table-hover align-middle mb-0 bg-white">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th>رقم الشحنة</th>
                                        <th>رقم الطلب</th>
                                        <th>العميل</th>
                                        <th>تاريخ التسليم</th>
                                        <th>المحصل الفعلي</th>
                                        <th>مسوى سابقاً</th>
                                        <th>المستحق المتبقي</th>
                                        <th style="width: 220px;">المبلغ المسوى بهذه العملية</th>
                                    </tr>
                                </thead>
                                <tbody id="deliveriesTableBody">
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            يرجى اختيار المندوب والعملة لعرض الشحنات المؤهلة.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary & Submission Bar -->
                    <div class="row align-items-center justify-content-between p-3 bg-white border rounded">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <div class="fs-14">
                                <span>عدد الشحنات المحددة: <strong class="text-primary" id="summarySelectedCount">0</strong></span>
                                <span class="mx-2">|</span>
                                <span>إجمالي المبلغ المسوى: <strong class="text-success fs-16" id="summaryTotalSettled">0.00</strong></span>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end d-flex gap-2 justify-content-md-end">
                            <button type="button" class="btn btn-outline-primary" id="btnSaveDraft">
                                <i class="ri-save-line me-1"></i> حفظ كمسودة (بدون أثر مالي)
                            </button>
                            <button type="button" class="btn btn-success" id="btnPostDirectly">
                                <i class="ri-check-double-line me-1"></i> اعتماد وترحيل مالي فوري
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Posting Confirmation Modal -->
<div class="modal fade" id="postingConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success-subtle">
                <h5 class="modal-title text-success"><i class="ri-shield-check-line me-1"></i> تأكيد الاعتماد والترحيل المالي</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fs-14 mb-2">سيتم اعتماد التسوية ماليًا وإيداع المبلغ في الحساب المالي المحدد، وسيتم تخفيض المبلغ المستحق على المندوب بالقيم المحددة، وإنشاء قيد محاسبي متوازن في الأستاذ العام.</p>
                <div class="alert alert-warning py-2 mb-3 fs-13">
                    <i class="ri-alert-line me-1"></i> بعد الترحيل لا يمكن تعديل التسوية مباشرة، وأي تعديل يتطلب إجراء إلغاء/عكس معتمد.
                </div>
                <div class="bg-light p-3 rounded">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">المندوب:</span>
                        <span class="fw-semibold" id="modalDriverName">-</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">الخزينة المستلمة:</span>
                        <span class="fw-semibold" id="modalTreasuryName">-</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">إجمالي التوريد:</span>
                        <span class="fw-bold text-success fs-16" id="modalTotalAmount">0.00</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" id="btnConfirmPostingSubmit">تأكيد الترحيل الآن</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const treasuryAccountsData = {
        cashbox: @json($cashboxes),
        bank_account: @json($bankAccounts),
        electronic_wallet: @json($electronicWallets)
    };

    const driverSelect = document.getElementById('driver_id');
    const currencySelect = document.getElementById('currency_id');
    const treasuryTypeSelect = document.getElementById('treasury_account_type');
    const treasuryAccountSelect = document.getElementById('treasury_account_id');
    const deliveriesTableBody = document.getElementById('deliveriesTableBody');
    const deliveriesLoading = document.getElementById('deliveriesLoading');
    const noDeliveriesAlert = document.getElementById('noDeliveriesAlert');
    const driverStatsBanner = document.getElementById('driverStatsBanner');

    let eligibleDeliveries = [];

    // Filter and populate Treasury Accounts by selected Type and Currency
    function updateTreasuryAccounts() {
        const type = treasuryTypeSelect.value;
        const currencyId = parseInt(currencySelect.value);

        treasuryAccountSelect.innerHTML = '<option value="">-- اختر الحساب المالي --</option>';

        if (!type || !treasuryAccountsData[type]) return;

        const accounts = treasuryAccountsData[type].filter(acc => {
            if (!currencyId) return true;
            if (parseInt(acc.currency_id) === currencyId) return true;
            const opened = Array.isArray(acc.currency_accounts) ? acc.currency_accounts : [];
            return opened.some(ca => parseInt(ca.currency_id) === currencyId && ca.is_active !== false);
        });

        if (accounts.length === 0) {
            treasuryAccountSelect.innerHTML = '<option value="">لا توجد حسابات نشطة متوافقة مع العملة المحددة</option>';
            return;
        }

        accounts.forEach(acc => {
            const opt = document.createElement('option');
            opt.value = acc.id;
            const codes = Array.isArray(acc.currency_accounts) && acc.currency_accounts.length
                ? acc.currency_accounts.map(ca => ca.currency ? ca.currency.code : '').filter(Boolean).join(', ')
                : (acc.currency ? acc.currency.code : '');
            opt.textContent = (acc.name_ar || acc.name || acc.account_name || acc.account_label || acc.code) + ' (' + codes + ')';
            treasuryAccountSelect.appendChild(opt);
        });
    }

    treasuryTypeSelect.addEventListener('change', updateTreasuryAccounts);
    currencySelect.addEventListener('change', function() {
        updateTreasuryAccounts();
        loadEligibleDeliveries();
    });
    driverSelect.addEventListener('change', loadEligibleDeliveries);

    // Fetch Eligible Deliveries from Backend
    function loadEligibleDeliveries() {
        const driverId = driverSelect.value;
        const currencyId = currencySelect.value;

        if (!driverId || !currencyId) {
            deliveriesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">يرجى اختيار المندوب والعملة لعرض الشحنات المؤهلة.</td></tr>';
            driverStatsBanner.classList.add('d-none');
            noDeliveriesAlert.classList.add('d-none');
            return;
        }

        deliveriesLoading.classList.remove('d-none');
        noDeliveriesAlert.classList.add('d-none');
        deliveriesTableBody.innerHTML = '';

        fetch(`{{ route('admin.finance.cod-settlements.eligible-deliveries') }}?driver_id=${driverId}&currency_id=${currencyId}`)
            .then(res => res.json())
            .then(data => {
                deliveriesLoading.classList.add('d-none');
                if (data.status === 'success') {
                    eligibleDeliveries = data.deliveries;

                    // Update stats
                    if (data.outstanding) {
                        document.getElementById('statDriverName').textContent = 'ملخص عهدة: ' + (driverSelect.options[driverSelect.selectedIndex].text);
                        document.getElementById('statTotalCollected').textContent = parseFloat(data.outstanding.total_collected).toLocaleString('en-US', {minimumFractionDigits: 2});
                        document.getElementById('statTotalSettled').textContent = parseFloat(data.outstanding.total_settled).toLocaleString('en-US', {minimumFractionDigits: 2});
                        document.getElementById('statTotalOutstanding').textContent = parseFloat(data.outstanding.total_outstanding).toLocaleString('en-US', {minimumFractionDigits: 2});
                        document.getElementById('statEligibleCount').textContent = data.outstanding.eligible_count + ' شحنة مؤهلة';
                        driverStatsBanner.classList.remove('d-none');
                    }

                    if (eligibleDeliveries.length === 0) {
                        noDeliveriesAlert.classList.remove('d-none');
                        deliveriesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">لا توجد شحنات مؤهلة للتسوية لهذا المندوب.</td></tr>';
                    } else {
                        renderDeliveriesTable(eligibleDeliveries);
                    }
                }
            })
            .catch(err => {
                deliveriesLoading.classList.add('d-none');
                console.error(err);
                deliveriesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">حدث خطأ أثناء جلب بيانات الشحنات المؤهلة.</td></tr>';
            });
    }

    function renderDeliveriesTable(deliveries) {
        deliveriesTableBody.innerHTML = '';
        deliveries.forEach((d, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center">
                    <div class="form-check">
                        <input class="form-check-input delivery-checkbox" type="checkbox" data-index="${idx}" id="chk_${d.delivery_id}" checked>
                    </div>
                </td>
                <td><span class="fw-semibold text-primary">${d.delivery_number}</span></td>
                <td><span class="badge bg-light text-body border">${d.order_number}</span></td>
                <td>${d.customer_name}</td>
                <td><small class="text-muted">${d.delivered_at}</small></td>
                <td>${parseFloat(d.collected_amount).toFixed(2)}</td>
                <td>${parseFloat(d.previously_settled_amount).toFixed(2)}</td>
                <td><strong class="text-danger">${parseFloat(d.outstanding_amount).toFixed(2)}</strong></td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" min="0.01" max="${d.outstanding_amount}" 
                            class="form-control item-settlement-input" 
                            name="items[${idx}][settlement_amount]" 
                            id="input_amount_${idx}"
                            value="${d.outstanding_amount}">
                        <input type="hidden" name="items[${idx}][delivery_id]" value="${d.delivery_id}" id="input_delivery_${idx}">
                        <button class="btn btn-outline-secondary btn-fill-max" type="button" data-index="${idx}" title="تسوية كامل المبلغ">
                            <i class="ri-arrow-up-line"></i>
                        </button>
                    </div>
                    <small class="text-danger d-none invalid-over-settle" id="err_${idx}">المبلغ يتجاوز المتبقي!</small>
                </td>
            `;
            deliveriesTableBody.appendChild(tr);
        });

        attachTableEvents();
        updateSummary();
    }

    function attachTableEvents() {
        document.querySelectorAll('.delivery-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const idx = this.getAttribute('data-index');
                const inputAmount = document.getElementById(`input_amount_${idx}`);
                const inputDelivery = document.getElementById(`input_delivery_${idx}`);
                if (this.checked) {
                    inputAmount.removeAttribute('disabled');
                    inputDelivery.removeAttribute('disabled');
                } else {
                    inputAmount.setAttribute('disabled', 'disabled');
                    inputDelivery.setAttribute('disabled', 'disabled');
                }
                updateSummary();
            });
        });

        document.querySelectorAll('.item-settlement-input').forEach(inp => {
            inp.addEventListener('input', function() {
                const max = parseFloat(this.getAttribute('max'));
                const val = parseFloat(this.value);
                const idx = this.id.replace('input_amount_', '');
                const errElem = document.getElementById(`err_${idx}`);

                if (val > max) {
                    this.classList.add('is-invalid');
                    if (errElem) errElem.classList.remove('d-none');
                } else {
                    this.classList.remove('is-invalid');
                    if (errElem) errElem.classList.add('d-none');
                }
                updateSummary();
            });
        });

        document.querySelectorAll('.btn-fill-max').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = this.getAttribute('data-index');
                const inp = document.getElementById(`input_amount_${idx}`);
                inp.value = inp.getAttribute('max');
                inp.classList.remove('is-invalid');
                document.getElementById(`err_${idx}`).classList.add('d-none');
                updateSummary();
            });
        });
    }

    function updateSummary() {
        let selectedCount = 0;
        let totalSettled = 0;

        document.querySelectorAll('.delivery-checkbox:checked').forEach(cb => {
            const idx = cb.getAttribute('data-index');
            const inp = document.getElementById(`input_amount_${idx}`);
            const val = parseFloat(inp.value) || 0;
            selectedCount++;
            totalSettled += val;
        });

        document.getElementById('summarySelectedCount').textContent = selectedCount;
        document.getElementById('summaryTotalSettled').textContent = totalSettled.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    document.getElementById('btnSelectAll').addEventListener('click', function() {
        document.querySelectorAll('.delivery-checkbox').forEach(cb => {
            cb.checked = true;
            const idx = cb.getAttribute('data-index');
            document.getElementById(`input_amount_${idx}`).removeAttribute('disabled');
            document.getElementById(`input_delivery_${idx}`).removeAttribute('disabled');
        });
        updateSummary();
    });

    document.getElementById('btnDeselectAll').addEventListener('click', function() {
        document.querySelectorAll('.delivery-checkbox').forEach(cb => {
            cb.checked = false;
            const idx = cb.getAttribute('data-index');
            document.getElementById(`input_amount_${idx}`).setAttribute('disabled', 'disabled');
            document.getElementById(`input_delivery_${idx}`).setAttribute('disabled', 'disabled');
        });
        updateSummary();
    });

    // Form Submission: Draft
    document.getElementById('btnSaveDraft').addEventListener('click', function() {
        if (!validateSubmission()) return;
        document.getElementById('action_type').value = 'draft';
        document.getElementById('settlementForm').submit();
    });

    // Form Submission: Post directly (with modal)
    const postingModal = new bootstrap.Modal(document.getElementById('postingConfirmModal'));
    document.getElementById('btnPostDirectly').addEventListener('click', function() {
        if (!validateSubmission()) return;

        document.getElementById('modalDriverName').textContent = driverSelect.options[driverSelect.selectedIndex].text;
        document.getElementById('modalTreasuryName').textContent = treasuryAccountSelect.options[treasuryAccountSelect.selectedIndex].text;
        document.getElementById('modalTotalAmount').textContent = document.getElementById('summaryTotalSettled').textContent;

        postingModal.show();
    });

    document.getElementById('btnConfirmPostingSubmit').addEventListener('click', function() {
        document.getElementById('action_type').value = 'post';
        document.getElementById('settlementForm').submit();
    });

    function validateSubmission() {
        if (!driverSelect.value) {
            alert('يرجى اختيار المندوب.');
            driverSelect.focus();
            return false;
        }
        if (!currencySelect.value) {
            alert('يرجى اختيار العملة.');
            currencySelect.focus();
            return false;
        }
        if (!treasuryAccountSelect.value) {
            alert('يرجى اختيار الحساب المالي المستلم.');
            treasuryAccountSelect.focus();
            return false;
        }

        const checkedDeliveries = document.querySelectorAll('.delivery-checkbox:checked');
        if (checkedDeliveries.length === 0) {
            alert('يجب تحديد شحنة واحدة على الأقل للتسوية.');
            return false;
        }

        let hasError = false;
        checkedDeliveries.forEach(cb => {
            const idx = cb.getAttribute('data-index');
            const inp = document.getElementById(`input_amount_${idx}`);
            const val = parseFloat(inp.value) || 0;
            const max = parseFloat(inp.getAttribute('max'));

            if (val <= 0 || val > max) {
                inp.classList.add('is-invalid');
                hasError = true;
            }
        });

        if (hasError) {
            alert('يوجد مبالغ تسوية غير صالحة أو تتجاوز الرصيد المستحق للشحنة.');
            return false;
        }

        return true;
    }

    // Initial trigger if preselected
    if (driverSelect.value && currencySelect.value) {
        updateTreasuryAccounts();
        loadEligibleDeliveries();
    }
});
</script>
@endpush
