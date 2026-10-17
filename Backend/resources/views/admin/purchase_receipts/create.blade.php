@extends('layouts.admin')

@section('title', 'إنشاء سند استلام مشتريات | الوفاء استور')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-11">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-inbox-archive-line me-1 text-primary"></i> استلام بضاعة مشتريات
                    </h5>
                    <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-sm btn-light">
                        <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                    </a>
                </div>
            </div>

            <div class="card-body">
                @if(! $selectedInvoice)
                    {{-- Step 1: Select Invoice --}}
                    <div class="p-4 text-center">
                        <i class="ri-file-search-line fs-48 text-primary mb-3"></i>
                        <h5>حدد فاتورة الشراء المراد استلام بضاعتها</h5>
                        <p class="text-muted mb-4">يجب اختيار فاتورة شراء مرحّلة لاستلام كمياتها في المستودع.</p>

                        <form method="GET" action="{{ route('admin.purchase-receipts.create') }}" class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <select name="purchase_invoice_id" class="form-control" data-choices required>
                                        <option value="">-- اختر فاتورة الشراء المرحّلة --</option>
                                        @foreach($postedInvoices as $inv)
                                            <option value="{{ $inv->id }}">
                                                {{ $inv->invoice_number }} — المورد: {{ $inv->supplier?->name }} ({{ $inv->warehouse?->name_ar }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="ri-arrow-left-line me-1"></i> متابعة الاستلام
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                @else
                    {{-- Step 2: Receive Items Form --}}
                    <div class="alert alert-info border-0 mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <span class="text-muted fs-12 d-block">فاتورة الشراء:</span>
                                <strong>{{ $selectedInvoice->invoice_number }}</strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted fs-12 d-block">المورد:</span>
                                <strong>{{ $selectedInvoice->supplier?->name }}</strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted fs-12 d-block">المستودع الافتراضي بالفاتورة:</span>
                                <strong>{{ $selectedInvoice->warehouse?->name_ar }}</strong>
                            </div>
                            <div class="col-md-3 text-end">
                                <a href="{{ route('admin.purchase-receipts.create') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="ri-exchange-line me-1"></i> تغيير الفاتورة
                                </a>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.purchase-receipts.store') }}">
                        @csrf
                        <input type="hidden" name="purchase_invoice_id" value="{{ $selectedInvoice->id }}">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label required">مستودع الاستلام الفعلي</label>
                                <select name="warehouse_id" class="form-control" data-choices required>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" @selected(old('warehouse_id', $selectedInvoice->warehouse_id) == $wh->id)>
                                            {{ $wh->name_ar }} ({{ $wh->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">ملاحظات سند الاستلام</label>
                                <input type="text" name="notes" class="form-control" placeholder="أي ملاحظات حول الشحنة أو التغليف..." value="{{ old('notes') }}">
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3">
                            <i class="ri-list-check me-1 text-primary"></i> بنود الفاتورة والكميات المراد استلامها:
                        </h6>

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>الصنف / المتغير</th>
                                        <th class="text-center">الوحدة الأساسية</th>
                                        <th class="text-center">شراء</th>
                                        <th class="text-center">مجاني</th>
                                        <th class="text-center">إجمالي المستحق</th>
                                        <th class="text-center">مستلم سابقاً</th>
                                        <th class="text-center">المتبقي للاستلام</th>
                                        <th class="text-center" style="width: 170px;">استلم الآن (الوحدة الأساسية)</th>
                                        <th>ملاحظات البند</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php($hasReceivable = false)
                                    @foreach($selectedInvoice->items as $idx => $invItem)
                                        @php($rem = $invItem->remaining_receivable_quantity)
                                        @if($rem > 0) @php($hasReceivable = true) @endif
                                        <tr class="{{ $rem <= 0 ? 'table-light text-muted' : '' }}">
                                            <td>
                                                <input type="hidden" name="items[{{ $idx }}][purchase_invoice_item_id]" value="{{ $invItem->id }}">
                                                <input type="hidden" name="items[{{ $idx }}][product_variant_id]" value="{{ $invItem->product_variant_id }}">
                                                <strong>{{ $invItem->productVariant?->product?->name_ar ?? '—' }}</strong>
                                                <div class="fs-12 text-muted" dir="ltr">
                                                    SKU: {{ $invItem->productVariant?->sku }}
                                                </div>
                                            </td>
                                            <td class="text-center">{{ $invItem->unit?->name_ar ?? 'قطعة' }}</td>
                                            <td class="text-center">{{ number_format((float) $invItem->base_quantity, 2) }}</td>
                                            <td class="text-center">{{ number_format((float) $invItem->free_quantity, 2) }}</td>
                                            <td class="text-center fw-bold">{{ number_format((float) $invItem->total_receivable_quantity, 2) }}</td>
                                            <td class="text-center text-info">{{ number_format((float) $invItem->already_received_quantity, 2) }}</td>
                                            <td class="text-center fw-bold {{ $rem > 0 ? 'text-success' : 'text-muted' }}">
                                                {{ number_format((float) $rem, 2) }}
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       step="0.0001" 
                                                       min="0" 
                                                       max="{{ $rem }}" 
                                                       name="items[{{ $idx }}][received_base_quantity]" 
                                                       class="form-control text-center fw-bold" 
                                                       value="{{ old("items.{$idx}.received_base_quantity", $rem) }}"
                                                       {{ $rem <= 0 ? 'readonly disabled' : 'required' }}>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       name="items[{{ $idx }}][notes]" 
                                                       class="form-control form-control-sm" 
                                                       placeholder="ملاحظات البند..." 
                                                       value="{{ old("items.{$idx}.notes") }}"
                                                       {{ $rem <= 0 ? 'disabled' : '' }}>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if(! $hasReceivable)
                            <div class="alert alert-warning">
                                <i class="ri-error-warning-line me-1"></i> لقد تم استلام كامل كميات هذه الفاتورة مسبقاً بنسبة 100%. لا توجد كميات متبقية للاستلام.
                            </div>
                        @else
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-light">إلغاء</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-save-line me-1"></i> حفظ مسودة سند الاستلام
                                </button>
                            </div>
                        @endif
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
