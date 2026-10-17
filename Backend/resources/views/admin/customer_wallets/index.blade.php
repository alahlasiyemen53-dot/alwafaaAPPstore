@extends('layouts.admin')
@section('title', 'محافظ العملاء | الوفاء استور')
@section('page-title', 'إدارة محافظ العملاء')

@section('content')
<div class="row">
    <!-- Header with Action Button -->
    <div class="col-12 mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1">أرصدة محافظ العملاء المالية</h4>
                <p class="text-muted mb-0 fs-13">متابعة حسابات المحافظ المالية كالتزام على الشركة وحركات الإيداع والخصم المعززة محاسبياً</p>
            </div>
            @if(auth()->user()->hasPermission('admin.customer_wallets.adjust'))
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustWalletModal">
                    <i class="ri-add-circle-line me-1"></i> تسوية رصيد يدوي
                </button>
            </div>
            @endif
        </div>
    </div>

    <!-- Filters Card -->
    <div class="col-12 mb-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.customer-wallets.index') }}" class="row g-3 filter-row">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="ri-search-line"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="بحث باسم العميل أو رقم الهاتف..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="currency_id" class="form-select">
                            <option value="">-- كل العملات --</option>
                            @foreach($currencies as $curr)
                            <option value="{{ $curr->id }}" {{ request('currency_id') == $curr->id ? 'selected' : '' }}>
                                {{ $curr->name_ar }} ({{ $curr->code }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">-- كل الحالات --</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط (Active)</option>
                            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>معلق (Suspended)</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">تصفية</button>
                        <a href="{{ route('admin.customer-wallets.index') }}" class="btn btn-light">إعادة ضبط</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>العميل</th>
                                <th>رقم الهاتف</th>
                                <th>عملة المحفظة</th>
                                <th>حالة الحساب</th>
                                <th class="text-center">الرصيد الفعلي المتاح</th>
                                <th>تاريخ الإنشاء</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accounts as $account)
                            <tr>
                                <td>{{ $account->id }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $account->customer?->name }}</div>
                                    <small class="text-muted">ID: {{ $account->customer_id }}</small>
                                </td>
                                <td>
                                    <span class="text-muted"><i class="ri-phone-line me-1"></i>{{ $account->customer?->phone }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ $account->currency?->code }} ({{ $account->currency?->symbol }})
                                    </span>
                                </td>
                                <td>
                                    <span class="{{ $account->status->badgeClass() }}">
                                        {{ $account->status->label() }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success fs-14 px-3 py-2">
                                        {{ number_format((float) $account->dynamic_balance, 2) }} {{ $account->currency?->symbol }}
                                    </span>
                                </td>
                                <td>{{ $account->created_at->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.customer-wallets.show', $account) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ri-file-list-3-line me-1"></i> كشف الحساب
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="ri-wallet-3-line fs-36 text-muted mb-2 d-block"></i>
                                    لا توجد محافظ عملاء مطابقة للمعايير المحددة.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($accounts->hasPages())
            <div class="card-footer bg-white border-top">
                {{ $accounts->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal: Adjust Customer Wallet -->
@if(auth()->user()->hasPermission('admin.customer_wallets.adjust'))
<div class="modal fade" id="adjustWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.customer-wallets.adjust') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="ri-wallet-3-line me-1 text-primary"></i> تسوية رصيد محفظة العميل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">اختر العميل</label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">-- اختر العميل --</option>
                            @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">العملة</label>
                        <select name="currency_id" class="form-select" required>
                            @foreach($currencies as $curr)
                            <option value="{{ $curr->id }}" {{ $curr->is_default ? 'selected' : '' }}>
                                {{ $curr->name_ar }} ({{ $curr->code }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">نوع العملية</label>
                        <select name="type" class="form-select" required>
                            <option value="credit">إيداع دائن (Credit) - زيادة رصيد العميل</option>
                            <option value="debit">خصم مدين (Debit) - تخفيض رصيد العميل</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">المبلغ</label>
                        <input type="number" step="0.0001" min="0.0001" name="amount" class="form-control" placeholder="مثال: 5000.0000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">سبب التسوية المالية</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="اكتب المبرر المالي للتسوية لتوثيقه في قيد اليومية العام..." required minlength="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تنفيذ وترحيل القيد</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
