@extends('layouts.admin')
@section('title', 'طلبات الشراء تحت الطلب | لمّة')
@section('page-title', 'طلبات الشراء (الأصناف تحت الطلب)')

@section('content')
<!-- Metrics Row -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate border-start border-warning border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-12">بانتظار الإسناد والتوفير</p>
                        <h4 class="fs-22 fw-bold mb-0 mt-2 text-warning">{{ number_format($metrics['pending']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20">
                            <i class="ri-time-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate border-start border-primary border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-12">مسند لمندوب للتوفير</p>
                        <h4 class="fs-22 fw-bold mb-0 mt-2 text-primary">{{ number_format($metrics['assigned']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                            <i class="ri-truck-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate border-start border-success border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-12">تم الاستلام والتجهيز</p>
                        <h4 class="fs-22 fw-bold mb-0 mt-2 text-success">{{ number_format($metrics['collected']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                            <i class="ri-checkbox-circle-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card card-animate border-start border-danger border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-12">تعذر التوفير</p>
                        <h4 class="fs-22 fw-bold mb-0 mt-2 text-danger">{{ number_format($metrics['failed']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-20">
                            <i class="ri-error-warning-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Card -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">
                    <i class="ri-shopping-basket-line me-1 text-primary"></i> طلبات الشراء المرتبطة بطلبات العملاء ({{ $requirements->total() }})
                </h4>
                <div class="text-muted fs-12">
                    تُنشأ تلقائياً عند تأكيد خدمة العملاء لطلب يحتوي أصنافاً تحت الطلب — مع كود المورد واسم الصنف والكمية
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.sourcing.index') }}" class="row g-2 align-items-center filter-row">
                    <div class="col-md-3">
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالطلب، SKU، المورد، المندوب...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">جميع الحالات</option>
                            @foreach(\App\Enums\SourcingStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="supplier_id">
                            <option value="">جميع الموردين</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string)($filters['supplier_id'] ?? '') === (string)$supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="driver_id">
                            <option value="">جميع المناديب</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected((string)($filters['driver_id'] ?? '') === (string)$driver->id)>
                                    {{ $driver->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-search-line me-1"></i> تصفية</button>
                        @if(!empty(array_filter($filters)))
                            <a href="{{ route('admin.sourcing.index') }}" class="btn btn-light" title="إعادة تعيين"><i class="ri-close-line"></i></a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>رقم الطلب</th>
                                <th>العميل</th>
                                <th>المنتج / المتغير</th>
                                <th class="text-center">الكمية</th>
                                <th>كود المورد</th>
                                <th>المورد المعتمد</th>
                                <th>المندوب المكلف</th>
                                <th>الحالة</th>
                                <th>تاريخ الطلب</th>
                                <th class="text-end" style="min-width: 180px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requirements as $req)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $req->order_id) }}" class="fw-bold text-primary">
                                            <code>{{ $req->order->order_number ?? ('#'.$req->order_id) }}</code>
                                        </a>
                                        @if($req->order)
                                            <div class="fs-11 text-muted">{{ $req->order->status->label() }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-medium text-dark">{{ $req->order?->customer?->name ?? $req->order?->shipping_name ?? '—' }}</span>
                                        @if($req->order?->customer?->phone ?? $req->order?->shipping_phone)
                                            <div class="text-muted fs-11" dir="ltr">{{ $req->order?->customer?->phone ?? $req->order?->shipping_phone }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $req->orderItem?->product_name ?? '—' }}</div>
                                        <div class="fs-11 text-muted">
                                            <code class="text-dark">{{ $req->orderItem?->sku_snapshot ?? '—' }}</code>
                                            @php
                                                $supplierItemNo = $req->orderItem?->supplier_item_number_snapshot
                                                    ?? $req->productVariant?->supplier_item_number
                                                    ?? $req->productVariant?->product?->supplier_item_number;
                                            @endphp
                                            @if($supplierItemNo)
                                                <div dir="ltr" class="mt-1"><span class="badge bg-light text-muted border"># {{ $supplierItemNo }}</span></div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border fs-12 px-2 py-1">
                                            <strong>{{ $req->quantity_requested }}</strong> قطعة
                                        </span>
                                    </td>
                                    <td>
                                        @if($req->supplier?->code)
                                            <span class="badge bg-dark-subtle text-dark border" dir="ltr">{{ $req->supplier->code }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-medium text-dark">{{ $req->supplier->name ?? '—' }}</span>
                                        @if($req->supplier?->phone)
                                            <div class="fs-11 text-muted" dir="ltr">{{ $req->supplier->phone }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($req->assignedDriver)
                                            <span class="fw-medium text-dark">{{ $req->assignedDriver->name }}</span>
                                            <div class="fs-11 text-muted" dir="ltr">{{ $req->assignedDriver->phone }}</div>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border">غير مسند</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $req->status->badgeClass() }} fs-12">
                                            {{ $req->status->label() }}
                                        </span>
                                        @if($req->isFailed() && $req->failure_reason)
                                            <div class="fs-11 text-danger mt-1" title="{{ $req->failure_reason }}">
                                                <i class="ri-error-warning-line me-1"></i>{{ \Illuminate\Support\Str::limit($req->failure_reason, 25) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-muted fs-12">{{ $req->created_at?->format('Y-m-d H:i') }}</span>
                                        @if($req->assigned_at)
                                            <div class="fs-11 text-info">أُسند: {{ $req->assigned_at->format('H:i') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1 flex-wrap">
                                            @if($req->isPending())
                                                @if(auth()->user()->hasPermission('admin.sourcing.manage'))
                                                    <button type="button" class="btn btn-sm btn-soft-primary" data-bs-toggle="modal" data-bs-target="#assignDriverModal{{ $req->id }}" title="إسناد لمندوب">
                                                        <i class="ri-user-shared-line me-1"></i> إسناد
                                                    </button>
                                                    @if($req->order?->pickup_driver_id)
                                                        <form method="POST" action="{{ route('admin.sourcing.confirm-collected', $req) }}" class="d-inline" data-confirm="هل تم استلام وتجهيز الكمية المطلوبة من المورد بالفعل؟">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-soft-success" title="تأكيد الاستلام المباشر">
                                                                <i class="ri-check-line"></i> استلام
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="badge bg-warning-subtle text-warning border align-self-center">أسند مندوب التوصيل للطلب أولاً</span>
                                                    @endif
                                                    <button type="button" class="btn btn-sm btn-soft-danger" data-bs-toggle="modal" data-bs-target="#markFailedModal{{ $req->id }}" title="تعذر التوفير">
                                                        <i class="ri-close-circle-line"></i>
                                                    </button>
                                                @endif
                                            @elseif($req->isAssigned())
                                                @if(auth()->user()->hasPermission('admin.sourcing.manage'))
                                                    @if($req->order?->pickup_driver_id)
                                                        <form method="POST" action="{{ route('admin.sourcing.confirm-collected', $req) }}" class="d-inline" data-confirm="تأكيد قيام المندوب باستلام الصنف من المورد بنجاح؟">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success" title="تأكيد استلام المندوب">
                                                                <i class="ri-checkbox-circle-line me-1"></i> تم الاستلام
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="badge bg-warning-subtle text-warning border align-self-center">أسند مندوب التوصيل للطلب أولاً</span>
                                                    @endif
                                                    <button type="button" class="btn btn-sm btn-soft-primary" data-bs-toggle="modal" data-bs-target="#assignDriverModal{{ $req->id }}" title="تغيير المندوب">
                                                        <i class="ri-user-received-line"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-soft-danger" data-bs-toggle="modal" data-bs-target="#markFailedModal{{ $req->id }}" title="تعذر التوفير">
                                                        <i class="ri-close-circle-line"></i>
                                                    </button>
                                                @endif
                                            @elseif($req->isCollected())
                                                <span class="text-success fs-12"><i class="ri-checkbox-circle-fill me-1"></i> جاهز للتسليم</span>
                                            @elseif($req->isFailed())
                                                <span class="text-danger fs-12"><i class="ri-error-warning-fill me-1"></i> متعذر</span>
                                            @elseif($req->isCancelled())
                                                <span class="text-muted fs-12"><i class="ri-forbid-line me-1"></i> ملغي</span>
                                            @endif
                                        </div>

                                        <!-- Modals for this requirement -->
                                        @if($req->isPending() || $req->isAssigned())
                                            <!-- Assign Driver Modal -->
                                            <div class="modal fade text-start" id="assignDriverModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <form method="POST" action="{{ route('admin.sourcing.assign-driver', $req) }}">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title"><i class="ri-user-shared-line me-1 text-primary"></i> إسناد مهمة توفير لمندوب</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="alert alert-light border fs-12 mb-3">
                                                                    <strong>الطلب:</strong> {{ $req->order?->order_number }}<br>
                                                                    <strong>الصنف:</strong> {{ $req->orderItem?->product_name }} ({{ $req->orderItem?->variant_sku }})<br>
                                                                    <strong>المطلوب:</strong> {{ $req->quantity_requested }} قطعة من المورد <strong>{{ $req->supplier->name }}</strong>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">اختر مندوب التوفير <span class="text-danger">*</span></label>
                                                                    <select class="form-select" name="driver_id" required>
                                                                        <option value="">-- اختر المندوب --</option>
                                                                        @foreach($drivers as $driver)
                                                                            <option value="{{ $driver->id }}" @selected($req->driver_id === $driver->id)>
                                                                                {{ $driver->name }} ({{ $driver->driver_code }})
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">ملاحظات للمندوب (اختياري)</label>
                                                                    <textarea class="form-control" name="notes" rows="2" placeholder="موقع المورد، تفاصيل خاصة...">{{ $req->notes }}</textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                                                                <button type="submit" class="btn btn-primary">حفظ الإسناد</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Mark Failed Modal -->
                                            <div class="modal fade text-start" id="markFailedModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <form method="POST" action="{{ route('admin.sourcing.mark-failed', $req) }}">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title text-danger"><i class="ri-error-warning-line me-1"></i> تسجيل تعذر التوفير</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="alert alert-danger py-2 fs-12 mb-3">
                                                                    سيتم وسم مهمة التوفير كمتعذرة. الطلب المرتبط لن ينتقل للتسليم حتى حل المشكلة.
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">سبب تعذر التوفير <span class="text-danger">*</span></label>
                                                                    <textarea class="form-control" name="failure_reason" rows="3" required placeholder="مثال: نفاد الكمية لدى المورد، إغلاق المحل، تغيير السعر..."></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                                                                <button type="submit" class="btn btn-danger">تأكيد التعذر</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-3">
                                            <div class="avatar-title bg-light text-primary rounded-circle fs-32">
                                                <i class="ri-shopping-basket-line"></i>
                                            </div>
                                        </div>
                                        <h5 class="fs-15 mb-1">لا توجد مهام توفير مطابقة</h5>
                                        <p class="text-muted fs-12 mb-0">طابور التوفير فارغ حالياً أو لا توجد نتائج تطابق شروط التصفية المحددة.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($requirements->hasPages())
                <div class="card-footer py-2">
                    {{ $requirements->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
