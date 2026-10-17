@extends('layouts.admin')

@section('title', 'إنشاء أمر تسوية جردية | الوفاء استور')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-equalizer-line me-1 text-primary"></i> أمر تسوية جردية جديد
                    </h5>
                    <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-sm btn-light">
                        <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="alert alert-warning border-0 mb-4">
                    <i class="ri-alert-line me-1"></i>
                    <strong>تنبيه إداري:</strong> عمليات التسوية الجردية وشطب التالف تؤثر بصورة مباشرة وموثقة على المخزون الفعلي، ولا يمكن تعديلها بعد الترحيل.
                </div>

                <form method="POST" action="{{ route('admin.stock-adjustments.store') }}" id="adj-form">
                    @csrf

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label required">المستودع</label>
                            <select name="warehouse_id" class="form-control" data-choices required>
                                <option value="">-- اختر المستودع --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('warehouse_id') == $wh->id)>
                                        {{ $wh->name_ar }} ({{ $wh->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">سبب التسوية</label>
                            <select name="reason_code" id="reason_code" class="form-control" data-choices data-choices-search-false required>
                                <option value="">-- اختر سبب التسوية --</option>
                                @foreach($reasons as $reason)
                                    <option value="{{ $reason->value }}" @selected(old('reason_code') === $reason->value)>
                                        {{ $reason->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">ملاحظات ومبررات التسوية</label>
                            <input type="text" name="notes" class="form-control" placeholder="محضر الجرد / تقرير التلف / سبب التصحيح..." value="{{ old('notes') }}">
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
                                <tr>
                                    <td>
                                        <select name="items[0][product_variant_id]" class="form-select" required>
                                            <option value="">-- اختر الصنف --</option>
                                            @foreach($variants as $variant)
                                                <option value="{{ $variant->id }}">
                                                    {{ $variant->product?->name_ar }} — SKU: {{ $variant->sku }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="items[0][direction]" class="form-select text-center direction-select" required>
                                            <option value="in">إضافة (+ وارد)</option>
                                            <option value="out">عجز (- منصرف)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" min="0.0001" name="items[0][quantity]" class="form-control text-center fw-bold" placeholder="0.00" required>
                                    </td>
                                    <td>
                                        <input type="text" name="items[0][notes]" class="form-control form-control-sm" placeholder="ملاحظات...">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" disabled>
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-light">إلغاء</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i> حفظ مسودة أمر التسوية
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let rowIndex = 1;
    const tableBody = document.querySelector('#adj-items-table tbody');
    const addBtn = document.getElementById('add-item-row');
    const reasonSelect = document.getElementById('reason_code');

    function syncDirectionOptions() {
        const isDamaged = reasonSelect.value === 'damaged';
        const dirSelects = tableBody.querySelectorAll('.direction-select');
        dirSelects.forEach(s => {
            if (isDamaged) {
                s.value = 'out';
                s.querySelector('option[value="in"]').disabled = true;
            } else {
                s.querySelector('option[value="in"]').disabled = false;
            }
        });
    }

    reasonSelect.addEventListener('change', syncDirectionOptions);

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
        syncDirectionOptions();
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
