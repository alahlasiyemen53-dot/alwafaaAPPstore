@extends('layouts.admin')

@section('title', 'طلبات المبيعات | الوفاء استور')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h4 class="card-title mb-0">
                        <i class="ri-shopping-cart-2-line me-1 text-primary"></i> طلبات المبيعات ({{ $orders->total() }})
                    </h4>
                    @if(auth()->user()->hasPermission('admin.orders.create'))
                        <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">
                            <i class="ri-add-line me-1"></i> إنشاء طلب جديد
                        </a>
                    @endif
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-3 filter-row">
                    <div class="col-md-3">
                        <label class="form-label fs-12 text-muted">البحث (رقم الطلب / العميل / الهاتف)</label>
                        <input type="text" name="search" class="form-control" placeholder="ابحث برقم الطلب أو العميل..." value="{{ request('search') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">المستودع</label>
                        <select name="warehouse_id" class="form-select">
                            <option value="">جميع المستودعات</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">الحالة</label>
                        <select name="status" class="form-select">
                            <option value="">جميع الحالات</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">العملة</label>
                        <select name="currency_id" class="form-select">
                            <option value="">جميع العملات</option>
                            @foreach($currencies as $curr)
                                <option value="{{ $curr->id }}" @selected(request('currency_id') == $curr->id)>{{ $curr->name_ar }} ({{ $curr->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-12 text-muted">حالة الدفع</label>
                        <select name="payment_status" class="form-select">
                            <option value="">الكل</option>
                            @foreach($paymentStatuses as $paymentStatus)
                                <option value="{{ $paymentStatus->value }}" @selected(request('payment_status') === $paymentStatus->value)>{{ $paymentStatus->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ri-search-line me-1"></i> بحث وتصفية
                        </button>
                        @if(request()->hasAny(['search', 'warehouse_id', 'status', 'currency_id', 'payment_status', 'from_date', 'to_date']))
                            <a href="{{ route('admin.orders.index') }}" class="btn btn-light" title="إعادة ضبط">
                                <i class="ri-refresh-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" style="width: 130px;">رقم الطلب</th>
                                <th scope="col">العميل</th>
                                <th scope="col">المستودع</th>
                                <th scope="col" class="text-center">البنود</th>
                                <th scope="col">الإجمالي</th>
                                <th scope="col">طريقة الدفع</th>
                                <th scope="col">حالة الدفع</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">تاريخ الإنشاء</th>
                                <th scope="col" class="text-end" style="width: 140px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $order) }}" class="fw-semibold text-primary">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium text-dark">{{ $order->customer_name_snapshot }}</span>
                                            <small class="text-muted d-flex align-items-center gap-1">
                                                <i class="ri-phone-line fs-11"></i> {{ $order->customer_phone_snapshot }}
                                                @if($order->customer && $order->customer->isPhoneVerified())
                                                    <span class="badge bg-success-subtle text-success p-1" title="هاتف موثق"><i class="ri-checkbox-circle-fill"></i></span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning p-1" title="هاتف غير موثق"><i class="ri-error-warning-line"></i></span>
                                                @endif
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $order->warehouse?->name_ar ?? 'غير محدد' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">{{ $order->items_count }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark">{{ number_format($order->total, 2) }} {{ $order->currency?->symbol ?? $order->currency?->code }}</span>
                                            @if($order->discount_amount > 0)
                                                <small class="text-danger">خصم: {{ number_format($order->discount_amount, 2) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-medium text-dark">{{ $order->paymentMethodLabel() }}</span>
                                        @if($order->payment_provider_reference)
                                            <div class="text-muted fs-11" dir="ltr">{{ $order->payment_provider_reference }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @php($paymentStatus = $order->paymentStatusOrDefault())
                                        <span class="badge {{ $paymentStatus->badgeClass() }}">
                                            {{ $paymentStatus->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $order->status->badgeClass() }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted fs-12">{{ $order->created_at->format('d-m-Y H:i') }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-subtle-primary" title="عرض التفاصيل">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @if($order->canBeEdited() && auth()->user()->hasPermission('admin.orders.update'))
                                                <a href="{{ route('admin.orders.edit', $order) }}" class="btn btn-sm btn-subtle-secondary" title="تعديل المسودة">
                                                    <i class="ri-pencil-line"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-3">
                                            <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                                <i class="ri-shopping-bag-line"></i>
                                            </div>
                                        </div>
                                        <h5 class="text-muted">لا توجد طلبات مبيعات مسجلة</h5>
                                        <p class="text-muted mb-3">لم يتم العثور على أي طلبات تطابق معايير البحث أو لم يتم إنشاء طلبات بعد.</p>
                                        @if(auth()->user()->hasPermission('admin.orders.create'))
                                            <a href="{{ route('admin.orders.create') }}" class="btn btn-sm btn-primary">
                                                <i class="ri-add-line me-1"></i> إنشاء أول طلب مبيعات
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($orders->hasPages())
                <div class="card-footer border-top">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="text-muted fs-13">
                            عرض {{ $orders->firstItem() }} إلى {{ $orders->lastItem() }} من أصل {{ $orders->total() }} طلب
                        </div>
                        <div>
                            {{ $orders->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
