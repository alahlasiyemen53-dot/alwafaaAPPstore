@extends('layouts.admin')
@section('title', 'إدارة '.$deliveryMethod->name_ar.' | الوفاء استور')
@section('page-title', 'المحافظات والتسعير')
@section('content')
<div class="page-heading">
    <div>
        <h2 class="fs-18 mb-1">{{ $deliveryMethod->name_ar }}</h2>
        <p class="mb-0 text-muted">المحافظات والتسعير لطريقة التوصيل <span dir="ltr">({{ $deliveryMethod->code }})</span></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-light" href="{{ route('admin.master-data.delivery-methods.index') }}"><i class="ri-arrow-right-line me-1"></i> العودة للطرق</a>
        @if(auth()->user()->hasPermission('admin.locations.create'))
            <a class="btn btn-primary" href="{{ route('admin.master-data.governorate-delivery-methods.create', ['delivery_method_id' => $deliveryMethod->id]) }}"><i class="ri-add-line me-1"></i> ربط محافظة</a>
        @endif
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>المحافظة</th>
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
                            <td colspan="5">
                                <div class="text-center py-5">
                                    <h5 class="fs-16 mb-1">لا توجد محافظات مربوطة</h5>
                                    <p class="text-muted mb-0">اربط محافظة وحدد رسوم التوصيل لهذه الطريقة.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($assignments->hasPages())
        <div class="card-footer">{{ $assignments->links() }}</div>
    @endif
</div>
@endsection
