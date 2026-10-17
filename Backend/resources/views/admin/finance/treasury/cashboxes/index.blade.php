@extends('layouts.admin')
@section('title', 'إدارة الصناديق النقدية | الوفاء استور')
@section('page-title', 'الصناديق النقدية')

@section('content')
<div class="row">
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">إجمالي الصناديق</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2">{{ number_format($metrics['total']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                            <i class="ri-safe-2-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">الصناديق النشطة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-success">{{ number_format($metrics['active']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                            <i class="ri-checkbox-circle-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">الصناديق المعطلة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-danger">{{ number_format($metrics['inactive']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle text-danger rounded fs-3">
                            <i class="ri-close-circle-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">قائمة الصناديق النقدية</h5>
        <div>
            @if(auth()->user()->hasPermission('admin.cashboxes.create'))
                <a href="{{ route('admin.finance.treasury.cashboxes.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> إضافة صندوق جديد
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.finance.treasury.cashboxes.index') }}" class="row g-3 mb-4 filter-row">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="البحث برمز أو اسم الصندوق..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="currency_id" class="form-select">
                    <option value="">جميع العملات</option>
                    @foreach($currencies as $currency)
                        <option value="{{ $currency->id }}" {{ request('currency_id') == $currency->id ? 'selected' : '' }}>
                            {{ $currency->name_ar }} ({{ $currency->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">جميع الحالات</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>معطل</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="ri-search-line"></i> تصفية</button>
                <a href="{{ route('admin.finance.treasury.cashboxes.index') }}" class="btn btn-light"><i class="ri-refresh-line"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-nowrap mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">الرمز</th>
                        <th scope="col">اسم الصندوق</th>
                        <th scope="col">العملات المفتوحة</th>
                        <th scope="col">الأرصدة المشتقة لكل عملة</th>
                        <th scope="col">حسابات الأستاذ العام</th>
                        <th scope="col">الحالة العامة</th>
                        <th scope="col" class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cashboxes as $cashbox)
                        <tr>
                            <td><span class="fw-bold text-primary">{{ $cashbox->code }}</span></td>
                            <td>{{ $cashbox->name_ar }} @if($cashbox->name_en)<small class="text-muted d-block">{{ $cashbox->name_en }}</small>@endif</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($cashbox->currencyAccounts as $currAcc)
                                        <a href="{{ route('admin.finance.treasury.currency-accounts.statement', $currAcc) }}" 
                                           class="badge {{ $currAcc->is_active ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-light text-muted' }}" 
                                           title="كشف حساب {{ $currAcc->currency?->code }} (انقر للعرض)">
                                            <i class="ri-file-list-line me-1"></i>{{ $currAcc->currency?->code }}
                                        </a>
                                    @empty
                                        <span class="badge bg-light text-dark">{{ $cashbox->currency?->code ?? '-' }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    @forelse($cashbox->currencyAccounts as $currAcc)
                                        <div class="fs-12">
                                            <span class="fw-bold {{ bccomp($currAcc->derived_balance ?? '0', '0', 4) < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format((float)($currAcc->derived_balance ?? 0), 2) }}
                                            </span>
                                            <span class="badge bg-light text-muted fs-10">{{ $currAcc->currency?->code }}</span>
                                        </div>
                                    @empty
                                        <span class="fw-semibold fs-14 {{ bccomp($cashbox->derived_balance, '0', 4) < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ number_format((float)$cashbox->derived_balance, 2) }} {{ $cashbox->currency?->code }}
                                        </span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1 fs-11">
                                    @forelse($cashbox->currencyAccounts as $currAcc)
                                        <div>
                                            <code>{{ $currAcc->glAccount?->code }}</code> - {{ $currAcc->glAccount?->name_ar }}
                                        </div>
                                    @empty
                                        @if($cashbox->glAccount)
                                            <code>{{ $cashbox->glAccount->code }}</code> - {{ $cashbox->glAccount->name_ar }}
                                        @else
                                            <span class="text-danger">غير مربوط</span>
                                        @endif
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                @if($cashbox->is_active)
                                    <span class="badge bg-success-subtle text-success">نشط</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">معطل</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-subtle-success" data-bs-toggle="modal" data-bs-target="#addCurrencyModal"
                                        data-entity-type="{{ get_class($cashbox) }}"
                                        data-entity-id="{{ $cashbox->id }}"
                                        data-entity-name="{{ $cashbox->name_ar }}"
                                        data-opened-currencies="{{ $cashbox->currencyAccounts->pluck('currency.code')->filter()->implode(',') }}"
                                        title="فتح عملة إضافية لهذا الصندوق">
                                        <i class="ri-money-dollar-circle-line"></i>
                                    </button>
                                    @if(auth()->user()->hasPermission('admin.treasury_reports.ledger'))
                                        <a href="{{ route('admin.finance.treasury.reports.ledger', ['account_type' => get_class($cashbox), 'account_id' => $cashbox->id]) }}" class="btn btn-sm btn-subtle-info" title="كشف الحساب">
                                            <i class="ri-file-list-line"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->hasPermission('admin.cashboxes.update'))
                                        <a href="{{ route('admin.finance.treasury.cashboxes.edit', $cashbox) }}" class="btn btn-sm btn-subtle-primary" title="تعديل">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->hasPermission('admin.cashboxes.toggle'))
                                        <form method="POST" action="{{ route('admin.finance.treasury.cashboxes.toggle', $cashbox) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $cashbox->is_active ? 'btn-subtle-warning' : 'btn-subtle-success' }}" title="{{ $cashbox->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                <i class="{{ $cashbox->is_active ? 'ri-stop-circle-line' : 'ri-play-circle-line' }}"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="ri-inbox-line fs-24 d-block mb-2"></i>
                                لا توجد صناديق نقدية مطابقة للمعايير المحددة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $cashboxes->links() }}
        </div>
    </div>
</div>

@include('admin.finance.treasury.currency_accounts.manage_modal')
@endsection
