@extends('layouts.admin')
@section('title', 'ربط طرق التوصيل بالمحافظات | لمّة')
@section('page-title', 'ربط طرق التوصيل بالمحافظات')
@section('content')
<div class="page-heading">
    <div>
        <h2 class="fs-18 mb-1">ربط طرق التوصيل بالمحافظات</h2>
        <p>تحديد رسوم كل طريقة توصيل لكل محافظة.</p>
    </div>
    @if(auth()->user()->hasPermission('admin.locations.create'))
        <a class="btn btn-primary" href="{{ route('admin.master-data.governorate-delivery-methods.create') }}"><i class="ri-add-line me-1"></i> إضافة ربط</a>
    @endif
</div>
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end filter-row">
            <div class="col-md-5">
                <label for="governorate_id" class="form-label">المحافظة</label>
                <select class="form-control" data-choices id="governorate_id" name="governorate_id">
                    <option value="">كل المحافظات</option>
                    @foreach($governorates as $governorate)
                        <option value="{{ $governorate->id }}" @selected((string) ($filters['governorate_id'] ?? '') === (string) $governorate->id)>{{ $governorate->name_ar }}</option>
                    @endforeach
                </select>
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
                        <th>المحافظة</th>
                        <th>طريقة التوصيل</th>
                        <th>الرسوم</th>
                        <th>الترتيب</th>
                        <th>الحالة</th>
                        <th class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->governorate?->name_ar ?? '—' }}</td>
                            <td>{{ $assignment->deliveryMethod?->name_ar ?? '—' }} <span class="text-muted fs-12" dir="ltr">({{ $assignment->deliveryMethod?->code }})</span></td>
                            <td dir="ltr">{{ number_format((float) $assignment->delivery_fee, 2) }}</td>
                            <td>{{ $assignment->sort_order }}</td>
                            <td>
                                <span class="badge {{ $assignment->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                    {{ $assignment->is_active ? 'نشط' : 'معطل' }}
                                </span>
                            </td>
                            <td class="actions-cell text-end">
                                @if(auth()->user()->hasPermission('admin.locations.update'))
                                    <a class="btn btn-sm btn-soft-primary" href="{{ route('admin.master-data.governorate-delivery-methods.edit', $assignment) }}" aria-label="تعديل"><i class="ri-pencil-line"></i></a>
                                @endif
                                @if($assignment->is_active && auth()->user()->hasPermission('admin.locations.deactivate'))
                                    <form method="POST" action="{{ route('admin.master-data.governorate-delivery-methods.deactivate', $assignment) }}" data-confirm="سيتم تعطيل هذا الربط. هل تريد المتابعة؟">@csrf @method('PATCH')<button class="btn btn-sm btn-soft-danger" type="submit"><i class="ri-forbid-line"></i></button></form>
                                @elseif(!$assignment->is_active && auth()->user()->hasPermission('admin.locations.activate'))
                                    <form method="POST" action="{{ route('admin.master-data.governorate-delivery-methods.activate', $assignment) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-soft-success" type="submit"><i class="ri-checkbox-circle-line"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="text-center py-5">
                                    <h5 class="fs-16 mb-1">لا توجد سجلات</h5>
                                    <p class="text-muted mb-0">ابدأ بإضافة أول ربط بين محافظة وطريقة توصيل.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">{{ $assignments->links() }}</div>
</div>
@endsection
