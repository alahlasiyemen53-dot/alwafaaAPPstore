@extends('layouts.admin')
@section('title', 'دليل الحسابات | الوفاء استور')
@section('page-title', 'دليل الحسابات المالي')

@section('content')
<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">إجمالي الحسابات</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2">{{ number_format($metrics['total']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                            <i class="ri-book-3-line"></i>
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
                        <p class="text-uppercase fw-medium text-muted mb-0">حسابات الأصول</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-info">{{ number_format($metrics['assets']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle text-info rounded fs-3">
                            <i class="ri-funds-line"></i>
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
                        <p class="text-uppercase fw-medium text-muted mb-0">حسابات الالتزامات</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-warning">{{ number_format($metrics['liabilities']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                            <i class="ri-bank-card-line"></i>
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
                        <p class="text-uppercase fw-medium text-muted mb-0">حسابات تقبل الترحيل</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-success">{{ number_format($metrics['postable']) }}</h4>
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
</div>

<div class="card">
    <div class="card-header border-0 pb-0">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0">شجرة ودليل الحسابات</h5>
            @if(auth()->user()->hasPermission('admin.accounts.create'))
                <a href="{{ route('admin.finance.accounts.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-middle me-1"></i> إضافة حساب جديد
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.finance.accounts.index') }}" class="row g-3 mb-4 filter-row">
            <div class="col-md-4">
                <div class="search-box">
                    <input type="text" name="search" class="form-control" placeholder="بحث بالرمز أو اسم الحساب..." value="{{ $filters['search'] ?? '' }}">
                    <i class="ri-search-line search-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">جميع أنواع الحسابات</option>
                    @foreach($accountTypes as $type)
                        <option value="{{ $type->value }}" {{ ($filters['type'] ?? '') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">جميع الحالات</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>نشط</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>معطل</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="ri-filter-2-line me-1"></i> فلترة</button>
                @if(!empty($filters['search']) || !empty($filters['type']) || !empty($filters['status']))
                    <a href="{{ route('admin.finance.accounts.index') }}" class="btn btn-light"><i class="ri-refresh-line"></i></a>
                @endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle table-nowrap mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">رمز الحساب</th>
                        <th scope="col">اسم الحساب</th>
                        <th scope="col">الحساب الأب</th>
                        <th scope="col">النوع</th>
                        <th scope="col">طبيعة الرصيد</th>
                        <th scope="col">قابل للترحيل</th>
                        <th scope="col">الحالة</th>
                        <th scope="col" class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td>
                                <span class="fw-bold font-monospace text-primary">{{ $account->code }}</span>
                            </td>
                            <td>
                                <div>
                                    <span class="fw-medium text-dark">{{ $account->name_ar }}</span>
                                    @if($account->name_en)
                                        <small class="text-muted d-block">{{ $account->name_en }}</small>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($account->parent)
                                    <span class="badge bg-light text-body border">{{ $account->parent->code }} - {{ $account->parent->name_ar }}</span>
                                @else
                                    <span class="text-muted small">حساب رئيسي (جذر)</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $account->type->badgeClass() }}">{{ $account->type->label() }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $account->normal_balance->badgeClass() }}">{{ $account->normal_balance->label() }}</span>
                            </td>
                            <td>
                                @if($account->is_postable)
                                    <span class="badge bg-success-subtle text-success"><i class="ri-check-line me-1"></i>فرعي (يقبل القيود)</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning"><i class="ri-folder-line me-1"></i>تجميعي (رئيسي)</span>
                                @endif
                            </td>
                            <td>
                                @if($account->is_active)
                                    <span class="badge bg-success-subtle text-success">نشط</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">معطل</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    @if(auth()->user()->hasPermission('admin.general_ledger.view') && $account->is_postable)
                                        <a href="{{ route('admin.finance.reports.general-ledger', ['account_id' => $account->id]) }}" class="btn btn-sm btn-subtle-info" title="كشف الحساب بالأستاذ العام">
                                            <i class="ri-file-list-line"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->hasPermission('admin.accounts.update'))
                                        <a href="{{ route('admin.finance.accounts.edit', $account) }}" class="btn btn-sm btn-subtle-primary" title="تعديل">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->hasPermission('admin.accounts.toggle'))
                                        <form method="POST" action="{{ route('admin.finance.accounts.toggle', $account) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $account->is_active ? 'btn-subtle-danger' : 'btn-subtle-success' }}" title="{{ $account->is_active ? 'تعطيل الحساب' : 'تفعيل الحساب' }}">
                                                <i class="{{ $account->is_active ? 'ri-close-circle-line' : 'ri-checkbox-circle-line' }}"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="ri-inbox-line fs-36 d-block mb-2 text-secondary"></i>
                                لا توجد حسابات مطابقة للبحث
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $accounts->links() }}
        </div>
    </div>
</div>
@endsection
