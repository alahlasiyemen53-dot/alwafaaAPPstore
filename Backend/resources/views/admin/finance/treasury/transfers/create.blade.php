@extends('layouts.admin')

@section('title', 'تحويل مالي بين الخزائن والحسابات')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">تحويل مالي بين الخزائن والحسابات</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.finance.treasury.transactions.index') }}">حركات الخزينة</a></li>
                        <li class="breadcrumb-item active">تحويل مالي</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-soft-primary border-bottom">
                    <h5 class="card-title mb-0 text-primary"><i class="ri-arrow-left-right-line me-1"></i> نموذج تحويل رصيد داخلي (Transfer)</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.finance.treasury.transfers.store') }}" method="POST" id="transferForm">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="transaction_date" class="form-label">تاريخ التحويل <span class="text-danger">*</span></label>
                                <input type="text" name="transaction_date" id="transaction_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ old('transaction_date', date('d-m-Y')) }}" placeholder="يوم-شهر-سنة" required>
                            </div>

                            <div class="col-md-6">
                                <label for="reference_number" class="form-label">رقم المرجع الخارجي / الإشعار</label>
                                <input type="text" name="reference_number" id="reference_number" class="form-control" value="{{ old('reference_number') }}" placeholder="رقم إشعار التحويل البنكي أو السند إن وجد">
                            </div>

                            <div class="col-12"><hr class="my-2"></div>

                            <!-- Source Account -->
                            <div class="col-md-6">
                                <div class="card border border-danger-subtle bg-light">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-danger"><i class="ri-upload-2-line me-1"></i> الحساب المصدر (خصم / سحب)</h6>
                                        <div class="mb-3">
                                            <label for="from_account_type" class="form-label">نوع المصدر <span class="text-danger">*</span></label>
                                            <select name="from_account_type" id="from_account_type" class="form-select" required>
                                                <option value="">-- اختر النوع --</option>
                                                <option value="cashbox" {{ old('from_account_type') == 'cashbox' ? 'selected' : '' }}>صندوق كاش (Cashbox)</option>
                                                <option value="bank_account" {{ old('from_account_type') == 'bank_account' ? 'selected' : '' }}>حساب بنكي (Bank Account)</option>
                                                <option value="electronic_wallet" {{ old('from_account_type') == 'electronic_wallet' ? 'selected' : '' }}>محفظة إلكترونية (Wallet)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="from_account_id" class="form-label">اختر الحساب المصدر <span class="text-danger">*</span></label>
                                            <select name="from_account_id" id="from_account_id" class="form-select" required>
                                                <option value="">-- اختر الحساب المصدر أولاً --</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Destination Account -->
                            <div class="col-md-6">
                                <div class="card border border-success-subtle bg-light">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-success"><i class="ri-download-2-line me-1"></i> الحساب الوجهة (إيداع / استلام)</h6>
                                        <div class="mb-3">
                                            <label for="to_account_type" class="form-label">نوع الوجهة <span class="text-danger">*</span></label>
                                            <select name="to_account_type" id="to_account_type" class="form-select" required>
                                                <option value="">-- اختر النوع --</option>
                                                <option value="cashbox" {{ old('to_account_type') == 'cashbox' ? 'selected' : '' }}>صندوق كاش (Cashbox)</option>
                                                <option value="bank_account" {{ old('to_account_type') == 'bank_account' ? 'selected' : '' }}>حساب بنكي (Bank Account)</option>
                                                <option value="electronic_wallet" {{ old('to_account_type') == 'electronic_wallet' ? 'selected' : '' }}>محفظة إلكترونية (Wallet)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="to_account_id" class="form-label">اختر الحساب الوجهة <span class="text-danger">*</span></label>
                                            <select name="to_account_id" id="to_account_id" class="form-select" required>
                                                <option value="">-- اختر الحساب الوجهة أولاً --</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="amount" class="form-label">المبلغ المحول <span class="text-danger">*</span></label>
                                <input type="number" step="0.0001" name="amount" id="amount" class="form-control" value="{{ old('amount') }}" required min="0.0001" placeholder="0.0000">
                            </div>

                            <div class="col-md-6">
                                <label for="exchange_rate" class="form-label">سعر الصرف (إذا اختلفت العملة)</label>
                                <input type="number" step="0.000001" name="exchange_rate" id="exchange_rate" class="form-control" value="{{ old('exchange_rate', 1.000000) }}" min="0.000001">
                                <div class="form-text text-muted">افتراضياً 1.000000 للعملات المتطابقة.</div>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">البيان / سبب التحويل <span class="text-danger">*</span></label>
                                <textarea name="description" id="description" class="form-control" rows="2" required placeholder="مثال: تغذية حساب البنك من الصندوق الرئيسي...">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.finance.treasury.transactions.index') }}" class="btn btn-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="ri-check-double-line me-1"></i> تنفيذ التحويل وترحيله
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const accountsData = {
        cashbox: @json($cashboxes),
        bank_account: @json($bankAccounts),
        electronic_wallet: @json($wallets ?? $electronicWallets ?? [])
    };

    const oldFromType = "{{ old('from_account_type') }}";
    const oldFromId = "{{ old('from_account_id') }}";
    const oldToType = "{{ old('to_account_type') }}";
    const oldToId = "{{ old('to_account_id') }}";

    function populateSelect(selectElem, type, selectedId) {
        selectElem.innerHTML = '<option value="">-- اختر الحساب --</option>';
        if (!type || !accountsData[type]) return;

        accountsData[type].forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            const codes = Array.isArray(item.currency_accounts) && item.currency_accounts.length
                ? item.currency_accounts.map(ca => ca.currency ? ca.currency.code : '').filter(Boolean).join(', ')
                : (item.currency ? item.currency.code : '');
            const name = item.name_ar || item.account_name || item.account_label || item.name || item.code;
            opt.textContent = `${name} (${codes})`;
            if (selectedId && selectedId == item.id) {
                opt.selected = true;
            }
            selectElem.appendChild(opt);
        });
    }

    const fromTypeSelect = document.getElementById('from_account_type');
    const fromIdSelect = document.getElementById('from_account_id');
    const toTypeSelect = document.getElementById('to_account_type');
    const toIdSelect = document.getElementById('to_account_id');

    fromTypeSelect.addEventListener('change', function() {
        populateSelect(fromIdSelect, this.value);
    });

    toTypeSelect.addEventListener('change', function() {
        populateSelect(toIdSelect, this.value);
    });

    if (oldFromType) {
        populateSelect(fromIdSelect, oldFromType, oldFromId);
    }
    if (oldToType) {
        populateSelect(toIdSelect, oldToType, oldToId);
    }
</script>
@endpush
@endsection
