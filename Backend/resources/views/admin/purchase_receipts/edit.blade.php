@extends('layouts.admin')

@section('title', 'تعديل سند استلام مشتريات | لمّة')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-11">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-edit-line me-1 text-primary"></i> تعديل مسودة سند الاستلام: {{ $receipt->receipt_number }}
                    </h5>
                    <a href="{{ route('admin.purchase-receipts.show', $receipt) }}" class="btn btn-sm btn-light">
                        <i class="ri-arrow-right-line me-1"></i> العودة للتفاصيل
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="alert alert-info border-0 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <span class="text-muted fs-12 d-block">فاتورة الشراء الأصلية:</span>
                            <strong>{{ $receipt->purchaseInvoice?->invoice_number }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-12 d-block">المورد:</span>
                            <strong>{{ $receipt->purchaseInvoice?->supplier?->name }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-12 d-block">حالة السند:</span>
                            <span class="badge {{ $receipt->status->badgeClass() }}">{{ $receipt->status->label() }}</span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.purchase-receipts.update', $receipt) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label required">مستودع الاستلام الفعلي</label>
                            <select name="warehouse_id" class="form-control" data-choices required>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('warehouse_id', $receipt->warehouse_id) == $wh->id)>
                                        {{ $wh->name_ar }} ({{ $wh->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">ملاحظات سند الاستلام</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes', $receipt->notes) }}">
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3">
                        <i class="ri-list-check me-1 text-primary"></i> بنود الاستلام والكميات:
                    </h6>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>الصنف / المتغير</th>
                                    <th class="text-center">الوحدة</th>
                                    <th class="text-center">إجمالي المستحق</th>
                                    <th class="text-center">مستلم بفواتير أخرى</th>
                                    <th class="text-center" style="width: 180px;">الكمية المستلمة بالسند</th>
                                    <th>ملاحظات البند</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php($receiptItemsKeyed = $receipt->items->keyBy('purchase_invoice_item_id'))
                                @foreach($receipt->purchaseInvoice->items as $idx => $invItem)
                                    @php($currentRcItem = $receiptItemsKeyed->get($invItem->id))
                                    @php($otherReceived = (float) $invItem->purchaseReceiptItems()->where('purchase_receipt_id', '!=', $receipt->id)->whereHas('purchaseReceipt', fn($q) => $q->where('status', \App\Enums\PurchaseReceiptStatus::Posted))->sum('received_base_quantity'))
                                    @php($maxAllowed = max(0, $invItem->total_receivable_quantity - $otherReceived))
                                    <tr>
                                        <td>
                                            <input type="hidden" name="items[{{ $idx }}][purchase_invoice_item_id]" value="{{ $invItem->id }}">
                                            <input type="hidden" name="items[{{ $idx }}][product_variant_id]" value="{{ $invItem->product_variant_id }}">
                                            <strong>{{ $invItem->productVariant?->product?->name_ar ?? '—' }}</strong>
                                            <div class="fs-12 text-muted" dir="ltr">SKU: {{ $invItem->productVariant?->sku }}</div>
                                        </td>
                                        <td class="text-center">{{ $invItem->unit?->name_ar ?? 'قطعة' }}</td>
                                        <td class="text-center fw-bold">{{ number_format((float) $invItem->total_receivable_quantity, 2) }}</td>
                                        <td class="text-center text-info">{{ number_format((float) $otherReceived, 2) }}</td>
                                        <td>
                                            <input type="number" 
                                                   step="0.0001" 
                                                   min="0" 
                                                   max="{{ $maxAllowed }}" 
                                                   name="items[{{ $idx }}][received_base_quantity]" 
                                                   class="form-control text-center fw-bold" 
                                                   value="{{ old("items.{$idx}.received_base_quantity", $currentRcItem?->received_base_quantity ?? 0) }}"
                                                   required>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="items[{{ $idx }}][notes]" 
                                                   class="form-control form-control-sm" 
                                                   value="{{ old("items.{$idx}.notes", $currentRcItem?->notes) }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.purchase-receipts.show', $receipt) }}" class="btn btn-light">إلغاء</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i> حفظ التعديلات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
