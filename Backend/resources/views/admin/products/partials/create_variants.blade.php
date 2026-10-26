@php
    $variantRows = old('variants');
    if (! is_array($variantRows) || $variantRows === []) {
        $variantRows = [[
            'price' => '',
            'compare_at_price' => '',
            'barcode' => '',
            'supplier_item_number' => '',
            'fulfillment_mode' => 'stocked',
            'supplier_id' => '',
            'status' => 'active',
            'is_default' => true,
            'attribute_value_ids' => [],
        ]];
    }
    $multi = (bool) old('has_variants', false);
@endphp

<div class="card mt-3" id="create-variants-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="card-title mb-0">متغيرات المنتج والتسعير</h2>
        <button type="button" class="btn btn-sm btn-primary" id="add-variant-row">
            <i class="ri-add-line me-1"></i> إضافة متغير جديد
        </button>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="primary_image">الصورة الرئيسية للمنتج</label>
                <input id="primary_image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('primary_image') is-invalid @enderror" name="primary_image">
                <small class="text-muted">JPG أو PNG أو WEBP، بحد أقصى 5 ميغابايت. تظهر كصورة المنتج في المتجر.</small>
                @error('primary_image')<span class="field-error">{{ $message }}</span>@enderror
                <img id="primary-image-preview" class="d-none mt-2 rounded border bg-light" alt="" style="width: 88px; height: 88px; object-fit: contain;">
            </div>
        </div>
        <p class="text-muted fs-13 mb-3">أدخل سعر كل متغير، ويمكنك إضافة صورته. زر «إضافة متغير جديد» يفتح صفاً آخر ويفعّل الخيارات المتعددة حتى تربط كل متغير بخصائصه.</p>
        <div id="create-variant-rows">
            @foreach($variantRows as $index => $row)
                @php
                    $selectedAttributes = array_map('intval', (array) ($row['attribute_value_ids'] ?? []));
                    $mode = $row['fulfillment_mode'] ?? 'stocked';
                    $isDefault = filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
                @endphp
                <div class="border rounded p-3 mb-3 create-variant-row" data-index="{{ $index }}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="fs-15 mb-0">المتغير <span class="variant-number">{{ $index + 1 }}</span></h3>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-variant-row" @if(count($variantRows) === 1) hidden @endif>حذف</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="variant-{{ $index }}-price">السعر بالعملة الأساسية <span class="text-danger">*</span></label>
                            <input id="variant-{{ $index }}-price" type="number" step="any" min="0" required class="form-control @error("variants.$index.price") is-invalid @enderror" name="variants[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}">
                            @error("variants.$index.price")<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="variant-{{ $index }}-compare">سعر المقارنة</label>
                            <input id="variant-{{ $index }}-compare" type="number" step="any" min="0" class="form-control @error("variants.$index.compare_at_price") is-invalid @enderror" name="variants[{{ $index }}][compare_at_price]" value="{{ $row['compare_at_price'] ?? '' }}">
                            <small class="text-muted">أعلى من السعر الفعلي إن وُجد</small>
                            @error("variants.$index.compare_at_price")<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="variant-{{ $index }}-barcode">الباركود</label>
                            <input id="variant-{{ $index }}-barcode" type="text" maxlength="64" class="form-control @error("variants.$index.barcode") is-invalid @enderror" name="variants[{{ $index }}][barcode]" value="{{ $row['barcode'] ?? '' }}" dir="ltr">
                            @error("variants.$index.barcode")<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="variant-{{ $index }}-image">صورة المتغير</label>
                            <input id="variant-{{ $index }}-image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control variant-image @error("variants.$index.image") is-invalid @enderror" name="variants[{{ $index }}][image]">
                            <small class="text-muted">اختيارية. إن تُركت فارغة تُعرض الصورة الرئيسية للمنتج.</small>
                            @error("variants.$index.image")<span class="field-error">{{ $message }}</span>@enderror
                            <img class="variant-image-preview d-none mt-2 rounded border bg-light" alt="" style="width: 72px; height: 72px; object-fit: contain;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="variant-{{ $index }}-item">رقم الصنف لدى المورد</label>
                            <input id="variant-{{ $index }}-item" type="text" maxlength="100" class="form-control" name="variants[{{ $index }}][supplier_item_number]" value="{{ $row['supplier_item_number'] ?? '' }}" dir="ltr">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="variant-{{ $index }}-status">الحالة</label>
                            <select id="variant-{{ $index }}-status" class="form-select" name="variants[{{ $index }}][status]">
                                <option value="active" @selected(($row['status'] ?? 'active') === 'active')>نشط</option>
                                <option value="inactive" @selected(($row['status'] ?? '') === 'inactive')>معطل</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" class="variant-default-flag" name="variants[{{ $index }}][is_default]" value="{{ $isDefault ? '1' : '0' }}">
                                <input class="form-check-input variant-default-toggle" type="checkbox" id="variant-{{ $index }}-default" @checked($isDefault)>
                                <label class="form-check-label" for="variant-{{ $index }}-default">المتغير الافتراضي</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">طريقة التوفير</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input fulfillment-mode-radio" type="radio" name="variants[{{ $index }}][fulfillment_mode]" id="variant-{{ $index }}-stocked" value="stocked" @checked($mode !== 'on_demand')>
                                    <label class="form-check-label" for="variant-{{ $index }}-stocked">مخزني</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input fulfillment-mode-radio" type="radio" name="variants[{{ $index }}][fulfillment_mode]" id="variant-{{ $index }}-ondemand" value="on_demand" @checked($mode === 'on_demand')>
                                    <label class="form-check-label" for="variant-{{ $index }}-ondemand">توفير عند الطلب</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 variant-supplier" @if($mode !== 'on_demand') hidden @endif>
                            <label class="form-label" for="variant-{{ $index }}-supplier">المورد المعتمد <span class="text-danger">*</span></label>
                            <select id="variant-{{ $index }}-supplier" class="form-select @error("variants.$index.supplier_id") is-invalid @enderror" name="variants[{{ $index }}][supplier_id]">
                                <option value="">-- اختر المورد --</option>
                                @foreach($suppliers ?? [] as $supp)
                                    <option value="{{ $supp->id }}" @selected((string) ($row['supplier_id'] ?? '') === (string) $supp->id)>{{ $supp->name }} ({{ $supp->code }})</option>
                                @endforeach
                            </select>
                            @error("variants.$index.supplier_id")<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-12 variant-attributes" @if(! $multi) hidden @endif>
                            <div class="border-top pt-3">
                                <h4 class="fs-14 mb-2">قيم الخصائص</h4>
                                @error("variants.$index.attribute_value_ids")<span class="field-error d-block mb-2">{{ $message }}</span>@enderror
                                <div class="row g-2">
                                    @forelse($attributes ?? [] as $attr)
                                        <div class="col-md-4">
                                            <label class="form-label fs-12 mb-1">{{ $attr->name_ar }}</label>
                                            <select class="form-select form-select-sm" name="variants[{{ $index }}][attribute_value_ids][]">
                                                <option value="">بدون تحديد</option>
                                                @foreach($attr->values as $val)
                                                    <option value="{{ $val->id }}" @selected(in_array((int) $val->id, $selectedAttributes, true))>{{ $val->value_ar }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @empty
                                        <div class="col-12"><span class="text-muted fs-12">لا توجد خصائص مضافة في النظام.</span></div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById('create-variant-rows');
    const addButton = document.getElementById('add-variant-row');
    const hasVariants = document.getElementById('has_variants');
    if (!rows || !addButton) return;

    function syncMode() {
        const multi = hasVariants && hasVariants.checked;
        rows.querySelectorAll('.create-variant-row').forEach(function (row, index) {
            const attributes = row.querySelector('.variant-attributes');
            if (attributes) attributes.hidden = !multi;
            const extra = index > 0;
            row.hidden = !multi && extra;
            row.querySelectorAll('input, select').forEach(function (field) {
                field.disabled = !multi && extra;
            });
        });
        refreshRemoveButtons();
    }

    function refreshRemoveButtons() {
        const visible = Array.from(rows.querySelectorAll('.create-variant-row')).filter(function (row) { return !row.hidden; });
        rows.querySelectorAll('.remove-variant-row').forEach(function (button) {
            button.hidden = visible.length < 2;
        });
    }

    function reindex() {
        rows.querySelectorAll('.create-variant-row').forEach(function (row, index) {
            row.dataset.index = String(index);
            const number = row.querySelector('.variant-number');
            if (number) number.textContent = String(index + 1);
            row.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/variants\[\d+\]/, 'variants[' + index + ']');
            });
            row.querySelectorAll('[id]').forEach(function (field) {
                field.id = field.id.replace(/variant-\d+/, 'variant-' + index);
            });
            row.querySelectorAll('label[for]').forEach(function (label) {
                label.htmlFor = label.htmlFor.replace(/variant-\d+/, 'variant-' + index);
            });
        });
    }

    function bindRow(row) {
        row.querySelectorAll('.fulfillment-mode-radio').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const supplier = row.querySelector('.variant-supplier');
                if (supplier) supplier.hidden = radio.value !== 'on_demand' || !radio.checked;
            });
        });
        const toggle = row.querySelector('.variant-default-toggle');
        if (toggle) {
            toggle.addEventListener('change', function () {
                if (!toggle.checked) {
                    row.querySelector('.variant-default-flag').value = '0';
                    return;
                }
                rows.querySelectorAll('.create-variant-row').forEach(function (other) {
                    const flag = other.querySelector('.variant-default-flag');
                    const box = other.querySelector('.variant-default-toggle');
                    const selected = other === row;
                    if (flag) flag.value = selected ? '1' : '0';
                    if (box) box.checked = selected;
                });
            });
        }
        const remove = row.querySelector('.remove-variant-row');
        if (remove) {
            remove.addEventListener('click', function () {
                if (rows.querySelectorAll('.create-variant-row').length < 2) return;
                row.remove();
                reindex();
                syncMode();
            });
        }
        const image = row.querySelector('.variant-image');
        const preview = row.querySelector('.variant-image-preview');
        if (image && preview) {
            image.addEventListener('change', function () {
                const file = image.files && image.files[0];
                if (!file) {
                    preview.classList.add('d-none');
                    preview.removeAttribute('src');
                    return;
                }
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('d-none');
            });
        }
    }

    rows.querySelectorAll('.create-variant-row').forEach(bindRow);

    addButton.addEventListener('click', function () {
        if (hasVariants && !hasVariants.checked) {
            hasVariants.checked = true;
            hasVariants.dispatchEvent(new Event('change'));
        }
        const source = rows.querySelector('.create-variant-row');
        if (!source) return;
        const copy = source.cloneNode(true);
        copy.querySelectorAll('input').forEach(function (field) {
            if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
            else if (field.classList.contains('variant-default-flag')) field.value = '0';
            else field.value = '';
        });
        copy.querySelectorAll('select').forEach(function (field) { field.selectedIndex = 0; });
        const supplier = copy.querySelector('.variant-supplier');
        if (supplier) supplier.hidden = true;
        const preview = copy.querySelector('.variant-image-preview');
        if (preview) {
            preview.classList.add('d-none');
            preview.removeAttribute('src');
        }
        copy.querySelectorAll('.field-error, .is-invalid').forEach(function (node) {
            node.classList.remove('is-invalid');
            if (node.classList.contains('field-error')) node.remove();
        });
        const nextIndex = rows.querySelectorAll('.create-variant-row').length;
        copy.querySelectorAll('[name]').forEach(function (field) {
            field.name = field.name.replace(/variants\[\d+\]/, 'variants[' + nextIndex + ']');
        });
        rows.appendChild(copy);
        const stocked = copy.querySelector('input[type="radio"][value="stocked"]');
        if (stocked) stocked.checked = true;
        reindex();
        bindRow(copy);
        syncMode();
    });

    const primaryImage = document.getElementById('primary_image');
    const primaryPreview = document.getElementById('primary-image-preview');
    if (primaryImage && primaryPreview) {
        primaryImage.addEventListener('change', function () {
            const file = primaryImage.files && primaryImage.files[0];
            if (!file) {
                primaryPreview.classList.add('d-none');
                primaryPreview.removeAttribute('src');
                return;
            }
            primaryPreview.src = URL.createObjectURL(file);
            primaryPreview.classList.remove('d-none');
        });
    }

    if (hasVariants) hasVariants.addEventListener('change', syncMode);
    syncMode();
});
</script>
@endpush
