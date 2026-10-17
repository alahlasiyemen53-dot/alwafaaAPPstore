@extends('layouts.admin')
@section('title', 'طلب شراء '.$purchaseOrder->po_number.' | لمّة')
@section('page-title', 'تفاصيل طلب الشراء')

@section('content')
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="card-title mb-1">
                <i class="ri-file-list-3-line me-1 text-primary"></i>
                {{ $purchaseOrder->po_number }}
                <span class="badge {{ $purchaseOrder->status->badgeClass() }} ms-1">{{ $purchaseOrder->status->label() }}</span>
            </h4>
            <div class="text-muted fs-13">
                طلب العميل:
                <a href="{{ route('admin.orders.show', $purchaseOrder->order) }}">{{ $purchaseOrder->order?->order_number }}</a>
                | المورد: <strong>{{ $purchaseOrder->supplier?->name }}</strong>
                @if($purchaseOrder->assignedDriver)
                    | المندوب: <strong>{{ $purchaseOrder->assignedDriver->name }}</strong>
                @endif
            </div>
        </div>
        <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-light"><i class="ri-arrow-right-line"></i> العودة</a>
    </div>
    @if($purchaseOrder->isOnHold())
        <div class="alert alert-warning mb-0 rounded-0">
            <strong>معلّق:</strong> {{ $purchaseOrder->on_hold_reason }}
        </div>
    @endif
    @if($purchaseOrder->failure_reason)
        <div class="alert alert-danger mb-0 rounded-0">
            <strong>تعذر الاستلام:</strong> {{ $purchaseOrder->failure_reason }}
        </div>
    @endif
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0">بنود طلب الشراء</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>الصنف</th>
                            <th>SKU</th>
                            <th>رقم المورد</th>
                            <th>الكمية</th>
                            <th>الحالة</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseOrder->items as $item)
                            <tr class="{{ $item->isCancelled() ? 'table-secondary' : '' }}">
                                <td>
                                    <div class="fw-semibold">{{ $item->product_name_snapshot }}</div>
                                    <small class="text-muted">{{ $item->variant_name_snapshot }}</small>
                                </td>
                                <td><code>{{ $item->sku_snapshot }}</code></td>
                                <td dir="ltr">{{ $item->supplier_item_number_snapshot ?: '—' }}</td>
                                <td>{{ rtrim(rtrim(number_format((float)$item->quantity, 4, '.', ''), '0'), '.') }}</td>
                                <td>{{ $item->status->label() }}</td>
                                <td class="text-end">
                                    @if($item->isActive() && $purchaseOrder->status->canChangeSupplier() && auth()->user()->hasPermission('admin.sourcing.manage'))
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelItemModal{{ $item->id }}">إلغاء البند</button>
                                        <div class="modal fade" id="cancelItemModal{{ $item->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <form method="POST" action="{{ route('admin.purchase-orders.cancel-item', $item) }}" class="modal-content">
                                                    @csrf
                                                    <div class="modal-header"><h5 class="modal-title">إلغاء بند من الطلب</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                    <div class="modal-body">
                                                        <p class="fs-13 text-muted">سيُلغى البند من طلب الشراء وطلب العميل، ويُعاد احتساب الإجمالي.</p>
                                                        <label class="form-label">سبب الإلغاء</label>
                                                        <textarea name="cancellation_reason" class="form-control" required minlength="3" rows="3"></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                                                        <button type="submit" class="btn btn-danger">تأكيد الإلغاء</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        @if(auth()->user()->hasPermission('admin.sourcing.manage') && $purchaseOrder->canCollectFromSupplier())
            <div class="card mb-3">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.purchase-orders.confirm-collected', $purchaseOrder) }}" data-confirm="تأكيد استلام كل البنود من المورد؟">
                        @csrf
                        <button class="btn btn-success w-100" type="submit"><i class="ri-checkbox-circle-line me-1"></i> تأكيد الاستلام من المورد</button>
                    </form>
                </div>
            </div>
        @elseif(auth()->user()->hasPermission('admin.sourcing.manage') && $purchaseOrder->status->canBeCollected())
            <div class="card mb-3">
                <div class="card-body">
                    <div class="alert alert-warning mb-0">أسند مندوب التوصيل للطلب أولاً قبل تأكيد الاستلام من المورد.</div>
                </div>
            </div>
        @endif

        @if(auth()->user()->hasPermission('admin.sourcing.manage') && $purchaseOrder->status->canChangeSupplier())
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">تغيير المورد</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.purchase-orders.change-supplier', $purchaseOrder) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">المورد البديل</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">اختر مورداً</option>
                                @foreach($suppliers as $supplier)
                                    @if($supplier->id !== $purchaseOrder->supplier_id)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">للبند فقط (اختياري)</label>
                            <select name="purchase_order_item_id" class="form-select">
                                <option value="">كل بنود طلب الشراء</option>
                                @foreach($purchaseOrder->items->filter(fn($i) => $i->isActive()) as $item)
                                    <option value="{{ $item->id }}">{{ $item->sku_snapshot }} — {{ $item->product_name_snapshot }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">تطبيق تغيير المورد</button>
                    </form>
                </div>
            </div>
        @endif

        @if(auth()->user()->hasPermission('admin.sourcing.manage') && $purchaseOrder->status->canBeHeld())
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">تعليق (صنف ناقص)</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.purchase-orders.hold', $purchaseOrder) }}">
                        @csrf
                        <textarea name="reason" class="form-control mb-2" rows="2" required minlength="3" placeholder="سبب التعليق..."></textarea>
                        <button class="btn btn-warning w-100" type="submit">تعليق طلب الشراء</button>
                    </form>
                </div>
            </div>
        @endif

        @if(auth()->user()->hasPermission('admin.sourcing.manage') && $purchaseOrder->status->canBeFailed())
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">تعذر الاستلام</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.purchase-orders.mark-failed', $purchaseOrder) }}">
                        @csrf
                        <textarea name="failure_reason" class="form-control mb-2" rows="2" required minlength="3"></textarea>
                        <button class="btn btn-outline-danger w-100" type="submit">تسجيل التعذر</button>
                    </form>
                </div>
            </div>
        @endif

        @if(auth()->user()->hasPermission('admin.sourcing.manage') && $purchaseOrder->status->canBeCancelled())
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">إلغاء طلب الشراء</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.purchase-orders.cancel', $purchaseOrder) }}">
                        @csrf
                        <textarea name="cancellation_reason" class="form-control mb-2" rows="2" required minlength="3"></textarea>
                        <button class="btn btn-danger w-100" type="submit">إلغاء</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
