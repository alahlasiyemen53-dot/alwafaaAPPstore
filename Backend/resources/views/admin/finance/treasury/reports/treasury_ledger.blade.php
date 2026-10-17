@extends('layouts.admin')

@section('title', 'كشف حساب الخزينة (Treasury Ledger)')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">كشف حساب الخزينة (Treasury Ledger)</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item active">كشف حساب الخزينة</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-soft-primary border-bottom">
            <h5 class="card-title mb-0"><i class="ri-filter-3-line me-1"></i> معايير البحث والفلترة</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.finance.treasury.reports.ledger') }}" method="GET" id="ledgerFilterForm" class="row g-3 align-items-end filter-row">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="account_type" class="form-label">نوع الحساب <span class="text-danger">*</span></label>
                        <select name="account_type" id="account_type" class="form-select" required>
                            <option value="{{ \App\Models\Cashbox::class }}" {{ request('account_type') == \App\Models\Cashbox::class ? 'selected' : '' }}>صندوق كاش (Cashbox)</option>
                            <option value="{{ \App\Models\BankAccount::class }}" {{ request('account_type') == \App\Models\BankAccount::class ? 'selected' : '' }}>حساب بنكي (Bank Account)</option>
                            <option value="{{ \App\Models\ElectronicWalletAccount::class }}" {{ request('account_type') == \App\Models\ElectronicWalletAccount::class ? 'selected' : '' }}>محفظة إلكترونية (Wallet)</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="account_id" class="form-label">الحساب التشغيلي <span class="text-danger">*</span></label>
                        <select name="account_id" id="account_id" class="form-select" required>
                            <option value="">-- اختر الحساب أولاً --</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="currency_id" class="form-label">العملة</label>
                        <select name="currency_id" id="currency_id" class="form-select">
                            @if($availableCurrencies->isNotEmpty())
                                @foreach($availableCurrencies as $curr)
                                    <option value="{{ $curr->id }}" {{ ($selectedCurrencyId == $curr->id) ? 'selected' : '' }}>
                                        {{ $curr->name_ar }} ({{ $curr->code }})
                                    </option>
                                @endforeach
                            @else
                                <option value="">افتراضي الحساب</option>
                            @endif
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="filter_date_from" class="form-label">من تاريخ</label>
                        <input type="text" name="date_from" id="filter_date_from" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('date_from', date('01-m-Y'))) }}" placeholder="يوم-شهر-سنة">
                    </div>

                    <div class="col-md-2">
                        <label for="filter_date_to" class="form-label">إلى تاريخ</label>
                        <input type="text" name="date_to" id="filter_date_to" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('date_to', date('d-m-Y'))) }}" placeholder="يوم-شهر-سنة">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="ri-search-line me-1"></i> عرض الكشف
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($selectedAccount)
        <!-- Account Info Summary -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-white text-primary rounded-circle fs-3">
                                    <i class="ri-wallet-3-line"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-white-50 mb-1">الحساب التشغيلي والعملة</p>
                                <h4 class="mb-0 text-white">
                                    {{ $selectedAccount->displayName }}
                                    <span class="badge bg-light text-primary fs-13 ms-2">
                                        {{ $selectedCurrencyAccount?->currency?->code ?? $selectedAccount->currency?->code }}
                                    </span>
                                </h4>
                                <small class="text-white-50">
                                    حساب الأستاذ العام: 
                                    {{ $selectedCurrencyAccount?->glAccount?->code ?? $selectedAccount->glAccount?->code }} - 
                                    {{ $selectedCurrencyAccount?->glAccount?->name_ar ?? $selectedAccount->glAccount?->name_ar }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-soft-success text-success rounded-circle fs-3">
                                    <i class="ri-safe-2-line"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-medium text-muted mb-1">الرصيد النهائي المشتق لعملة ({{ $selectedCurrencyAccount?->currency?->code ?? $selectedAccount->currency?->code }})</p>
                                <h4 class="mb-0 {{ (float)$currentBalance < 0 ? 'text-danger' : 'text-primary' }}">
                                    {{ number_format((float)$currentBalance, 2) }} {{ $selectedCurrencyAccount?->currency?->code ?? $selectedAccount->currency?->code }}
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statement Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">سجل الحركات المالية من قيود الأستاذ العام (General Ledger) - العملة: {{ $selectedCurrencyAccount?->currency?->code ?? $selectedAccount->currency?->code }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>التاريخ</th>
                                <th>رقم القيد (Journal Entry)</th>
                                <th>رقم السند/الحركة</th>
                                <th>البيان والوصف</th>
                                <th class="text-end">مدين (وارد/إيداع)</th>
                                <th class="text-end">دائن (صادر/صرف)</th>
                                <th class="text-end">الرصيد التراكمي الجاري</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $row)
                                <tr>
                                    <td>{{ \App\Support\DateFilterNormalizer::formatDisplay($row->entry_date) }}</td>
                                    <td>
                                        <a href="{{ route('admin.finance.journal-entries.show', $row->journal_id) }}" class="badge bg-soft-primary text-primary fw-medium">
                                            {{ $row->entry_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $row->transaction_number ?? '-' }}</span>
                                    </td>
                                    <td>{{ $row->line_description ?: $row->journal_description }}</td>
                                    <td class="text-end text-success fw-semibold">
                                        {{ (float)$row->incoming > 0 ? number_format((float)$row->incoming, 2) : '-' }}
                                    </td>
                                    <td class="text-end text-danger fw-semibold">
                                        {{ (float)$row->outgoing > 0 ? number_format((float)$row->outgoing, 2) : '-' }}
                                    </td>
                                    <td class="text-end fw-bold {{ (float)$row->running_balance < 0 ? 'text-danger' : 'text-dark' }}">
                                        {{ number_format((float)$row->running_balance, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">لا توجد حركات مسجلة خلال الفترة المحددة.</td>
                                </tr>
                            @endforelse

                            <tr class="table-light fw-bold">
                                <td colspan="6"><i class="ri-checkbox-circle-line me-1"></i> الرصيد التراكمي الختامي</td>
                                <td class="text-end text-primary fs-6">{{ number_format((float)$currentBalance, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info shadow-sm" role="alert">
            <i class="ri-information-line me-1"></i> يرجى اختيار نوع الحساب والحساب التشغيلي لعرض كشف حساب الخزينة والرصيد التراكمي.
        </div>
    @endif
</div>

@push('scripts')
<script>
    const accountsData = {
        "{{ addslashes(\App\Models\Cashbox::class) }}": @json($cashboxes),
        "{{ addslashes(\App\Models\BankAccount::class) }}": @json($bankAccounts),
        "{{ addslashes(\App\Models\ElectronicWalletAccount::class) }}": @json($wallets)
    };

    const selectedType = "{{ addslashes(request('account_type', \App\Models\Cashbox::class)) }}";
    const selectedId = "{{ request('account_id') }}";
    const selectedCurrencyId = "{{ request('currency_id') }}";

    const typeSelect = document.getElementById('account_type');
    const idSelect = document.getElementById('account_id');
    const currencySelect = document.getElementById('currency_id');

    function populateCurrencies(type, accountId, currentCurrencyId) {
        if (!currencySelect) return;
        currencySelect.innerHTML = '<option value="">-- العملة الافتراضية --</option>';
        if (!type || !accountId || !accountsData[type]) return;

        const acc = accountsData[type].find(a => a.id == accountId);
        if (!acc || !acc.currency_accounts || acc.currency_accounts.length === 0) return;

        acc.currency_accounts.forEach(ca => {
            if (!ca.currency) return;
            const opt = document.createElement('option');
            opt.value = ca.currency.id;
            opt.textContent = `${ca.currency.name_ar || ca.currency.name} (${ca.currency.code})`;
            if (currentCurrencyId && currentCurrencyId == ca.currency.id) {
                opt.selected = true;
            }
            currencySelect.appendChild(opt);
        });
    }

    function populateSelect(selectElem, type, currentId) {
        selectElem.innerHTML = '<option value="">-- اختر الحساب --</option>';
        if (!type || !accountsData[type]) return;

        accountsData[type].forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            const name = item.name_ar || item.account_name || item.account_label || item.code;
            opt.textContent = `${name} (${item.code})`;
            if (currentId && currentId == item.id) {
                opt.selected = true;
            }
            selectElem.appendChild(opt);
        });
    }

    typeSelect.addEventListener('change', function() {
        populateSelect(idSelect, this.value, null);
        populateCurrencies(this.value, null, null);
    });

    idSelect.addEventListener('change', function() {
        populateCurrencies(typeSelect.value, this.value, null);
    });

    if (selectedType) {
        populateSelect(idSelect, selectedType, selectedId);
        if (selectedId) {
            populateCurrencies(selectedType, selectedId, selectedCurrencyId);
        }
    }
</script>
@endpush
@endsection
