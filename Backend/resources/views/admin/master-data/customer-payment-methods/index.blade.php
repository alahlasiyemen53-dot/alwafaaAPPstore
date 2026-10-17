@extends('layouts.admin')
@section('title', 'طرق دفع العملاء | لمّة')
@section('page-title', 'طرق دفع العملاء')
@section('content')
<div class="page-heading">
    <div>
        <h2 class="fs-18 mb-1">طرق دفع العملاء</h2>
        <p>إدارة طرق الدفع المعروضة في التطبيق (COD، جيب، فلوسك، …).</p>
    </div>
    @if(auth()->user()->hasPermission('admin.customer_payment_methods.create'))
        <a class="btn btn-primary" href="{{ route('admin.master-data.customer-payment-methods.create') }}"><i class="ri-add-line me-1"></i> إضافة طريقة</a>
    @endif
</div>
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end filter-row">
            <div class="col-md-7">
                <label for="search" class="form-label">البحث</label>
                <input class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="ابحث بالرمز أو الاسم">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">الحالة</label>
                <select class="form-control" data-choices data-choices-search-false id="status" name="status">
                    <option value="">الكل</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>نشط</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>معطل</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-soft-primary w-100" type="submit"><i class="ri-search-line me-1"></i> تطبيق</button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الرمز</th>
                        <th>الاسم</th>
                        <th>المزوّد</th>
                        <th>القناة</th>
                        <th>التدفق</th>
                        <th>الترتيب</th>
                        <th>الحالة</th>
                        <th class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($methods as $method)
                        <tr>
                            <td dir="ltr">{{ $method->code }}</td>
                            <td>{{ $method->name_ar }}</td>
                            <td dir="ltr">{{ $method->provider_code?->value }}</td>
                            <td>{{ $method->channel?->label() }}</td>
                            <td>{{ $method->flow_type?->label() }}</td>
                            <td>{{ $method->sort_order }}</td>
                            <td>
                                <span class="badge {{ $method->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                    {{ $method->is_active ? 'نشط' : 'معطل' }}
                                </span>
                            </td>
                            <td class="actions-cell text-end">
                                @if(auth()->user()->hasPermission('admin.customer_payment_methods.update'))
                                    <a class="btn btn-sm btn-soft-primary" href="{{ route('admin.master-data.customer-payment-methods.edit', $method) }}" aria-label="تعديل"><i class="ri-pencil-line"></i></a>
                                @endif
                                @if($method->is_active && auth()->user()->hasPermission('admin.customer_payment_methods.deactivate'))
                                    <form method="POST" action="{{ route('admin.master-data.customer-payment-methods.deactivate', $method) }}" data-confirm="سيتم تعطيل طريقة الدفع. هل تريد المتابعة؟">@csrf @method('PATCH')<button class="btn btn-sm btn-soft-danger" type="submit"><i class="ri-forbid-line"></i></button></form>
                                @elseif(!$method->is_active && auth()->user()->hasPermission('admin.customer_payment_methods.activate'))
                                    <form method="POST" action="{{ route('admin.master-data.customer-payment-methods.activate', $method) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-soft-success" type="submit"><i class="ri-checkbox-circle-line"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="text-center py-5">
                                    <h5 class="fs-16 mb-1">لا توجد طرق دفع</h5>
                                    <p class="text-muted mb-0">ابدأ بإضافة أول طريقة دفع للعملاء.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($methods->hasPages())
        <div class="card-body border-top">{{ $methods->links() }}</div>
    @endif
</div>
@endsection
