@extends('layouts.admin')

@section('title', 'إضافة عرض ترويجي جديد')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">إضافة عرض ترويجي</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.marketing.promotions.index') }}">العروض الترويجية</a></li>
                        <li class="breadcrumb-item active">إضافة عرض</li>
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

    <form method="POST" action="{{ route('admin.marketing.promotions.store') }}">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">البيانات الأساسية للعرض الترويجي</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">كود العرض <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control font-monospace" placeholder="مثال: SUMMER2026" value="{{ old('code') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">اسم العرض <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="مثال: خصم موسم الصيف 20%" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">وصف العرض</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="وصف توضيحي للشروط...">{{ old('description') }}</textarea>
                            </div>
                            @php
                                $baseCurrency = \App\Models\Currency::where('is_default', true)->first();
                                $baseSymbol = $baseCurrency?->symbol ?? 'ر.ي';
                            @endphp
                            <div class="col-md-6">
                                <label class="form-label">نوع الخصم <span class="text-danger">*</span></label>
                                <select name="type" id="promotion_type" class="form-select" required>
                                    <option value="percentage" {{ old('type') === 'percentage' ? 'selected' : '' }}>نسبة مئوية (%)</option>
                                    <option value="fixed_amount" {{ old('type') === 'fixed_amount' ? 'selected' : '' }}>مبلغ خصم ثابت</option>
                                    <option value="fixed_price" {{ old('type') === 'fixed_price' ? 'selected' : '' }}>سعر بيع مخفض ثابت</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">قيمة الخصم / السعر <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.0001" min="0.0001" name="value" id="promotion_value" class="form-control" placeholder="القيمة..." value="{{ old('value') }}" required>
                                    <span class="input-group-text" id="value_currency_badge">{{ old('type') === 'percentage' ? '%' : $baseSymbol }}</span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    المبالغ الثابتة محددة بالعملة الأساسية ({{ $baseCurrency?->name_ar ?? 'الريال' }}). النسب المئوية محايدة للعملة وتطبق بنفس النسبة على أي عملة طلب.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">نطاق الاستهداف للمنتجات</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">نطاق تطبيق العرض <span class="text-danger">*</span></label>
                                <select name="scope" id="scope_select" class="form-select" required>
                                    <option value="all" {{ old('scope', 'all') === 'all' ? 'selected' : '' }}>جميع المنتجات دون استثناء</option>
                                    <option value="category" {{ old('scope') === 'category' ? 'selected' : '' }}>أقسام / فئات معينة</option>
                                    <option value="brand" {{ old('scope') === 'brand' ? 'selected' : '' }}>علامات تجارية معينة</option>
                                    <option value="product" {{ old('scope') === 'product' ? 'selected' : '' }}>منتجات محددة</option>
                                    <option value="variant" {{ old('scope') === 'variant' ? 'selected' : '' }}>متغيرات منتج محددة (SKU)</option>
                                </select>
                            </div>

                            <div class="col-12 target-group" id="target_category_group" style="display: none;">
                                <label class="form-label">اختر الفئات المستهدفة</label>
                                <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                    @foreach($categories as $category)
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="target_ids[]" value="{{ $category->id }}" id="cat_{{ $category->id }}">
                                            <label class="form-check-label" for="cat_{{ $category->id }}">{{ $category->name_ar }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-12 target-group" id="target_brand_group" style="display: none;">
                                <label class="form-label">اختر العلامات التجارية المستهدفة</label>
                                <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                    @foreach($brands as $brand)
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="target_ids[]" value="{{ $brand->id }}" id="brand_{{ $brand->id }}">
                                            <label class="form-check-label" for="brand_{{ $brand->id }}">{{ $brand->name_ar }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-12 target-group" id="target_product_group" style="display: none;">
                                <label class="form-label">اختر المنتجات المستهدفة</label>
                                <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                    @foreach($products as $product)
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="target_ids[]" value="{{ $product->id }}" id="prod_{{ $product->id }}">
                                            <label class="form-check-label" for="prod_{{ $product->id }}">{{ $product->name_ar }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-12 target-group" id="target_variant_group" style="display: none;">
                                <label class="form-label">اختر المتغيرات المستهدفة (SKU)</label>
                                <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                    @foreach($variants as $variant)
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="target_ids[]" value="{{ $variant->id }}" id="var_{{ $variant->id }}">
                                            <label class="form-check-label" for="var_{{ $variant->id }}">
                                                {{ $variant->product?->name_ar }} - <span class="font-monospace text-primary">[{{ $variant->sku }}]</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">الجدول الزمني والتحكم</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">تاريخ ووقت البدء</label>
                            <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at') }}">
                            <small class="text-muted">اتركه فارغاً ليكون مفعّلاً فوراً.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تاريخ ووقت الانتهاء</label>
                            <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at') }}">
                            <small class="text-muted">اتركه فارغاً ليستمر العرض دون أجل مسمى.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">أولوية العرض (Priority)</label>
                            <input type="number" name="priority" class="form-control" value="{{ old('priority', 0) }}" min="0">
                            <small class="text-muted">الرقم الأعلى له أسبقية فك التعارض عند التساوي.</small>
                        </div>
                        <div class="mb-3 form-check form-switch form-switch-lg">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">تفعيل العرض الترويجي فوراً</label>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-success w-100">حفظ العرض</button>
                            <a href="{{ route('admin.marketing.promotions.index') }}" class="btn btn-light w-100">إلغاء</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const scopeSelect = document.getElementById('scope_select');
    const targetGroups = {
        category: document.getElementById('target_category_group'),
        brand: document.getElementById('target_brand_group'),
        product: document.getElementById('target_product_group'),
        variant: document.getElementById('target_variant_group'),
    };

    function updateScopeVisibility() {
        const val = scopeSelect.value;
        Object.keys(targetGroups).forEach(k => {
            if (targetGroups[k]) {
                const isVisible = (k === val);
                targetGroups[k].style.display = isVisible ? 'block' : 'none';
                targetGroups[k].querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    cb.disabled = !isVisible;
                });
            }
        });
    }

    scopeSelect.addEventListener('change', updateScopeVisibility);
    updateScopeVisibility();

    const promoType = document.getElementById('promotion_type');
    const badge = document.getElementById('value_currency_badge');
    if (promoType && badge) {
        promoType.addEventListener('change', function() {
            badge.textContent = this.value === 'percentage' ? '%' : @json($baseSymbol);
        });
    }
});
</script>
@endsection
