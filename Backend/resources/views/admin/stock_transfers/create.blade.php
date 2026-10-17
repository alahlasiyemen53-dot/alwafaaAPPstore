@extends('layouts.admin')

@section('title', 'إنشاء أمر تحويل مخزون | لمّة')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-arrow-left-right-line me-1 text-primary"></i> أمر تحويل مخزون جديد
                    </h5>
                    <a href="{{ route('admin.stock-transfers.index') }}" class="btn btn-sm btn-light">
                        <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                    </a>
                </div>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('admin.stock-transfers.store') }}" id="transfer-form">
                    @csrf

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label required">من مستودع (المصدر)</label>
                            <select name="from_warehouse_id" id="from_warehouse_id" class="form-control" data-choices required>
                                <option value="">-- حدد مستودع المصدر --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('from_warehouse_id') == $wh->id)>
                                        {{ $wh->name_ar }} ({{ $wh->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">إلى مستودع (الوجهة)</label>
                            <select name="to_warehouse_id" id="to_warehouse_id" class="form-control" data-choices required>
                                <option value="">-- حدد مستودع الوجهة --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('to_warehouse_id') == $wh->id)>
                                        {{ $wh->name_ar }} ({{ $wh->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">ملاحظات التحويل</label>
                            <input type="text" name="notes" class="form-control" placeholder="سبب التحويل أو بيانات السائق/الشاحنة..." value="{{ old('notes') }}">
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold mb-0">
                            <i class="ri-box-3-line me-1 text-primary"></i> الأصناف المراد تحويلها:
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-item-row">
                            <i class="ri-add-line me-1"></i> إضافة صنف
                        </button>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle" id="transfer-items-table">
                            <thead class="table-light">
                                <tr>
                                    <th>الصنف / المتغير</th>
                                    <th style="width: 220px;" class="text-center">الكمية (بالوحدة الأساسية)</th>
                                    <th style="width: 60px;" class="text-center">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select name="items[0][product_variant_id]" class="form-select" required>
                                            <option value="">-- اختر الصنف المتغير --</option>
                                            @foreach($variants as $variant)
                                                <option value="{{ $variant->id }}">
                                                    {{ $variant->product?->name_ar }} — SKU: {{ $variant->sku }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" min="0.0001" name="items[0][quantity]" class="form-control text-center fw-bold" placeholder="0.00" required>
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
                        <a href="{{ route('admin.stock-transfers.index') }}" class="btn btn-light">إلغاء</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i> حفظ مسودة أمر التحويل
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
    const tableBody = document.querySelector('#transfer-items-table tbody');
    const addBtn = document.getElementById('add-item-row');

    addBtn.addEventListener('click', function() {
        const firstRowSelect = tableBody.querySelector('tr select').cloneNode(true);
        firstRowSelect.name = `items[${rowIndex}][product_variant_id]`;
        firstRowSelect.value = '';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td></td>
            <td>
                <input type="number" step="0.0001" min="0.0001" name="items[${rowIndex}][quantity]" class="form-control text-center fw-bold" placeholder="0.00" required>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </td>
        `;
        tr.children[0].appendChild(firstRowSelect);
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
            if (rows.length === 1) {
                btn.disabled = true;
            } else {
                btn.disabled = false;
            }
        });
    }
});
</script>
@endsection
