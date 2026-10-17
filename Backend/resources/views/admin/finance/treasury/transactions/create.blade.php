@extends('layouts.admin')
@section('title', 'إنشاء سند خزينة جديد | لمّة')
@section('page-title', 'إنشاء سند خزينة جديد')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">بيانات سند القبض / الصرف</h5>
                <a href="{{ route('admin.finance.treasury.transactions.index') }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-right-line align-bottom me-1"></i> العودة للقائمة
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.finance.treasury.transactions.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">نوع السند / الحركة <span class="text-danger">*</span></label>
                            <select name="transaction_type" class="form-select @error('transaction_type') is-invalid @enderror" required>
                                <option value="">اختر نوع الحركة...</option>
                                @foreach(\App\Enums\TreasuryTransactionType::cases() as $type)
                                    @if(!$type->isTransfer())
                                        <option value="{{ $type->value }}" {{ old('transaction_type') === $type->value ? 'selected' : '' }}>
                                            {{ $type->label() }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('transaction_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">حساب الخزينة التشغيلي <span class="text-danger">*</span></label>
                            <select name="account_selection" id="accountSelection" class="form-select @error('account_id') is-invalid @enderror" required onchange="handleAccountChange(this)">
                                <option value="">اختر الصندوق / البنك / المحفظة...</option>
                                @include('admin.finance.treasury.transactions._account_options', [
                                    'cashboxes' => $cashboxes,
                                    'bankAccounts' => $bankAccounts,
                                    'wallets' => $wallets,
                                    'selectedAccountType' => old('account_type'),
                                    'selectedAccountId' => old('account_id'),
                                ])
                            </select>
                            <input type="hidden" name="account_type" id="accountType" value="{{ old('account_type') }}">
                            <input type="hidden" name="account_id" id="accountId" value="{{ old('account_id') }}">
                            @error('account_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @error('account_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">عملة الحركة (من العملات المفتوحة على الحساب) <span class="text-danger">*</span></label>
                            <select name="currency_id" id="currencyId" class="form-select @error('currency_id') is-invalid @enderror" required>
                                <option value="">اختر حساب الخزينة أولاً...</option>
                            </select>
                            <div class="form-text text-muted">تظهر فقط العملات المفتوحة على الحساب المختار. سيُربط السند بحساب العملة تلقائياً.</div>
                            @error('currency_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">المبلغ <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" placeholder="0.00" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">سعر الصرف إلى العملة الأساسية (اختياري)</label>
                            <input type="number" step="0.00000001" min="0.00000001" name="exchange_rate_snapshot" class="form-control @error('exchange_rate_snapshot') is-invalid @enderror" value="{{ old('exchange_rate_snapshot', '1.00000000') }}" placeholder="1.00000000">
                            <div class="form-text text-muted">اتركه 1 إذا كانت نفس العملة الأساسية أو سيتم استخدام السعر المعتمد تلقائياً.</div>
                            @error('exchange_rate_snapshot')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">الحساب المقابل في الأستاذ العام <span class="text-danger">*</span></label>
                            <select name="offset_account_id" class="form-select @error('offset_account_id') is-invalid @enderror" required>
                                <option value="">اختر الحساب المقابل...</option>
                                @foreach($offsetAccounts as $acc)
                                    <option value="{{ $acc->id }}" {{ old('offset_account_id') == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} - {{ $acc->name_ar }} ({{ $acc->type->value }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted">الحساب الذي سيُقابل حساب الخزينة في القيد المحاسبي (إيراد، مصروف، رأس مال، ذمم...).</div>
                            @error('offset_account_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">رقم المرجع الخارجي (شيك / إيصال بنكي / رقم حوالة)</label>
                            <input type="text" name="reference_number" class="form-control @error('reference_number') is-invalid @enderror" value="{{ old('reference_number') }}" placeholder="مثال: CHQ-98124">
                            @error('reference_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">البيان / الوصف <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2" placeholder="وصف وتفاصيل الحركة المحاسبية..." required>{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <a href="{{ route('admin.finance.treasury.transactions.index') }}" class="btn btn-light me-2">إلغاء</a>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> حفظ كمسودة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@include('admin.finance.treasury.transactions._account_currency_script', [
    'preferredCurrencyId' => old('currency_id'),
])
@endsection
