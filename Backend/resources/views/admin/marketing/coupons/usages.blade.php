@extends('layouts.admin')

@section('title', 'سجل استخدامات الكوبون: ' . $coupon->code)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">سجل استخدامات الكوبون: <span class="font-monospace text-primary">{{ $coupon->code }}</span></h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.marketing.coupons.index') }}">كوبونات الخصم</a></li>
                        <li class="breadcrumb-item active">سجل الاستخدامات</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">{{ $coupon->name }}</h5>
                        <p class="text-muted mb-0 small">
                            إجمالي الاستخدامات النشطة: <span class="badge bg-primary">{{ $coupon->active_usages_count }}</span>
                            من أصل <span class="fw-bold">{{ $coupon->usage_limit ?? 'غير محدود' }}</span>
                        </p>
                    </div>
                    <a href="{{ route('admin.marketing.coupons.index') }}" class="btn btn-light btn-sm">
                        <i class="ri-arrow-right-line me-1"></i> العودة لقائمة الكوبونات
                    </a>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive table-card mb-1 mt-3">
                        <table class="table table-nowrap align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>مبلغ الخصم الفعلي</th>
                                    <th>العملة</th>
                                    <th>تاريخ الاستخدام</th>
                                    <th>الحالة</th>
                                    <th>تاريخ التحرير / الإلغاء</th>
                                    <th>سبب التحرير</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($usages as $usage)
                                    <tr>
                                        <td>
                                            @if($usage->order)
                                                <a href="{{ route('admin.orders.show', $usage->order) }}" class="fw-medium font-monospace">
                                                    {{ $usage->order->order_number }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $usage->customer?->name ?? 'غير محدد' }}</td>
                                        <td class="fw-semibold text-danger">{{ number_format((float) $usage->discount_amount_snapshot, 2) }}</td>
                                        <td>{{ $usage->currency?->code ?? '-' }}</td>
                                        <td>{{ $usage->used_at ? $usage->used_at->format('Y-m-d H:i') : '-' }}</td>
                                        <td>
                                            @if($usage->status->value === 'active')
                                                <span class="badge bg-success-subtle text-success">مستهلك (نشط)</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">محرر (ملغي)</span>
                                            @endif
                                        </td>
                                        <td>{{ $usage->released_at ? $usage->released_at->format('Y-m-d H:i') : '-' }}</td>
                                        <td><small class="text-muted">{{ $usage->release_reason ?? '-' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            لم يتم تسجيل أي استخدامات فعلية لهذا الكوبون حتى الآن.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                        {{ $usages->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
