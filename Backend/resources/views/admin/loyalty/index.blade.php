@extends('layouts.admin')
@section('title', 'نقاط ولاء العملاء | الوفاء استور')
@section('page-title', 'برنامج ولاء العملاء')

@section('content')
<div class="row">
    <!-- Header with Action Button -->
    <div class="col-12 mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1">أرصدة نقاط ولاء العملاء</h4>
                <p class="text-muted mb-0 fs-13">متابعة حسابات الولاء ونقاط المكافآت المكتسبة والمستردة وتسويتها</p>
            </div>
            @if(auth()->user()->hasPermission('admin.loyalty.adjust'))
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustLoyaltyModal">
                    <i class="ri-add-circle-line me-1"></i> تسوية نقاط يدوية
                </button>
            </div>
            @endif
        </div>
    </div>

    <!-- Filters Card -->
    <div class="col-12 mb-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.loyalty.index') }}" class="row g-3 filter-row">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="ri-search-line"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="بحث بالاسم أو رقم الهاتف..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">-- كل الحالات --</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط (Active)</option>
                            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>معلق (Suspended)</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">تصفية</button>
                        <a href="{{ route('admin.loyalty.index') }}" class="btn btn-light">إعادة ضبط</a>
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
                                <th>حالة الحساب</th>
                                <th class="text-center">رصيد النقاط الفعلي</th>
                                <th>تاريخ التفعيل</th>
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
                                    <span class="{{ $account->status->badgeClass() }}">
                                        {{ $account->status->label() }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary fs-14 px-3 py-2">
                                        <i class="ri-copper-coin-line me-1"></i>{{ number_format($account->dynamic_balance) }} نقطة
                                    </span>
                                </td>
                                <td>{{ $account->created_at->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.loyalty.show', $account) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ri-file-list-3-line me-1"></i> كشف الحساب
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ri-inbox-line fs-36 text-muted mb-2 d-block"></i>
                                    لا توجد حسابات نقاط ولاء مطابقة للمعايير المحددة.
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

<!-- Modal: Adjust Loyalty Points -->
@if(auth()->user()->hasPermission('admin.loyalty.adjust'))
<div class="modal fade" id="adjustLoyaltyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.loyalty.adjust') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="ri-copper-coin-line me-1 text-primary"></i> تسوية نقاط ولاء العميل</h5>
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
                        <label class="form-label required">نوع التسوية</label>
                        <select name="type" class="form-select" required>
                            <option value="credit">إضافة نقاط (Credit)</option>
                            <option value="debit">خصم نقاط (Debit)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">عدد النقاط</label>
                        <input type="number" name="points" min="1" class="form-control" placeholder="مثال: 50" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">سبب التسوية الإدارية</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="اكتب مبرر التسوية الإدارية بالتفصيل..." required minlength="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تنفيذ التسوية</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
