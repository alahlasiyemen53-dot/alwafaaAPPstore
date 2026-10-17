@extends('layouts.admin')

@section('title', 'تعديل أمر تسوية جردية | الوفاء استور')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-edit-line me-1 text-primary"></i> تعديل مسودة أمر التسوية: {{ $adjustment->adjustment_number }}
                    </h5>
                    <a href="{{ route('admin.stock-adjustments.show', $adjustment) }}" class="btn btn-sm btn-light">
                        <i class="ri-arrow-right-line me-1"></i> العودة للتفاصيل
                    </a>
                </div>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('admin.stock-adjustments.update', $adjustment) }}" id="adj-form">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label required">المستودع</label>
                            <select name="warehouse_id" class="form-control" data-choices required>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('warehouse_id', $adjustment->warehouse_id) == $wh->id)>
                                        {{ $wh->name_ar }} ({{ $wh->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">سبب التسوية</label>
                            <select name="reason_code" id="reason_code" class="form-control" data-choices data-choices-search-false required>
                                @foreach($reasons as $reason)
                                    <option value="{{ $reason->value }}" @selected(old('reason_code', $adjustment->reason_code->value) === $reason->value)>
                                        {{ $reason->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">ملاحظات ومبررات التسوية</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes', $adjustment->notes) }}">
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold mb-0">
                            <i class="ri-box-3-line me-1 text-primary"></i> بنود التسوية:
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-item-row">
                            <i class="ri-add-line me-1"></i> إضافة بند
                        </button>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle" id="adj-items-table">
                            <thead class="table-light">
                                <tr>
                                    <th>الصنف / المتغير</th>
                                    <th style="width: 160px;" class="text-center">نوع الحركة</th>
                                    <th style="width: 180px;" class="text-center">الكمية</th>
                                    <th>ملاحظات البند</th>
                                    <th style="width: 60px;" class="text-center">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($adjustment->items as $idx => $item)
                                    <tr>
                                        <td>
                                            <select name="items[{{ $idx }}][product_variant_id]" class="form-select" required>
                                                @foreach($variants as $variant)
                                                    <option value="{{ $variant->id }}" @selected($item->product_variant_id == $variant->id)>
                                                        {{ $variant->product?->name_ar }} — SKU: {{ $variant->sku }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select name="items[{{ $idx }}][direction]" class="form-select text-center direction-select" required>
                                                <option value="in" @selected($item->direction->value === 'in')>إضافة (+ وارد)</option>
                                                <option value="out" @selected($item->direction->value === 'out')>عجز (- منصرف)</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0.0001" name="items[{{ $idx }}][quantity]" class="form-control text-center fw-bold" value="{{ (float) $item->quantity }}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="items[{{ $idx }}][notes]" class="form-control form-control-sm" value="{{ $item->notes }}">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row" {{ $adjustment->items->count() === 1 ? 'disabled' : '' }}>
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.stock-adjustments.show', $adjustment) }}" class="btn btn-light">إلغاء</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i> حفظ التعديلات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let rowIndex = {{ $adjustment->items->count() }};
    const tableBody = document.querySelector('#adj-items-table tbody');
    const addBtn = document.getElementById('add-item-row');

    addBtn.addEventListener('click', function() {
        const firstRowVariant = tableBody.querySelector('tr select[name*="product_variant_id"]').cloneNode(true);
        firstRowVariant.name = `items[${rowIndex}][product_variant_id]`;
        firstRowVariant.value = '';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td></td>
            <td>
                <select name="items[${rowIndex}][direction]" class="form-select text-center direction-select" required>
                    <option value="in">إضافة (+ وارد)</option>
                    <option value="out">عجز (- منصرف)</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.0001" min="0.0001" name="items[${rowIndex}][quantity]" class="form-control text-center fw-bold" placeholder="0.00" required>
            </td>
            <td>
                <input type="text" name="items[${rowIndex}][notes]" class="form-control form-control-sm" placeholder="ملاحظات...">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </td>
        `;
        tr.children[0].appendChild(firstRowVariant);
        tableBody.appendChild(tr);
        rowIndex++;
        updateRemoveButtons();
    });

    tableBody.addEventListener('click', function(e) {
        if (e.target.closest('.remove-row')) {
            e.target.closest('tr').remove();
            updateRemoveButtons();
        }
    });

    function updateRemoveButtons() {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(r => {
            const btn = r.querySelector('.remove-row');
            btn.disabled = (rows.length === 1);
        });
    }
});
</script>
@endsection
