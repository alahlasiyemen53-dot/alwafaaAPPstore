@extends('layouts.admin')

@section('title', 'تفاصيل مرتجع المبيعات | الوفاء استور')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <!-- Return Header Card -->
        <div class="card mb-3">
            <div class="card-header bg-light d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="card-title mb-0">
                        <i class="ri-refund-2-line me-1 text-primary"></i> مرتجع مبيعات: {{ $salesReturn->return_number }}
                    </h5>
                    <span class="{{ $salesReturn->status->badgeClass() }}">
                        {{ $salesReturn->status->label() }}
                    </span>
                </div>
                <div class="text-muted fs-12">
                    تاريخ الإنشاء: {{ $salesReturn->created_at->format('Y-m-d H:i') }}
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted fs-12 mb-1">الطلب الأصلي:</div>
                        <a href="{{ route('admin.orders.show', $salesReturn->order_id) }}" class="fw-bold fs-14 text-primary">
                            <i class="ri-shopping-cart-2-line me-1"></i> {{ $salesReturn->order?->order_number }}
                        </a>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-12 mb-1">العميل:</div>
                        <div class="fw-semibold text-dark">{{ $salesReturn->order?->customer_name_snapshot ?? $salesReturn->order?->customer?->name }}</div>
                        <small class="text-muted"><i class="ri-phone-line me-1"></i>{{ $salesReturn->order?->customer_phone_snapshot ?? $salesReturn->order?->customer?->phone }}</small>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-12 mb-1">مستودع الاستلام الفعلي:</div>
                        <span class="badge bg-light text-dark border">
                            <i class="ri-store-2-line me-1 text-muted"></i>{{ $salesReturn->warehouse?->name_ar }}
                        </span>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-12 mb-1">سبب الإرجاع:</div>
                        <span class="badge bg-secondary-subtle text-secondary">
                            {{ $salesReturn->reason_code?->label() }}
                        </span>
                    </div>
                    @if($salesReturn->notes)
                        <div class="col-12">
                            <div class="text-muted fs-12 mb-1">الملاحظات:</div>
                            <div class="p-2 rounded bg-light border fs-13">{{ $salesReturn->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Return Items Table -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="ri-list-check-2 me-1 text-primary"></i> الأصناف المرتجعة ({{ $salesReturn->items->count() }})
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>الصنف / المتغير (SKU)</th>
                                <th style="width: 120px;" class="text-center">كمية المرتجع</th>
                                <th style="width: 140px;">سعر الوحدة التاريخي</th>
                                <th style="width: 140px;">قيمة السطر</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($salesReturn->items as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $item->product_name_snapshot }}</div>
                                        <small class="text-muted">{{ $item->sku_snapshot }} {{ $item->variant_name_snapshot ? '['.$item->variant_name_snapshot.']' : '' }}</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-13">
                                            {{ (float) $item->return_quantity }}
                                        </span>
                                        @if($item->unit_name_snapshot)
                                            <small class="text-muted d-block">{{ $item->unit_name_snapshot }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span>{{ number_format((float) $item->unit_price_snapshot, 2) }}</span>
                                        <small class="text-muted">{{ $salesReturn->currency?->symbol ?? $salesReturn->currency?->code }}</small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark">{{ number_format((float) $item->line_total, 2) }}</span>
                                        <small class="text-muted">{{ $salesReturn->currency?->symbol ?? $salesReturn->currency?->code }}</small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Stock Movements History Card if Posted -->
        @if($salesReturn->stockMovements->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="ri-history-line me-1 text-success"></i> حركات المخزون الناتجة عن استلام المرتجع
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الصنف (SKU)</th>
                                    <th>المستودع</th>
                                    <th>نوع الحركة</th>
                                    <th>الاتجاه</th>
                                    <th>الكمية المستلمة</th>
                                    <th>وقت التسجيل</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salesReturn->stockMovements as $sm)
                                    <tr>
                                        <td>{{ $sm->productVariant?->sku }}</td>
                                        <td>{{ $sm->warehouse?->name_ar }}</td>
                                        <td><span class="badge bg-info-subtle text-info">{{ $sm->movement_type->label() }}</span></td>
                                        <td><span class="badge bg-success-subtle text-success">وارد (IN)</span></td>
                                        <td class="fw-bold text-success">+{{ (float) $sm->quantity }}</td>
                                        <td class="text-muted fs-12">{{ $sm->occurred_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <!-- Financial Summary Card -->
        <div class="card mb-3 sticky-top" style="top: 80px;">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="ri-file-text-line me-1 text-primary"></i> الملخص المالي للمرتجع
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">المجموع الفرعي:</span>
                    <span class="fw-semibold">{{ number_format((float) $salesReturn->subtotal, 2) }} {{ $salesReturn->currency?->symbol ?? $salesReturn->currency?->code }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fs-16 fw-bold">إجمالي المرتجع:</span>
                    <span class="fs-18 fw-bold text-primary">{{ number_format((float) $salesReturn->total, 2) }} {{ $salesReturn->currency?->symbol ?? $salesReturn->currency?->code }}</span>
                </div>

                @if($salesReturn->currency_id !== $salesReturn->order?->currency_id || bccomp((string) $salesReturn->exchange_rate_snapshot, '1.00000000', 8) !== 0)
                    <div class="p-2 rounded bg-light border mb-3 fs-12">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">سعر الصرف المعتمد للطلب:</span>
                            <span class="fw-semibold">{{ $salesReturn->exchange_rate_snapshot }}</span>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <span class="text-muted">المعادل بالعملة الأساسية:</span>
                            <span class="fw-semibold">{{ number_format((float) $salesReturn->base_currency_total, 2) }}</span>
                        </div>
                    </div>
                @endif

                <!-- Audit info -->
                <div class="border-top pt-3 fs-12 text-muted mb-3">
                    @if($salesReturn->posted_at)
                        <div class="mb-1"><i class="ri-checkbox-circle-line text-success me-1"></i> تم الاعتماد والاستلام في: <strong>{{ $salesReturn->posted_at->format('Y-m-d H:i') }}</strong></div>
                        @if($salesReturn->postedBy)
                            <div class="mb-1"><i class="ri-user-follow-line me-1"></i> المعتمد: <strong>{{ $salesReturn->postedBy->name }}</strong></div>
                        @endif
                    @endif
                    @if($salesReturn->createdBy)
                        <div><i class="ri-user-add-line me-1"></i> أنشئ بواسطة: <strong>{{ $salesReturn->createdBy->name }}</strong></div>
                    @endif
                </div>

                <!-- Actions -->
                <div class="d-grid gap-2">
                    @if($salesReturn->canBePosted() && auth()->user()->hasPermission('admin.sales_returns.post'))
                        <form action="{{ route('admin.sales-returns.post', $salesReturn) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من اعتماد واستلام المرتجع في المستودع؟ ستتم إضافة الكميات فوراً للمخزون الفعلي.');">
                            @csrf
                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="ri-checkbox-circle-line me-1"></i> اعتماد واستلام المرتجع في المستودع
                            </button>
                        </form>
                    @endif

                    @if($salesReturn->canBeEdited() && auth()->user()->hasPermission('admin.sales_returns.update'))
                        <a href="{{ route('admin.sales-returns.edit', $salesReturn) }}" class="btn btn-warning">
                            <i class="ri-edit-line me-1"></i> تعديل المسودة
                        </a>
                    @endif

                    @if($salesReturn->canBeCancelled() && auth()->user()->hasPermission('admin.sales_returns.cancel'))
                        <form action="{{ route('admin.sales-returns.cancel', $salesReturn) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من إلغاء هذه المسودة؟');">
                            @csrf
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="ri-close-circle-line me-1"></i> إلغاء المسودة
                            </button>
                        </form>
                    @endif

                    @if($salesReturn->status === \App\Enums\SalesReturnStatus::Posted)
                        @php
                            $walletRefundEntry = \App\Models\CustomerWalletEntry::where('source_type', 'SalesReturn')
                                ->where('source_id', $salesReturn->id)
                                ->where('source_event', 'SALES_RETURN_REFUND')
                                ->first();
                        @endphp
                        @if($walletRefundEntry)
                            <div class="alert alert-success d-flex align-items-center p-2 fs-12 mb-0">
                                <i class="ri-checkbox-circle-fill me-2 fs-16"></i>
                                <div>تم استرداد القيمة لمحفظة العميل بحركة رقم <strong>{{ $walletRefundEntry->entry_number }}</strong></div>
                            </div>
                        @elseif(auth()->user()->hasPermission('admin.customer_wallets.refund'))
                            <button type="button" class="btn btn-info w-100" data-bs-toggle="modal" data-bs-target="#refundWalletModal">
                                <i class="ri-wallet-3-line me-1"></i> استرداد القيمة إلى محفظة العميل
                            </button>

                            <!-- Modal Refund to Wallet -->
                            <div class="modal fade" id="refundWalletModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow text-start">
                                        <form action="{{ route('admin.customer-wallets.refund') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="sales_return_id" value="{{ $salesReturn->id }}">
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title fw-bold"><i class="ri-wallet-3-line me-1 text-primary"></i> استرداد إلى محفظة العميل</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="mb-2">سيتم إيداع مبلغ <strong>{{ number_format((float) $salesReturn->total, 2) }} {{ $salesReturn->currency?->symbol }}</strong> في محفظة العميل <strong>{{ $salesReturn->order?->customer_name_snapshot ?? $salesReturn->order?->customer?->name }}</strong>.</p>
                                                <p class="text-muted fs-12 mb-3">سيتم إنشاء قيد محاسبي آلي متوازن (من وسيط المرتجعات إلى التزام محافظ العملاء).</p>
                                                <div class="mb-3">
                                                    <label class="form-label">ملاحظات / سبب الاسترداد (اختياري)</label>
                                                    <textarea name="reason" rows="2" class="form-control" placeholder="استرداد قيمة مرتجع المبيعات رقم {{ $salesReturn->return_number }}"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                <button type="submit" class="btn btn-info">تأكيد الاسترداد للمحفظة</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    <a href="{{ route('admin.sales-returns.index') }}" class="btn btn-light">
                        <i class="ri-arrow-right-line me-1"></i> العودة لقائمة المرتجعات
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
