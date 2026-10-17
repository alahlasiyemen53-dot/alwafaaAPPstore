@extends('layouts.admin')

@section('title', 'تفاصيل طلب المبيعات: ' . $order->order_number . ' | لمّة')

@section('content')
<div class="row">
    <div class="col-12">
        <!-- Order Header Card -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h4 class="card-title mb-0">
                            <i class="ri-shopping-cart-2-line me-1 text-primary"></i> طلب مبيعات: <span class="text-primary">{{ $order->order_number }}</span>
                        </h4>
                        <span class="badge {{ $order->status->badgeClass() }} fs-13">
                            {{ $order->status->label() }}
                        </span>
                        @php($paymentStatus = $order->paymentStatusOrDefault())
                        <span class="badge {{ $paymentStatus->badgeClass() }} fs-13">
                            {{ $paymentStatus->label() }}
                        </span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-13">
                            <i class="ri-wallet-3-line me-1"></i>{{ $order->paymentMethodLabel() }}
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        @if($order->canBeEdited() && auth()->user()->hasPermission('admin.orders.update'))
                            <a href="{{ route('admin.orders.edit', $order) }}" class="btn btn-outline-secondary">
                                <i class="ri-pencil-line me-1"></i> تعديل المسودة
                            </a>
                        @endif

                        @if($order->canBeConfirmed() && auth()->user()->hasPermission('admin.orders.confirm'))
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmOrderModal">
                                <i class="ri-checkbox-circle-line me-1"></i> تأكيد الطلب وحجز المخزون
                            </button>
                        @endif

                        @if($order->canBeAssignedToDriver() && auth()->user()->hasPermission('admin.deliveries.assign'))
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignDriverModal">
                                <i class="ri-e-bike-2-line me-1"></i> إسناد لمندوب التوصيل
                            </button>
                        @endif

                        @if($order->status === \App\Enums\OrderStatus::Confirmed && $order->purchaseOrders->isNotEmpty() && auth()->user()->hasPermission('admin.deliveries.assign'))
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignPickupDriverModal">
                                <i class="ri-truck-line me-1"></i> إسناد مندوب الاستلام من الموردين
                            </button>
                        @endif

                        @if($order->canBeCancelled() && auth()->user()->hasPermission('admin.orders.cancel'))
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                                <i class="ri-close-circle-line me-1"></i> إلغاء الطلب
                            </button>
                        @endif

                        <a href="{{ route('admin.orders.print', $order) }}" target="_blank" class="btn btn-outline-primary">
                            <i class="ri-printer-line me-1"></i> طباعة طلب العميل
                        </a>

                        <a href="{{ route('admin.orders.index') }}" class="btn btn-light">
                            <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                        </a>
                    </div>
                </div>
            </div>

            @if($order->activeDelivery)
                <div class="card-body bg-light border-bottom p-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-title bg-primary-subtle text-primary rounded fs-4 p-2">
                                <i class="ri-e-bike-2-line"></i>
                            </span>
                            <div>
                                <div class="fw-bold text-dark fs-14">
                                    الشحنة الحالية: <a href="{{ route('admin.deliveries.show', $order->activeDelivery) }}" class="text-primary"><code>{{ $order->activeDelivery->delivery_number }}</code></a>
                                </div>
                                <div class="text-muted fs-12">
                                    مندوب التوصيل: <strong>{{ $order->activeDelivery->driver->name }}</strong> (<span dir="ltr">{{ $order->activeDelivery->driver->phone }}</span>) | تاريخ الإسناد: {{ $order->activeDelivery->assigned_at?->format('Y-m-d H:i') }}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-{{ $order->activeDelivery->status->badgeClass() }}-subtle text-{{ $order->activeDelivery->status->badgeClass() }} fs-13">
                                {{ $order->activeDelivery->status->label() }}
                            </span>
                            <a href="{{ route('admin.deliveries.show', $order->activeDelivery) }}" class="btn btn-sm btn-subtle-primary">
                                <i class="ri-eye-line me-1"></i> متابعة الشحنة
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            @if($order->status === \App\Enums\OrderStatus::Cancelled)
                <div class="card-body bg-danger-subtle border-bottom border-danger-subtle p-3">
                    <div class="d-flex align-items-center gap-2 text-danger">
                        <i class="ri-error-warning-fill fs-20"></i>
                        <div>
                            <strong>تم إلغاء هذا الطلب بتاريخ {{ $order->cancelled_at?->format('Y-m-d H:i') }} بواسطة {{ $order->canceller?->name ?? 'النظام' }}.</strong>
                            <div class="fs-13 mt-1">سبب الإلغاء: {{ $order->cancellation_reason }}</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Snapshots Information Row -->
        <div class="row g-3 mb-3">
            <!-- Customer Snapshot Card -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-light py-2">
                        <h6 class="card-title mb-0 fs-13 text-muted">
                            <i class="ri-user-3-line me-1 text-primary"></i> بيانات العميل (لقطة تاريخية)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold fs-15 text-dark">{{ $order->customer_name_snapshot }}</span>
                            @if($order->customer)
                                <a href="{{ route('admin.customers.show', $order->customer) }}" class="btn btn-sm btn-subtle-primary p-1" title="عرض ملف العميل">
                                    <i class="ri-external-link-line"></i> ملف العميل
                                </a>
                            @endif
                        </div>
                        <div class="text-muted fs-13 mb-1">
                            <i class="ri-phone-line me-1"></i> {{ $order->customer_phone_snapshot }}
                        </div>
                        <div class="d-flex align-items-center gap-1 mt-2">
                            <span class="fs-12 text-muted">حالة توثيق هاتف العميل:</span>
                            @if($order->customer && $order->customer->isPhoneVerified())
                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="ri-checkbox-circle-line"></i> موثق ({{ $order->customer->phone_verification_method?->label() }})</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="ri-alert-line"></i> غير موثق</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Address Snapshot Card -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-light py-2">
                        <h6 class="card-title mb-0 fs-13 text-muted">
                            <i class="ri-map-pin-2-line me-1 text-primary"></i> عنوان التوصيل (لقطة تاريخية غير قابلة للتغيير)
                        </h6>
                    </div>
                    <div class="card-body">
                        @if($order->deliveryAddress)
                            <div class="fw-semibold text-dark mb-1">
                                {{ $order->deliveryAddress->contact_name }} ({{ $order->deliveryAddress->contact_phone }})
                            </div>
                            <div class="text-muted fs-13">
                                {{ $order->deliveryAddress->formatted_address_line }}
                            </div>
                            @if($order->deliveryAddress->notes)
                                <div class="mt-2 text-info fs-12">
                                    <i class="ri-sticky-note-line me-1"></i> ملاحظات العنوان: {{ $order->deliveryAddress->notes }}
                                </div>
                            @endif
                        @else
                            <span class="text-muted">لم يتم تسجيل عنوان توصيل</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Fulfillment & Currency Card -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-light py-2">
                        <h6 class="card-title mb-0 fs-13 text-muted">
                            <i class="ri-building-4-line me-1 text-primary"></i> مستودع التنفيذ والعملة والدفع
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <span class="text-muted fs-13">مستودع حجز المخزون:</span>
                            <span class="fw-bold text-dark ms-1">{{ $order->warehouse?->name_ar ?? 'غير محدد' }}</span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted fs-13">عملة العملية:</span>
                            <span class="fw-bold text-primary ms-1">{{ $order->currency?->name_ar }} ({{ $order->currency?->code }})</span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted fs-13">طريقة الدفع:</span>
                            <span class="fw-bold text-dark ms-1">{{ $order->paymentMethodLabel() }}</span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted fs-13">حالة الدفع:</span>
                            @php($paymentStatus = $order->paymentStatusOrDefault())
                            <span class="badge {{ $paymentStatus->badgeClass() }} ms-1">{{ $paymentStatus->label() }}</span>
                        </div>
                        @if($order->payment_provider_reference)
                            <div class="mb-2 fs-12 text-muted">
                                <span>مرجع المزود:</span>
                                <span class="fw-semibold ms-1" dir="ltr">{{ $order->payment_provider_reference }}</span>
                            </div>
                        @endif
                        <div class="fs-12 text-muted">
                            <span>سعر الصرف المعتمد للطلب:</span>
                            <span class="fw-semibold ms-1">{{ rtrim(rtrim(number_format($order->exchange_rate_snapshot, 8, '.', ''), '0'), '.') }}</span>
                        </div>
                        <div class="fs-12 text-muted mt-1">
                            <span>المكافئ بالعملة الأساسية:</span>
                            <span class="fw-bold text-dark ms-1">{{ number_format($order->base_currency_total, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gift Wrapping Snapshot Card (Sprint 21.11) -->
        <div class="card mb-3 border-start border-4 @if($order->hasGiftWrapping()) border-success @else border-secondary @endif">
            <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                <h6 class="card-title mb-0 fs-13 text-muted">
                    <i class="ri-gift-line me-1 text-primary"></i> خدمة تغليف الهدايا (لقطة تاريخية للطلب)
                </h6>
                @if($order->hasGiftWrapping())
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                        <i class="ri-check-line me-1"></i> تم طلب تغليف الهدية (مجاني V1)
                    </span>
                @else
                    <span class="badge bg-light text-muted border px-2 py-1">
                        <i class="ri-close-line me-1"></i> لم يتم طلب تغليف
                    </span>
                @endif
            </div>
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted fs-13">طلب تغليف كهدية:</span>
                            @if($order->hasGiftWrapping())
                                <span class="fw-bold text-success"><i class="ri-checkbox-circle-fill text-success me-1"></i> نعم (Yes)</span>
                            @else
                                <span class="fw-semibold text-muted">لا (No)</span>
                            @endif
                        </div>
                        <div class="fs-12 text-muted mt-1">
                            <span>رسوم خدمة التغليف:</span>
                            <span class="badge bg-primary-subtle text-primary ms-1">0.00 (مجاني V1)</span>
                        </div>
                    </div>
                    <div class="col-md-8 border-start-md">
                        <span class="text-muted fs-13 d-block mb-1">
                            <i class="ri-message-2-line me-1"></i> بطاقة رسالة الهدية (Gift Message):
                        </span>
                        @if($order->hasGiftMessage())
                            <div class="p-2 rounded bg-light border border-dashed text-dark fs-13" style="white-space: pre-wrap;">{{ $order->gift_message_snapshot }}</div>
                        @elseif($order->hasGiftWrapping())
                            <span class="text-muted fs-12 fst-italic">بدون رسالة هدية مرفقة.</span>
                        @else
                            <span class="text-muted fs-12">-</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Fulfillment Hub: warehouse + purchase orders -->
        @if($order->status !== \App\Enums\OrderStatus::Draft)
            @php($poProgress = $order->purchaseOrdersCollectionProgress())
            <div class="card mb-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0">
                        <i class="ri-store-2-line me-1 text-primary"></i> مركز التوريد والاستلام
                    </h5>
                    @if($poProgress['total'] > 0)
                        <span class="badge bg-primary-subtle text-primary border">
                            استُلم {{ $poProgress['collected'] }} من {{ $poProgress['total'] }} موردين
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    @if($order->pickupDriver)
                        <div class="alert alert-info py-2 mb-3">
                            مندوب الاستلام/التوصيل: <strong>{{ $order->pickupDriver->name }}</strong>
                            (<span class="text-muted">({{ $order->pickupDriver->phone }})</span>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100 bg-light-subtle">
                                <h6 class="fw-bold mb-2"><i class="ri-building-2-line me-1"></i> من المخزن</h6>
                                @php($stockedItems = $order->items->filter(fn($i) => $i->isStocked() && ! $i->isCancelled()))
                                @if($stockedItems->isEmpty())
                                    <p class="text-muted mb-0 fs-13">لا توجد أصناف مخزنية في هذا الطلب.</p>
                                @else
                                    <ul class="mb-0 ps-3 fs-13">
                                        @foreach($stockedItems as $item)
                                            <li>{{ $item->product_name_snapshot }} — كمية {{ rtrim(rtrim(number_format((float)$item->quantity, 4, '.', ''), '0'), '.') }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="fw-bold mb-2"><i class="ri-file-list-3-line me-1"></i> طلبات الشراء</h6>
                            @forelse($order->purchaseOrders as $po)
                                <div class="border rounded p-3 mb-2 {{ $po->isOnHold() ? 'border-warning' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <a href="{{ route('admin.purchase-orders.show', $po) }}" class="fw-semibold">{{ $po->po_number }}</a>
                                            <span class="text-muted">— {{ $po->supplier?->name }}</span>
                                            <div class="mt-1">
                                                <span class="badge {{ $po->status->badgeClass() }}">{{ $po->status->label() }}</span>
                                                <span class="text-muted fs-12 ms-1">{{ $po->items->where('status', 'pending')->count() }} بند نشط</span>
                                            </div>
                                            @if($po->isOnHold())
                                                <div class="text-warning fs-12 mt-1">معلّق: {{ $po->on_hold_reason }}</div>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="{{ route('admin.purchase-orders.show', $po) }}" class="btn btn-sm btn-subtle-primary">تفاصيل</a>
                                            @if($po->canCollectFromSupplier() && auth()->user()->hasPermission('admin.sourcing.manage'))
                                                <form method="POST" action="{{ route('admin.purchase-orders.confirm-collected', $po) }}" data-confirm="تأكيد استلام طلب الشراء من المورد؟">
                                                    @csrf
                                                    <button class="btn btn-sm btn-success" type="submit">تم الاستلام</button>
                                                </form>
                                            @elseif($po->status->canBeCollected() && auth()->user()->hasPermission('admin.sourcing.manage'))
                                                <span class="badge bg-warning-subtle text-warning border">أسند مندوب التوصيل أولاً</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0 fs-13">لا توجد طلبات شراء — الطلب يعتمد على المخزن فقط أو لم يُؤكَّد بعد.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Order Items Table Card -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="ri-list-check me-1 text-primary"></i> بنود الطلب والأسعار التاريخية
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>المنتج / المتغير (SKU)</th>
                                <th class="text-center">الوحدة</th>
                                <th class="text-center">الكمية المطلوبة</th>
                                <th class="text-end">سعر الوحدة</th>
                                <th class="text-end">الإجمالي الفرعي</th>
                                <th class="text-end">الخصم</th>
                                <th class="text-end">الإجمالي الصافي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $index => $item)
                                <tr>
                                    <td class="text-center text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            @php($itemImgUrl = $item->productVariant?->getPrimaryImageUrl('thumb') ?: $item->productVariant?->getPrimaryImageUrl())
                                            @if($itemImgUrl)
                                                <a href="{{ $item->productVariant?->product ? route('admin.products.show', $item->productVariant->product) : '#' }}">
                                                    <img src="{{ $itemImgUrl }}" alt="{{ $item->product_name_snapshot }}"
                                                         class="avatar-sm rounded object-fit-cover border shadow-sm flex-shrink-0"
                                                         style="width: 52px; height: 52px;">
                                                </a>
                                            @else
                                                <div class="avatar-sm rounded bg-light d-flex align-items-center justify-content-center text-muted border flex-shrink-0"
                                                     style="width: 52px; height: 52px;">
                                                    <i class="ri-image-line fs-20"></i>
                                                </div>
                                            @endif
                                            <div class="d-flex flex-column flex-grow-1">
                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <span class="fw-bold text-dark fs-14">{{ $item->product_name_snapshot }}</span>
                                                    @if($item->productVariant?->product)
                                                        <a href="{{ route('admin.products.show', $item->productVariant->product) }}" class="text-primary fs-12" title="عرض المنتج في الكتالوج">
                                                            <i class="ri-external-link-line"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                                <small class="text-muted">
                                                    SKU: <span class="badge bg-light text-dark border">{{ $item->sku_snapshot }}</span>
                                                    @if($item->variant_name_snapshot)
                                                        | {{ $item->variant_name_snapshot }}
                                                    @endif
                                                </small>

                                                @php($supplierItemNo = $item->supplier_item_number_snapshot ?? $item->productVariant?->supplier_item_number ?? $item->productVariant?->product?->supplier_item_number)
                                                @if($supplierItemNo)
                                                    <div class="mt-1">
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle fs-11" title="رقم الصنف لدى المورد">
                                                            <i class="ri-barcode-line me-1"></i>رقم الصنف لدى المورد: <span dir="ltr" class="fw-bold">{{ $supplierItemNo }}</span>
                                                        </span>
                                                    </div>
                                                @endif

                                                @if($item->isOnDemand())
                                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                                        <span class="badge bg-warning-subtle text-warning border">تحت الطلب</span>
                                                        @if($item->sourcingSupplier?->code || $item->supplier_name_snapshot)
                                                            <span class="badge bg-light text-dark border" dir="ltr">
                                                                مورد: {{ $item->sourcingSupplier?->code ?? '' }}
                                                                {{ $item->supplier_name_snapshot }}
                                                            </span>
                                                        @endif
                                                        <span class="badge bg-secondary-subtle text-secondary border">كمية: {{ rtrim(rtrim(number_format($item->quantity, 4, '.', ''), '0'), '.') }}</span>
                                                    </div>
                                                @endif
                                                @if($item->promotion_name_snapshot)
                                                    <div class="mt-1">
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle fs-11">
                                                            <i class="ri-price-tag-3-line"></i> عرض: {{ $item->promotion_name_snapshot }}
                                                        </span>
                                                    </div>
                                                @endif
                                                @if($item->bundle_name_snapshot)
                                                    <div class="mt-1">
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">
                                                            <i class="ri-gift-line"></i> حزمة: {{ $item->bundle_name_snapshot }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center text-muted">
                                        {{ $item->unit_name_snapshot ?? '-' }}
                                    </td>
                                    <td class="text-center fw-bold fs-14">
                                        {{ rtrim(rtrim(number_format($item->quantity, 4, '.', ''), '0'), '.') }}
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($item->unit_price, 2) }}
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($item->line_subtotal, 2) }}
                                    </td>
                                    <td class="text-end text-danger">
                                        @if($item->discount_amount > 0)
                                            <div>{{ number_format($item->discount_amount, 2) }}</div>
                                            @if($item->allocated_order_discount > 0)
                                                <small class="text-muted fs-11">(كوبون: {{ number_format($item->allocated_order_discount, 2) }})</small>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold text-primary">
                                        {{ number_format($item->line_total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="7" class="text-end fw-bold">المجموع الفرعي (قبل الخصومات):</td>
                                <td class="text-end fw-bold">{{ number_format($order->subtotal, 2) }} {{ $order->currency?->symbol ?? $order->currency?->code }}</td>
                            </tr>
                            @if($order->promotion_discount_total > 0)
                                <tr>
                                    <td colspan="7" class="text-end fw-bold text-info">
                                        <i class="ri-price-tag-3-line me-1"></i> خصم العروض الترويجية:
                                    </td>
                                    <td class="text-end fw-bold text-info">- {{ number_format($order->promotion_discount_total, 2) }} {{ $order->currency?->symbol ?? $order->currency?->code }}</td>
                                </tr>
                            @endif
                            @if($order->bundle_discount_total > 0)
                                <tr>
                                    <td colspan="7" class="text-end fw-bold text-primary">
                                        <i class="ri-gift-line me-1"></i> خصم الحزم الترويجية:
                                    </td>
                                    <td class="text-end fw-bold text-primary">- {{ number_format($order->bundle_discount_total, 2) }} {{ $order->currency?->symbol ?? $order->currency?->code }}</td>
                                </tr>
                            @endif
                            @if($order->coupon_discount_total > 0)
                                <tr>
                                    <td colspan="7" class="text-end fw-bold text-success">
                                        <i class="ri-coupon-3-line me-1"></i> خصم الكوبون ({{ $order->coupon_code_snapshot }}{{ $order->coupon_name_snapshot ? ' - '.$order->coupon_name_snapshot : '' }}):
                                    </td>
                                    <td class="text-end fw-bold text-success">- {{ number_format($order->coupon_discount_total, 2) }} {{ $order->currency?->symbol ?? $order->currency?->code }}</td>
                                </tr>
                            @endif
                            @if($order->delivery_fee > 0)
                                <tr>
                                    <td colspan="7" class="text-end fw-bold text-dark">
                                        <i class="ri-truck-line me-1 text-primary"></i> رسوم التوصيل ({{ $order->deliveryAddress?->governorate_name_snapshot ?? 'المحافظة' }}):
                                    </td>
                                    <td class="text-end fw-bold text-dark">+ {{ number_format($order->delivery_fee, 2) }} {{ $order->currency?->symbol ?? $order->currency?->code }}</td>
                                </tr>
                            @endif
                            <tr class="table-active fs-16">
                                <td colspan="7" class="text-end fw-bold text-primary">الإجمالي النهائي للطلب:</td>
                                <td class="text-end fw-bold text-primary">{{ number_format($order->total, 2) }} {{ $order->currency?->symbol ?? $order->currency?->code }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Inventory Reservations Section -->
        @if($order->reservations->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="ri-archive-line me-1 text-info"></i> سجل حجز المخزون (Inventory Reservations)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>المستودع</th>
                                    <th>متغير المنتج (SKU)</th>
                                    <th class="text-center">الكمية المحجوزة</th>
                                    <th>حالة الحجز</th>
                                    <th>تاريخ الحجز</th>
                                    <th>تاريخ التحرير</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->reservations as $reservation)
                                    <tr>
                                        <td>{{ $reservation->warehouse?->name_ar }}</td>
                                        <td>{{ $reservation->productVariant?->sku }}</td>
                                        <td class="text-center fw-bold">{{ rtrim(rtrim(number_format($reservation->quantity, 4, '.', ''), '0'), '.') }}</td>
                                        <td>
                                            <span class="badge {{ $reservation->status->badgeClass() }}">
                                                {{ $reservation->status->label() }}
                                            </span>
                                        </td>
                                        <td class="fs-12 text-muted">{{ $reservation->reserved_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td class="fs-12 text-muted">{{ $reservation->released_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 bg-light border-top text-muted fs-12">
                        <i class="ri-information-line me-1 text-primary"></i> <strong>قاعدة العمل المعتمدة:</strong> تم حجز هذه الكميات فقط لضمان عدم بيعها لطلب آخر. سيتم الخصم الفعلي للبضاعة من رصيد المخزون الفيزيائي عند تسليم الطلب للسائق في مرحلة التوصيل (إسبرنت 12).
                    </div>
                </div>
            </div>
        @endif

        <!-- Audit & Tracking Card -->
        <div class="card mb-3">
            <div class="card-header bg-light py-2">
                <h6 class="card-title mb-0 fs-13 text-muted">
                    <i class="ri-history-line me-1"></i> سجل التدقيق والتتبع
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3 fs-13">
                    <div class="col-md-3">
                        <span class="text-muted">تم الإنشاء بواسطة:</span>
                        <div class="fw-semibold">{{ $order->creator?->name ?? 'غير محدد' }}</div>
                        <small class="text-muted">{{ $order->created_at->format('Y-m-d H:i:s') }}</small>
                    </div>
                    @if($order->confirmed_at)
                        <div class="col-md-3">
                            <span class="text-muted">تم التأكيد بواسطة:</span>
                            <div class="fw-semibold text-success">{{ $order->confirmer?->name ?? 'غير محدد' }}</div>
                            <small class="text-muted">{{ $order->confirmed_at->format('Y-m-d H:i:s') }}</small>
                        </div>
                    @endif
                    @if($order->cancelled_at)
                        <div class="col-md-3">
                            <span class="text-muted">تم الإلغاء بواسطة:</span>
                            <div class="fw-semibold text-danger">{{ $order->canceller?->name ?? 'غير محدد' }}</div>
                            <small class="text-muted">{{ $order->cancelled_at->format('Y-m-d H:i:s') }}</small>
                        </div>
                    @endif
                    @if($order->notes)
                        <div class="col-12 mt-2">
                            <span class="text-muted">ملاحظات الطلب:</span>
                            <div class="p-2 rounded bg-light border mt-1">{{ $order->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Order Modal -->
@if($order->canBeConfirmed())
<div class="modal fade" id="confirmOrderModal" tabindex="-1" aria-labelledby="confirmOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.orders.confirm', $order) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="confirmOrderModalLabel"><i class="ri-checkbox-circle-line me-1"></i> تأكيد طلب المبيعات</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-15">هل أنت متأكد من رغبتك في تأكيد الطلب رقم <strong>{{ $order->order_number }}</strong>؟</p>
                    <div class="alert alert-warning fs-13 mb-0">
                        <i class="ri-alert-line me-1"></i> <strong>سيترتب على التأكيد:</strong>
                        <ul class="mb-0 mt-1">
                            <li>تثبيت اللقطات التاريخية للأسعار والعملة وعنوان التوصيل.</li>
                            <li>حجز الكميات المطلوبة في المستودع المختار ومنع بيعها.</li>
                            <li>قفل إمكانية تعديل بنود الطلب.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">تراجع</button>
                    <button type="submit" class="btn btn-success">تأكيد وحجز المخزون</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Cancel Order Modal -->
@if($order->canBeCancelled())
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.orders.cancel', $order) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cancelOrderModalLabel"><i class="ri-close-circle-line me-1"></i> إلغاء طلب المبيعات</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-15">هل أنت متأكد من رغبتك في إلغاء الطلب رقم <strong>{{ $order->order_number }}</strong>؟</p>
                    @if($order->status === \App\Enums\OrderStatus::Confirmed)
                        <div class="alert alert-info fs-13 mb-3">
                            <i class="ri-information-line me-1"></i> سيتم تحرير كافة الكميات المحجوزة لهذا الطلب وإعادتها فوراً إلى رصيد المخزون المتاح.
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label required">سبب الإلغاء (إلزامي):</label>
                        <textarea name="cancellation_reason" class="form-control" rows="3" required placeholder="أدخل سبب إلغاء الطلب بالتفصيل..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">تراجع</button>
                    <button type="submit" class="btn btn-danger">تأكيد الإلغاء وتحرير الحجوزات</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Assign Driver Modal -->
@if($order->canBeAssignedToDriver())
<div class="modal fade" id="assignDriverModal" tabindex="-1" aria-labelledby="assignDriverModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.deliveries.assign', $order) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="assignDriverModalLabel"><i class="ri-e-bike-2-line me-1"></i> إسناد الطلب لمندوب التوصيل</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning fs-13 mb-3">
                        <i class="ri-alert-line me-1"></i> <strong>تنبيه حركة المخزون وخروج البضاعة:</strong>
                        <ul class="mb-0 mt-1">
                            <li>سيتم تسجيل خروج البضاعة فعلياً من مستودع <strong>({{ $order->warehouse?->name_ar }})</strong>.</li>
                            <li>سيتم استهلاك حجز المخزون وخصم الكميات من الرصيد الفعلي للمستودع.</li>
                            <li>ستنتقل البضاعة إلى عهدة المندوب المحدد مع إنشاء سجل شحنة جديد برقم تتبع.</li>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">اختر مندوب التوصيل:</label>
                        <select name="driver_id" class="form-select" required>
                            <option value="">-- اختر المندوب --</option>
                            @foreach($activeDrivers ?? [] as $driver)
                                <option value="{{ $driver->id }}" @selected($order->pickup_driver_id === $driver->id)>
                                    {{ $driver->name }} ({{ $driver->code ?? $driver->driver_code ?? '' }}) - {{ $driver->phone }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ملاحظات التوصيل / التسليم للمندوب:</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="أي تعليمات خاصة للمندوب أو أوقات التوصيل المفضلة..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">تراجع</button>
                    <button type="submit" class="btn btn-primary">تأكيد الإسناد وإذن الخروج</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@if($order->status === \App\Enums\OrderStatus::Confirmed && $order->purchaseOrders->isNotEmpty())
<div class="modal fade" id="assignPickupDriverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.orders.assign-pickup-driver', $order) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-truck-line me-1"></i> إسناد مندوب الاستلام من الموردين</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-13 text-muted">المندوب سيزور الموردين لاستلام طلبات الشراء. عند اكتمال الاستلام يصبح الطلب جاهزاً للتوصيل تلقائياً.</p>
                    <label class="form-label required">المندوب</label>
                    <select name="driver_id" class="form-select" required>
                        <option value="">-- اختر المندوب --</option>
                        @foreach($activeDrivers ?? [] as $driver)
                            <option value="{{ $driver->id }}" @selected($order->pickup_driver_id === $driver->id)>
                                {{ $driver->name }} — {{ $driver->phone }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                    <button type="submit" class="btn btn-primary">حفظ الإسناد</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
