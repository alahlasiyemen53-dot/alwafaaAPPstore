@extends('layouts.admin')
@section('title', 'إدارة المحافظ الإلكترونية | الوفاء استور')
@section('page-title', 'المحافظ الإلكترونية')

@section('content')
<div class="row">
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">إجمالي المحافظ</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2">{{ number_format($metrics['total']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                            <i class="ri-wallet-3-line"></i>
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
                        <p class="text-uppercase fw-medium text-muted mb-0">المحافظ النشطة</p>
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
                        <p class="text-uppercase fw-medium text-muted mb-0">المحافظ المعطلة</p>
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
        <h5 class="card-title mb-0">قائمة حسابات المحافظ الإلكترونية</h5>
        <div>
            @if(auth()->user()->hasPermission('admin.electronic_wallets.create'))
                <a href="{{ route('admin.finance.treasury.electronic-wallets.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> إضافة محفظة جديدة
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.finance.treasury.electronic-wallets.index') }}" class="row g-3 mb-4 filter-row">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="البحث برمز أو مزود أو معرف المحفظة..." value="{{ request('search') }}">
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
                <a href="{{ route('admin.finance.treasury.electronic-wallets.index') }}" class="btn btn-light"><i class="ri-refresh-line"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-nowrap mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">الرمز</th>
                        <th scope="col">مزود الخدمة</th>
                        <th scope="col">مسمى المحفظة</th>
                        <th scope="col">المعرف / رقم الهاتف</th>
                        <th scope="col">العملات المفتوحة</th>
                        <th scope="col">الأرصدة المشتقة لكل عملة</th>
                        <th scope="col">حسابات الأستاذ العام</th>
                        <th scope="col">الحالة العامة</th>
                        <th scope="col" class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($wallets as $wallet)
                        <tr>
                            <td><span class="fw-bold text-primary">{{ $wallet->code }}</span></td>
                            <td><span class="badge bg-secondary-subtle text-secondary">{{ $wallet->provider_name }}</span></td>
                            <td>{{ $wallet->account_label }}</td>
                            <td><code>{{ $wallet->account_identifier }}</code></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($wallet->currencyAccounts as $currAcc)
                                        <a href="{{ route('admin.finance.treasury.currency-accounts.statement', $currAcc) }}" 
                                           class="badge {{ $currAcc->is_active ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-light text-muted' }}" 
                                           title="كشف حساب {{ $currAcc->currency?->code }} (انقر للعرض)">
                                            <i class="ri-file-list-line me-1"></i>{{ $currAcc->currency?->code }}
                                        </a>
                                    @empty
                                        <span class="badge bg-light text-dark">{{ $wallet->currency?->code ?? '-' }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    @forelse($wallet->currencyAccounts as $currAcc)
                                        <div class="fs-12">
                                            <span class="fw-bold {{ bccomp($currAcc->derived_balance ?? '0', '0', 4) < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format((float)($currAcc->derived_balance ?? 0), 2) }}
                                            </span>
                                            <span class="badge bg-light text-muted fs-10">{{ $currAcc->currency?->code }}</span>
                                        </div>
                                    @empty
                                        <span class="fw-semibold fs-14 {{ bccomp($wallet->derived_balance, '0', 4) < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ number_format((float)$wallet->derived_balance, 2) }} {{ $wallet->currency?->code }}
                                        </span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1 fs-11">
                                    @forelse($wallet->currencyAccounts as $currAcc)
                                        <div>
                                            <code>{{ $currAcc->glAccount?->code }}</code> - {{ $currAcc->glAccount?->name_ar }}
                                        </div>
                                    @empty
                                        @if($wallet->glAccount)
                                            <code>{{ $wallet->glAccount->code }}</code> - {{ $wallet->glAccount->name_ar }}
                                        @else
                                            <span class="text-danger">غير مربوط</span>
                                        @endif
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                @if($wallet->is_active)
                                    <span class="badge bg-success-subtle text-success">نشط</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">معطل</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-subtle-success" data-bs-toggle="modal" data-bs-target="#addCurrencyModal"
                                        data-entity-type="{{ get_class($wallet) }}"
                                        data-entity-id="{{ $wallet->id }}"
                                        data-entity-name="{{ $wallet->provider_name }} - {{ $wallet->account_label }}"
                                        data-opened-currencies="{{ $wallet->currencyAccounts->pluck('currency.code')->filter()->implode(',') }}"
                                        title="فتح عملة إضافية لهذه المحفظة">
                                        <i class="ri-money-dollar-circle-line"></i>
                                    </button>
                                    @if(auth()->user()->hasPermission('admin.treasury_reports.ledger'))
                                        <a href="{{ route('admin.finance.treasury.reports.ledger', ['account_type' => get_class($wallet), 'account_id' => $wallet->id]) }}" class="btn btn-sm btn-subtle-info" title="كشف الحساب">
                                            <i class="ri-file-list-line"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->hasPermission('admin.electronic_wallets.update'))
                                        <a href="{{ route('admin.finance.treasury.electronic-wallets.edit', $wallet) }}" class="btn btn-sm btn-subtle-primary" title="تعديل">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->hasPermission('admin.electronic_wallets.toggle'))
                                        <form method="POST" action="{{ route('admin.finance.treasury.electronic-wallets.toggle', $wallet) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $wallet->is_active ? 'btn-subtle-warning' : 'btn-subtle-success' }}" title="{{ $wallet->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                <i class="{{ $wallet->is_active ? 'ri-stop-circle-line' : 'ri-play-circle-line' }}"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="ri-inbox-line fs-24 d-block mb-2"></i>
                                لا توجد محافظ إلكترونية مطابقة للمعايير المحددة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $wallets->links() }}
        </div>
    </div>
</div>

@include('admin.finance.treasury.currency_accounts.manage_modal')
@endsection
