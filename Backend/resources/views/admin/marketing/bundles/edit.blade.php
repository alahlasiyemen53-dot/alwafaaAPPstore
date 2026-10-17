@extends('layouts.admin')

@section('title', 'تعديل باقة المنتجات: ' . $bundle->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">تعديل باقة المنتجات (Bundle)</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.marketing.bundles.index') }}">باقات المنتجات</a></li>
                        <li class="breadcrumb-item active">تعديل</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.marketing.bundles.update', $bundle) }}">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">بيانات الباقة وطريقة التسعير</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">كود الباقة <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control font-monospace" value="{{ old('code', $bundle->code) }}" style="text-transform: uppercase;" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">اسم الباقة <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $bundle->name) }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">الوصف</label>
                                <textarea name="description" class="form-control" rows="2">{{ old('description', $bundle->description) }}</textarea>
                            </div>
                            @php
                                $baseCurrency = \App\Models\Currency::where('is_default', true)->first();
                                $baseSymbol = $baseCurrency?->symbol ?? 'ر.ي';
                                $isPercentage = old('pricing_type', $bundle->pricing_type->value) === 'percentage_discount';
                            @endphp
                            <div class="col-md-6">
                                <label class="form-label">طريقة التسعير <span class="text-danger">*</span></label>
                                <select name="pricing_type" id="pricing_type" class="form-select" required>
                                    <option value="fixed_bundle_price" {{ old('pricing_type', $bundle->pricing_type->value) === 'fixed_bundle_price' ? 'selected' : '' }}>سعر إجمالي محدد للباقة</option>
                                    <option value="percentage_discount" {{ old('pricing_type', $bundle->pricing_type->value) === 'percentage_discount' ? 'selected' : '' }}>نسبة خصم على مجموع مكونات الباقة (%)</option>
                                    <option value="fixed_amount_discount" {{ old('pricing_type', $bundle->pricing_type->value) === 'fixed_amount_discount' ? 'selected' : '' }}>مبلغ خصم ثابت على الباقة</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">القيمة <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.0001" min="0.0001" name="value" id="bundle_value" class="form-control" value="{{ old('value', (float) $bundle->value) }}" required>
                                    <span class="input-group-text" id="bundle_pricing_badge">{{ $isPercentage ? '%' : $baseSymbol }}</span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    المبالغ الثابتة محددة بالعملة الأساسية ({{ $baseCurrency?->name_ar ?? 'الريال' }}). النسب المئوية محايدة للعملة وتطبق بنفس النسبة على أي عملة طلب.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">مكونات الباقة من المنتجات والمتغيرات</h5>
                        <button type="button" class="btn btn-sm btn-soft-primary" id="add_component_btn">
                            <i class="ri-add-line me-1"></i> إضافة متغير للباقة
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle" id="bundle_items_table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 65%;">المتغير (المنتج والـ SKU)</th>
                                        <th style="width: 25%;">الكمية المطلوبة للباقة</th>
                                        <th style="width: 10%;">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bundle->items as $idx => $item)
                                        <tr class="bundle-item-row">
                                            <td>
                                                <select name="items[{{ $idx }}][product_variant_id]" class="form-select" required>
                                                    <option value="">-- اختر متغير المنتج --</option>
                                                    @foreach($variants as $variant)
                                                        <option value="{{ $variant->id }}" {{ (int) $item->product_variant_id === (int) $variant->id ? 'selected' : '' }}>
                                                            {{ $variant->product?->name_ar }} - [{{ $variant->sku }}]
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" step="0.0001" min="0.0001" name="items[{{ $idx }}][quantity]" class="form-control" value="{{ (float) $item->quantity }}" required>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-soft-danger remove-row-btn" {{ count($bundle->items) <= 1 ? 'disabled' : '' }}>
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">الصلاحية والتحكم</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">تاريخ ووقت البدء</label>
                            <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $bundle->starts_at ? $bundle->starts_at->format('Y-m-d\TH:i') : '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تاريخ ووقت الانتهاء</label>
                            <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $bundle->ends_at ? $bundle->ends_at->format('Y-m-d\TH:i') : '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الأولوية (Priority)</label>
                            <input type="number" name="priority" class="form-control" value="{{ old('priority', $bundle->priority) }}" min="0">
                        </div>
                        <div class="mb-3 form-check form-switch form-switch-lg">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $bundle->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">الباقة نشطة</label>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary w-100">تحديث الباقة</button>
                            <a href="{{ route('admin.marketing.bundles.index') }}" class="btn btn-light w-100">إلغاء</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = {{ count($bundle->items) }};
    const tableBody = document.querySelector('#bundle_items_table tbody');
    const addBtn = document.getElementById('add_component_btn');

    function attachRemoveHandlers() {
        tableBody.querySelectorAll('.remove-row-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                if (tableBody.querySelectorAll('.bundle-item-row').length > 1) {
                    btn.closest('.bundle-item-row').remove();
                }
            });
        });
    }

    attachRemoveHandlers();

    addBtn.addEventListener('click', function () {
        const firstRow = tableBody.querySelector('.bundle-item-row');
        const newRow = firstRow.cloneNode(true);

        const select = newRow.querySelector('select');
        select.name = `items[${rowIndex}][product_variant_id]`;
        select.value = '';

        const qtyInput = newRow.querySelector('input[type="number"]');
        qtyInput.name = `items[${rowIndex}][quantity]`;
        qtyInput.value = '1.0000';

        const removeBtn = newRow.querySelector('.remove-row-btn');
        removeBtn.disabled = false;
        removeBtn.addEventListener('click', function () {
            if (tableBody.querySelectorAll('.bundle-item-row').length > 1) {
                newRow.remove();
            }
        });

        tableBody.appendChild(newRow);
        rowIndex++;
    });

    const pricingType = document.getElementById('pricing_type');
    const badge = document.getElementById('bundle_pricing_badge');
    if (pricingType && badge) {
        pricingType.addEventListener('change', function() {
            badge.textContent = this.value === 'percentage_discount' ? '%' : @json($baseSymbol);
        });
    }
});
</script>
@endsection
