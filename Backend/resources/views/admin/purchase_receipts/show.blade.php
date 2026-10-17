@extends('layouts.admin')

@section('title', 'تفاصيل سند استلام مشتريات | الوفاء استور')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="card-title mb-0">
                            <i class="ri-inbox-archive-line me-1 text-primary"></i> سند استلام: {{ $receipt->receipt_number }}
                        </h5>
                        <span class="badge {{ $receipt->status->badgeClass() }} fs-12">
                            {{ $receipt->status->label() }}
                        </span>
                    </div>

                    <div class="d-flex gap-2">
                        @if($receipt->status === \App\Enums\PurchaseReceiptStatus::Draft)
                            @if(auth()->user()->hasPermission('admin.purchase_receipts.update'))
                                <a href="{{ route('admin.purchase-receipts.edit', $receipt) }}" class="btn btn-soft-primary btn-sm">
                                    <i class="ri-edit-line me-1"></i> تعديل المسودة
                                </a>
                            @endif

                            @if(auth()->user()->hasPermission('admin.purchase_receipts.post'))
                                <form method="POST" action="{{ route('admin.purchase-receipts.post', $receipt) }}" onsubmit="return confirm('هل أنت متأكد من ترحيل سند الاستلام؟ سيتم زيادة المخزون الفعلي في المستودع فوراً ولا يمكن التراجع.')">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="ri-check-line me-1"></i> ترحيل السند وإيداع المخزون
                                    </button>
                                </form>
                            @endif

                            @if(auth()->user()->hasPermission('admin.purchase_receipts.delete'))
                                <form method="POST" action="{{ route('admin.purchase-receipts.destroy', $receipt) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا السند؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger btn-sm">
                                        <i class="ri-delete-bin-line me-1"></i> حذف
                                    </button>
                                </form>
                            @endif
                        @else
                            <span class="badge bg-success-subtle text-success border border-success-subtle p-2">
                                <i class="ri-check-double-line me-1"></i> السند مرحّل وغير قابل للتعديل
                            </span>
                        @endif

                        <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-light btn-sm">
                            <i class="ri-arrow-right-line me-1"></i> العودة
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">فاتورة الشراء الأصلية</span>
                            @if($receipt->purchaseInvoice)
                                <a href="{{ route('admin.purchases.show', $receipt->purchaseInvoice) }}" class="fw-bold text-primary">
                                    {{ $receipt->purchaseInvoice->invoice_number }}
                                </a>
                            @else
                                —
                            @endif
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">المورد</span>
                            <strong class="text-dark">{{ $receipt->purchaseInvoice?->supplier?->name ?? '—' }}</strong>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">مستودع الاستلام</span>
                            <strong class="text-dark">
                                <i class="ri-building-4-line me-1 text-primary"></i>{{ $receipt->warehouse?->name_ar ?? '—' }}
                            </strong>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">تاريخ ووقت الاستلام</span>
                            <strong class="text-dark">
                                {{ $receipt->received_at?->format('Y-m-d H:i') ?? 'بانتظار الترحيل' }}
                            </strong>
                        </div>
                    </div>
                </div>

                @if($receipt->notes)
                    <div class="alert alert-light border mb-4">
                        <strong>ملاحظات:</strong> {{ $receipt->notes }}
                    </div>
                @endif

                <h6 class="fw-bold mb-3">
                    <i class="ri-box-3-line me-1 text-primary"></i> تفاصيل البنود المستلمة ({{ $receipt->items->count() }}):
                </h6>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>الصنف / المتغير</th>
                                <th>رمز الصنف (SKU)</th>
                                <th class="text-center">الكمية المستلمة (بالوحدة الأساسية)</th>
                                <th class="text-center">تكلفة الوحدة بالفاتورة</th>
                                <th class="text-center">التكلفة بالعملة الأساسية</th>
                                <th>ملاحظات البند</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receipt->items as $i => $item)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <strong>{{ $item->productVariant?->product?->name_ar ?? '—' }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-body border" dir="ltr">
                                            {{ $item->productVariant?->sku ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold fs-14 text-success">
                                        +{{ number_format((float) $item->received_base_quantity, 2) }}
                                    </td>
                                    <td class="text-center">
                                        {{ number_format((float) $item->unit_cost_snapshot, 2) }}
                                    </td>
                                    <td class="text-center">
                                        {{ number_format((float) $item->base_currency_cost, 2) }}
                                    </td>
                                    <td class="text-muted">{{ $item->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4 pt-3 border-top text-muted fs-12">
                    <div class="col-md-6">
                        أنشئ بواسطة: <strong>{{ $receipt->createdBy?->name ?? '—' }}</strong> في {{ $receipt->created_at->format('Y-m-d H:i') }}
                    </div>
                    @if($receipt->received_by)
                        <div class="col-md-6 text-md-end">
                            تم الترحيل والاستلام بواسطة: <strong>{{ $receipt->receivedBy?->name ?? '—' }}</strong> في {{ $receipt->received_at?->format('Y-m-d H:i') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
