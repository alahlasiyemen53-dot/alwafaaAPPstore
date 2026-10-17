@extends('layouts.admin')
@section('title', 'حركات وسندات الخزينة | الوفاء استور')
@section('page-title', 'حركات وسندات الخزينة')

@section('content')
<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">إجمالي الحركات</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2">{{ number_format($metrics['total']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                            <i class="ri-exchange-dollar-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">المسودات</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-warning">{{ number_format($metrics['draft']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                            <i class="ri-draft-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">الحركات المرحلة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-success">{{ number_format($metrics['posted']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                            <i class="ri-check-double-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">الحركات المعكوسة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-danger">{{ number_format($metrics['reversed']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle text-danger rounded fs-3">
                            <i class="ri-arrow-go-back-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">سجل حركات وسندات الخزينة</h5>
        <div class="d-flex gap-2">
            @if(auth()->user()->hasPermission('admin.treasury_transfers.create'))
                <a href="{{ route('admin.finance.treasury.transfers.create') }}" class="btn btn-outline-primary btn-sm">
                    <i class="ri-arrow-left-right-line align-bottom me-1"></i> تحويل مالي بين الخزائن
                </a>
            @endif
            @if(auth()->user()->hasPermission('admin.treasury_transactions.create'))
                <a href="{{ route('admin.finance.treasury.transactions.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> سند قبض / صرف جديد
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.finance.treasury.transactions.index') }}" class="row g-3 mb-4 filter-row">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="البحث برقم السند أو الوصف أو المرجع..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">جميع أنواع الحركات</option>
                    @foreach(\App\Enums\TreasuryTransactionType::cases() as $type)
                        <option value="{{ $type->value }}" {{ request('type') === $type->value ? 'selected' : '' }}>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">جميع الحالات</option>
                    @foreach(\App\Enums\TreasuryTransactionStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="currency_id" class="form-select">
                    <option value="">جميع العملات</option>
                    @foreach($currencies as $currency)
                        <option value="{{ $currency->id }}" {{ request('currency_id') == $currency->id ? 'selected' : '' }}>
                            {{ $currency->name_ar }} ({{ $currency->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="ri-search-line"></i> تصفية</button>
                <a href="{{ route('admin.finance.treasury.transactions.index') }}" class="btn btn-light"><i class="ri-refresh-line"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-nowrap mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">رقم السند</th>
                        <th scope="col">النوع</th>
                        <th scope="col">حساب الخزينة</th>
                        <th scope="col">المبلغ</th>
                        <th scope="col">الحساب المقابل / الوجهة</th>
                        <th scope="col">الحالة</th>
                        <th scope="col">التاريخ</th>
                        <th scope="col" class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                        <tr>
                            <td>
                                <a href="{{ route('admin.finance.treasury.transactions.show', $trx) }}" class="fw-bold text-primary">
                                    {{ $trx->transaction_number }}
                                </a>
                                @if($trx->reference_number)
                                    <small class="text-muted d-block">مرجع: {{ $trx->reference_number }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $trx->transaction_type->badgeClass() }}">
                                    {{ $trx->transaction_type->label() }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold">{{ $trx->account?->displayName ?? 'غير محدد' }}</span>
                            </td>
                            <td>
                                <span class="fw-bold fs-14 {{ $trx->transaction_type->isIncoming() ? 'text-success' : 'text-danger' }}">
                                    {{ $trx->transaction_type->isIncoming() ? '+' : '-' }} {{ number_format((float)$trx->amount, 2) }} {{ $trx->currency?->code }}
                                </span>
                            </td>
                            <td>
                                @if($trx->isTransfer())
                                    <span class="text-primary"><i class="ri-arrow-left-line"></i> {{ $trx->destinationAccount?->displayName ?? 'وجهة غير محددة' }}</span>
                                @elseif($trx->offsetAccount)
                                    <code>{{ $trx->offsetAccount->code }}</code> - {{ $trx->offsetAccount->name_ar }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $trx->status->badgeClass() }}">
                                    {{ $trx->status->label() }}
                                </span>
                            </td>
                            <td>{{ $trx->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="{{ route('admin.finance.treasury.transactions.show', $trx) }}" class="btn btn-sm btn-subtle-info" title="عرض التفاصيل">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                    @if($trx->isDraft() && auth()->user()->hasPermission('admin.treasury_transactions.update'))
                                        <a href="{{ route('admin.finance.treasury.transactions.edit', $trx) }}" class="btn btn-sm btn-subtle-primary" title="تعديل">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="ri-inbox-line fs-24 d-block mb-2"></i>
                                لا توجد حركات خزينة مطابقة للمعايير المحددة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $transactions->links() }}
        </div>
    </div>
</div>
@endsection
