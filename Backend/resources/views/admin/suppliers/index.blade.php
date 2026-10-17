@extends('layouts.admin')
@section('title', 'إدارة الموردين | الوفاء استور')
@section('page-title', 'الموردون')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0"><i class="ri-truck-line me-1 text-primary"></i> قائمة الموردين ({{ $suppliers->total() }})</h4>
                @if(auth()->user()->hasPermission('admin.suppliers.create'))
                    <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">
                        <i class="ri-add-line me-1"></i> إضافة مورد جديد
                    </a>
                @endif
            </div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.suppliers.index') }}" class="row g-2 filter-row">
                    <div class="col-md-5">
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="البحث بالاسم، الكود، الهاتف، أو جهة الاتصال...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" data-choices data-choices-search-false name="status">
                            <option value="">جميع الحالات</option>
                            <option value="active" @selected(request('status') === 'active')>نشط فقط</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>معطل فقط</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i> تصفية</button>
                        @if(request()->hasAny(['search', 'status']))
                            <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light"><i class="ri-close-line me-1"></i> إعادة ضبط</a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>الكود</th>
                                <th>اسم المورد</th>
                                <th>جهة الاتصال</th>
                                <th>الهاتف</th>
                                <th>فواتير الشراء</th>
                                <th>الحالة</th>
                                <th class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($suppliers as $supplier)
                                <tr>
                                    <td><code class="fw-bold text-dark fs-13">{{ $supplier->code }}</code></td>
                                    <td>
                                        <span class="fw-semibold text-dark fs-14">{{ $supplier->name }}</span>
                                        @if($supplier->email)
                                            <div class="text-muted fs-12" dir="ltr">{{ $supplier->email }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $supplier->contact_person ?: '—' }}</td>
                                    <td><span dir="ltr">{{ $supplier->phone }}</span></td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle fs-12">
                                            {{ $supplier->purchase_invoices_count }} فاتورة
                                        </span>
                                    </td>
                                    <td>
                                        @if($supplier->is_active)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">معطل</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if(auth()->user()->hasPermission('admin.suppliers.statement.view'))
                                                <a href="{{ route('admin.suppliers.statement', $supplier) }}" class="btn btn-sm btn-soft-info" title="كشف حساب المورد">
                                                    <i class="ri-file-list-line me-1"></i> كشف الحساب
                                                </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('admin.suppliers.update'))
                                                <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-sm btn-soft-primary" title="تعديل">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('admin.suppliers.activate') || auth()->user()->hasPermission('admin.suppliers.deactivate'))
                                                <form method="POST" action="{{ route('admin.suppliers.toggle-status', $supplier) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm {{ $supplier->is_active ? 'btn-soft-danger' : 'btn-soft-success' }}" title="{{ $supplier->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                        <i class="{{ $supplier->is_active ? 'ri-stop-circle-line' : 'ri-checkbox-circle-line' }}"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="text-center py-5">
                                            <div class="avatar-md mx-auto mb-3">
                                                <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                                    <i class="ri-truck-line"></i>
                                                </div>
                                            </div>
                                            <h5 class="fs-16 mb-1">لا يوجد موردون مطابقون</h5>
                                            <p class="text-muted mb-0">أضف مورداً جديداً أو غيّر معايير البحث.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($suppliers->hasPages())
                    <div class="card-footer py-2">
                        {{ $suppliers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
