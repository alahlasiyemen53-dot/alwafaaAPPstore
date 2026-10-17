@extends('layouts.admin')
@section('title', 'تعديل مسودة القيد اليومي | لمّة')
@section('page-title', 'تعديل مسودة القيد اليومي')

@section('content')
<form action="{{ route('admin.finance.journal-entries.update', $entry) }}" method="POST" id="journalForm">
    @csrf
    @method('PUT')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title mb-0">تعديل مسودة القيد: {{ $entry->entry_number }}</h5>
                            <span class="badge bg-warning-subtle text-warning mt-1">مسودة غير مرحلة</span>
                        </div>
                        <a href="{{ route('admin.finance.journal-entries.show', $entry) }}" class="btn btn-light btn-sm">
                            <i class="ri-arrow-right-line me-1"></i> العودة لتفاصيل القيد
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label required">الفترة المحاسبية</label>
                            <select name="accounting_period_id" class="form-select @error('accounting_period_id') is-invalid @enderror" required>
                                @foreach($periods as $period)
                                    <option value="{{ $period->id }}" {{ old('accounting_period_id', $entry->accounting_period_id) == $period->id ? 'selected' : '' }}>
                                        {{ $period->name }} ({{ $period->status->label() }})
                                    </option>
                                @endforeach
                            </select>
                            @error('accounting_period_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label required">تاريخ القيد</label>
                            <input type="text" name="entry_date" id="entry_date" class="form-control @error('entry_date') is-invalid @enderror" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ old('entry_date', $entry->entry_date->format('d-m-Y')) }}" placeholder="يوم-شهر-سنة" required>
                            @error('entry_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label required">عملة القيد</label>
                            <select name="currency_id" id="currency_id" class="form-select @error('currency_id') is-invalid @enderror" required>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}" {{ old('currency_id', $entry->currency_id) == $currency->id ? 'selected' : '' }}>
                                        {{ $currency->code }} - {{ $currency->name_ar }}
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">سعر الصرف للعملة الأساسية</label>
                            <input type="number" step="0.00000001" min="0.00000001" name="exchange_rate_snapshot" id="exchange_rate_snapshot" class="form-control font-monospace" value="{{ old('exchange_rate_snapshot', $entry->exchange_rate_snapshot) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label required">البيان العام للقيد</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2" required>{{ old('description', $entry->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">سطور القيد المزدوج</h5>
                        <button type="button" class="btn btn-primary btn-sm" id="addLineBtn">
                            <i class="ri-add-line me-1"></i> إضافة سطر
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0" id="linesTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%;">الحساب المالي <span class="text-danger">*</span></th>
                                    <th style="width: 25%;">شرح السطر</th>
                                    <th style="width: 17%;" class="text-end">مدين (Debit)</th>
                                    <th style="width: 17%;" class="text-end">دائن (Credit)</th>
                                    <th style="width: 6%;" class="text-center">حذف</th>
                                </tr>
                            </thead>
                            <tbody id="linesContainer">
                                @foreach($entry->lines as $index => $line)
                                    <tr class="line-row">
                                        <td>
                                            <select name="lines[{{ $index }}][account_id]" class="form-select account-select" required>
                                                <option value="">-- اختر الحساب --</option>
                                                @foreach($accounts as $acc)
                                                    <option value="{{ $acc->id }}" {{ $line->account_id == $acc->id ? 'selected' : '' }}>
                                                        {{ $acc->code }} - {{ $acc->name_ar }} ({{ $acc->type->label() }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="lines[{{ $index }}][description]" class="form-control form-control-sm" value="{{ $line->description }}" placeholder="بيان السطر...">
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0" name="lines[{{ $index }}][debit]" class="form-control text-end debit-input font-monospace" value="{{ number_format((float) $line->debit, 4, '.', '') }}">
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0" name="lines[{{ $index }}][credit]" class="form-control text-end credit-input font-monospace" value="{{ number_format((float) $line->credit, 4, '.', '') }}">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-subtle-danger remove-line-btn"><i class="ri-delete-bin-line"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="2" class="text-end">المجموع الإجمالي:</td>
                                    <td class="text-end text-primary font-monospace fs-15" id="totalDebit">0.0000</td>
                                    <td class="text-end text-success font-monospace fs-15" id="totalCredit">0.0000</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-end">الفرق (توازن القيد):</td>
                                    <td colspan="2" class="text-center fs-14">
                                        <span class="badge bg-danger-subtle text-danger" id="balanceBadge">
                                            <i class="ri-error-warning-line me-1"></i> القيد غير متوازن: الفرق <span id="balanceDiff">0.0000</span>
                                        </span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-4 text-end">
                        <a href="{{ route('admin.finance.journal-entries.show', $entry) }}" class="btn btn-light me-2">إلغاء</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="ri-save-line me-1"></i> حفظ تعديلات المسودة
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = {{ $entry->lines->count() }};
    const linesContainer = document.getElementById('linesContainer');
    const addLineBtn = document.getElementById('addLineBtn');
    const totalDebitEl = document.getElementById('totalDebit');
    const totalCreditEl = document.getElementById('totalCredit');
    const balanceBadge = document.getElementById('balanceBadge');
    const balanceDiff = document.getElementById('balanceDiff');

    function calculateTotals() {
        let debits = 0;
        let credits = 0;

        document.querySelectorAll('.line-row').forEach(row => {
            const deb = parseFloat(row.querySelector('.debit-input').value) || 0;
            const cred = parseFloat(row.querySelector('.credit-input').value) || 0;
            debits += deb;
            credits += cred;
        });

        totalDebitEl.textContent = debits.toFixed(4);
        totalCreditEl.textContent = credits.toFixed(4);

        const diff = Math.abs(debits - credits);
        balanceDiff.textContent = diff.toFixed(4);

        if (diff < 0.0001 && debits > 0) {
            balanceBadge.className = 'badge bg-success-subtle text-success';
            balanceBadge.innerHTML = '<i class="ri-checkbox-circle-line me-1"></i> القيد متوازن تماماً ومستوفٍ لشروط القيد المزدوج';
        } else {
            balanceBadge.className = 'badge bg-danger-subtle text-danger';
            balanceBadge.innerHTML = `<i class="ri-error-warning-line me-1"></i> القيد غير متوازن: الفرق ${diff.toFixed(4)}`;
        }
    }

    linesContainer.addEventListener('input', function (e) {
        if (e.target.classList.contains('debit-input')) {
            const val = parseFloat(e.target.value) || 0;
            if (val > 0) {
                e.target.closest('tr').querySelector('.credit-input').value = '0.0000';
            }
            calculateTotals();
        } else if (e.target.classList.contains('credit-input')) {
            const val = parseFloat(e.target.value) || 0;
            if (val > 0) {
                e.target.closest('tr').querySelector('.debit-input').value = '0.0000';
            }
            calculateTotals();
        }
    });

    addLineBtn.addEventListener('click', function () {
        const firstRow = document.querySelector('.line-row');
        const selectOptions = firstRow.querySelector('.account-select').innerHTML;

        const newRow = document.createElement('tr');
        newRow.className = 'line-row';
        newRow.innerHTML = `
            <td>
                <select name="lines[${rowIndex}][account_id]" class="form-select account-select" required>
                    ${selectOptions}
                </select>
            </td>
            <td>
                <input type="text" name="lines[${rowIndex}][description]" class="form-control form-control-sm" placeholder="بيان السطر...">
            </td>
            <td>
                <input type="number" step="0.0001" min="0" name="lines[${rowIndex}][debit]" class="form-control text-end debit-input font-monospace" placeholder="0.0000" value="0.0000">
            </td>
            <td>
                <input type="number" step="0.0001" min="0" name="lines[${rowIndex}][credit]" class="form-control text-end credit-input font-monospace" placeholder="0.0000" value="0.0000">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-subtle-danger remove-line-btn"><i class="ri-delete-bin-line"></i></button>
            </td>
        `;
        linesContainer.appendChild(newRow);
        rowIndex++;
        calculateTotals();
    });

    linesContainer.addEventListener('click', function (e) {
        if (e.target.closest('.remove-line-btn')) {
            const row = e.target.closest('tr');
            if (document.querySelectorAll('.line-row').length > 2) {
                row.remove();
                calculateTotals();
            } else {
                alert('يجب أن يحتوي القيد على سطرين على الأقل.');
            }
        }
    });

    calculateTotals();
});
</script>
@endsection
