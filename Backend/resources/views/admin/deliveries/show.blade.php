@extends('layouts.admin')
@section('title', 'تفاصيل الشحنة ' . $delivery->delivery_number . ' | لمّة')
@section('page-title', 'تفاصيل الشحنة')

@section('content')
<div class="row">
    <div class="col-12 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1 text-dark">
                    <i class="ri-e-bike-2-line me-1 text-primary"></i> الشحنة رقم: <code>{{ $delivery->delivery_number }}</code>
                </h4>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-{{ $delivery->status->badgeClass() }}-subtle text-{{ $delivery->status->badgeClass() }} fs-13">
                        {{ $delivery->status->label() }}
                    </span>
                    <span class="text-muted fs-12">
                        <i class="ri-calendar-line me-1"></i> تاريخ الإسناد: {{ $delivery->assigned_at?->format('Y-m-d H:i') }}
                    </span>
                    @if($delivery->delivered_at)
                        <span class="text-success fs-12">
                            <i class="ri-checkbox-circle-line me-1"></i> تاريخ التسليم: {{ $delivery->delivered_at?->format('Y-m-d H:i') }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.deliveries.index') }}" class="btn btn-light">
                    <i class="ri-arrow-right-line me-1"></i> العودة لقائمة الشحنات
                </a>

                @if($delivery->status === \App\Enums\DeliveryStatus::Assigned)
                    @if(auth()->user()->hasPermission('admin.deliveries.mark_delivered'))
                        <form method="POST" action="{{ route('admin.deliveries.mark-delivered', $delivery) }}" onsubmit="return confirm('هل أنت متأكد من تأكيد تسليم هذا الطلب للعميل؟');">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="ri-checkbox-circle-line me-1"></i> تأكيد التسليم للعميل
                            </button>
                        </form>
                    @endif

                    @if(auth()->user()->hasPermission('admin.deliveries.mark_failed'))
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#markFailedModal">
                            <i class="ri-close-circle-line me-1"></i> تسجيل تعذر التوصيل
                        </button>
                    @endif
                @endif

                @if($delivery->status === \App\Enums\DeliveryStatus::Failed && auth()->user()->hasPermission('admin.deliveries.return_to_warehouse'))
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#returnToWarehouseModal">
                        <i class="ri-inbox-archive-line me-1"></i> استلام المرتجع في المستودع
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

@if($delivery->status === \App\Enums\DeliveryStatus::Failed)
    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
        <i class="ri-error-warning-line fs-20 me-2"></i>
        <div>
            <strong>تنبيه تشغيلي ومخزني:</strong> تعذر تسليم هذه الشحنة للعميل. البضاعة ما زالت <u>في عهدة المندوب</u> ولم تعد بعد للمستودع. رصيد المخزون لا يزيد حتى يتم استلامها فعلياً عبر زر "استلام المرتجع في المستودع".
        </div>
    </div>
@elseif($delivery->status === \App\Enums\DeliveryStatus::ReturnedToWarehouse)
    <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
        <i class="ri-information-line fs-20 me-2"></i>
        <div>
            تم إرجاع بضاعة هذه الشحنة بالكامل إلى مستودع <strong>({{ $delivery->order->warehouse->name_ar ?? 'المستودع الرئيسي' }})</strong>، وتم قيد حركة مخزنية واردة من نوع (مرتجع توصيل) وإعادة الكميات للرصيد المتاح.
        </div>
    </div>
@endif

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="ri-user-star-line me-1 text-primary"></i> بيانات مندوب التوصيل</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted" style="width: 35%;">اسم المندوب:</th>
                            <td class="fw-semibold text-dark">{{ $delivery->driver->name }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">رمز المندوب:</th>
                            <td><code>{{ $delivery->driver->driver_code }}</code></td>
                        </tr>
                        <tr>
                            <th class="text-muted">رقم الهاتف:</th>
                            <td><span dir="ltr" class="fw-medium">{{ $delivery->driver->phone }}</span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">نوع المركبة:</th>
                            <td>{{ $delivery->driver->vehicle_type ?: 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">رقم اللوحة:</th>
                            <td><code>{{ $delivery->driver->vehicle_plate_number ?: '-' }}</code></td>
                        </tr>
                        <tr>
                            <th class="text-muted">المسؤول عن الإسناد:</th>
                            <td>{{ $delivery->creator->name ?? '-' }}</td>
                        </tr>
                        @if($delivery->notes)
                            <tr>
                                <th class="text-muted">ملاحظات الإسناد:</th>
                                <td>{{ $delivery->notes }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="ri-file-list-3-line me-1 text-primary"></i> بيانات الطلب والمستلم</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted" style="width: 35%;">رقم الطلب:</th>
                            <td>
                                <a href="{{ route('admin.orders.show', $delivery->order) }}" class="fw-bold text-primary">
                                    <code>{{ $delivery->order->order_number }}</code>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">اسم المستلم:</th>
                            <td class="fw-semibold text-dark">{{ $delivery->recipient_name }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">هاتف المستلم:</th>
                            <td><span dir="ltr" class="fw-medium">{{ $delivery->recipient_phone }}</span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">مستودع الصرف:</th>
                            <td>{{ $delivery->order->warehouse->name_ar ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">عنوان التوصيل:</th>
                            <td>{{ $delivery->shipping_address_snapshot }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">مبلغ التحصيل (COD):</th>
                            <td>
                                <span class="badge bg-primary-subtle text-primary fs-14">
                                    {{ number_format((float)$delivery->cod_amount, 2) }} {{ $delivery->order->currency->code ?? '' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="ri-archive-line me-1 text-primary"></i> الأصناف المسلّمة للمندوب في هذه الشحنة</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>الصنف / المتغير</th>
                                <th>رمز الصنف (SKU)</th>
                                <th>الكمية المسلمة للمندوب</th>
                                <th>سعر الوحدة</th>
                                <th>الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($delivery->order->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $item->variant_name_snapshot }}</span>
                                    </td>
                                    <td><code>{{ $item->sku_snapshot }}</code></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary fs-13">{{ $item->quantity }}</span></td>
                                    <td>{{ number_format((float)$item->unit_price, 2) }} {{ $delivery->order->currency->code ?? '' }}</td>
                                    <td>{{ number_format((float)$item->line_total, 2) }} {{ $delivery->order->currency->code ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="5" class="text-end">الإجمالي الكلي للطلب:</th>
                                <th>{{ number_format((float)$delivery->order->total, 2) }} {{ $delivery->order->currency->code ?? '' }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($delivery->failed_at)
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-header bg-danger-subtle">
                    <h5 class="card-title mb-0 text-danger"><i class="ri-history-line me-1"></i> تفاصيل تعذر التوصيل</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>سبب التعذر</th>
                                    <th>ملاحظات المندوب / المشرف</th>
                                    <th>المسؤول عن التوثيق</th>
                                    <th>تاريخ التوثيق</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <span class="badge bg-danger-subtle text-danger fs-12">
                                            {{ $delivery->failure_reason_code?->label() ?? 'غير محدد' }}
                                        </span>
                                    </td>
                                    <td>{{ $delivery->failure_notes ?: '-' }}</td>
                                    <td>{{ $delivery->failedBy->name ?? '-' }}</td>
                                    <td><span class="text-muted fs-12">{{ $delivery->failed_at?->format('Y-m-d H:i') }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Modal: Mark Failed -->
@if($delivery->status === \App\Enums\DeliveryStatus::Assigned)
    <div class="modal fade" id="markFailedModal" tabindex="-1" aria-labelledby="markFailedModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.deliveries.mark-failed', $delivery) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="markFailedModalLabel"><i class="ri-close-circle-line me-1 text-danger"></i> تسجيل تعذر التوصيل</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning fs-13">
                            <strong>ملاحظة هامة:</strong> تسجيل تعذر التوصيل لن يعيد الأصناف للمخزن تلقائياً؛ ستبقى الشحنة بعهدة المندوب حتى استلامها في المستودع.
                        </div>
                        <div class="mb-3">
                            <label for="failure_reason" class="form-label">سبب تعذر التوصيل <span class="text-danger">*</span></label>
                            <select class="form-select" id="failure_reason" name="failure_reason" required>
                                <option value="">اختر السبب...</option>
                                @foreach($failureReasons as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات توضيحية</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="أدخل أي تفاصيل إضافية من المندوب..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">تأكيد تعذر التوصيل</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- Modal: Return to Warehouse -->
@if($delivery->status === \App\Enums\DeliveryStatus::Failed)
    <div class="modal fade" id="returnToWarehouseModal" tabindex="-1" aria-labelledby="returnToWarehouseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.deliveries.return-to-warehouse', $delivery) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="returnToWarehouseModalLabel"><i class="ri-inbox-archive-line me-1 text-warning"></i> استلام المرتجع في المستودع</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info fs-13">
                            عند تأكيد الاستلام، سيتم إنهاء عهدة المندوب، وقيد حركة مخزنية واردة (مرتجع توصيل) إلى مستودع <strong>({{ $delivery->order->warehouse->name_ar ?? 'المستودع' }})</strong>، وزيادة رصيد المخزون الفعلي.
                        </div>
                        <div class="mb-3">
                            <label for="return_notes" class="form-label">ملاحظات أمين المستودع عند الاستلام</label>
                            <textarea class="form-control" id="return_notes" name="notes" rows="3" placeholder="حالة الطرد والبضاعة عند الفحص والاستلام..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-warning">تأكيد الاستلام بالمستودع</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection
