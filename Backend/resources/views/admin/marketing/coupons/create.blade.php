@extends('layouts.admin')

@section('title', 'إنشاء كوبون خصم جديد')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">إنشاء كوبون خصم</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.marketing.coupons.index') }}">كوبونات الخصم</a></li>
                        <li class="breadcrumb-item active">إنشاء كوبون</li>
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

    <form method="POST" action="{{ route('admin.marketing.coupons.store') }}">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">بيانات الكوبون الأساسية وقيمة الخصم</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">كود الكوبون <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control font-monospace" placeholder="مثال: WELCOME10" value="{{ old('code') }}" style="text-transform: uppercase;" required>
                                <small class="text-muted">سيتم تحويله تلقائياً لأحرف كبيرة وإزالة المسافات.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">اسم / وصف الكوبون <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="مثال: خصم الترحيب بالعملاء الجدد" value="{{ old('name') }}" required>
                            </div>
                            @php
                                $baseCurrency = \App\Models\Currency::where('is_default', true)->first();
                                $baseSymbol = $baseCurrency?->symbol ?? 'ر.ي';
                            @endphp
                            <div class="col-md-6">
                                <label class="form-label">نوع الخصم <span class="text-danger">*</span></label>
                                <select name="discount_type" id="discount_type" class="form-select" required>
                                    <option value="percentage" {{ old('discount_type') === 'percentage' ? 'selected' : '' }}>نسبة مئوية (%)</option>
                                    <option value="fixed_amount" {{ old('discount_type') === 'fixed_amount' ? 'selected' : '' }}>مبلغ خصم ثابت</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">قيمة الخصم <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.0001" min="0.0001" name="discount_value" id="discount_value" class="form-control" placeholder="القيمة..." value="{{ old('discount_value') }}" required>
                                    <span class="input-group-text" id="discount_type_badge">{{ old('discount_type') === 'fixed_amount' ? $baseSymbol : '%' }}</span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    المبالغ الثابتة محددة بالعملة الأساسية ({{ $baseCurrency?->name_ar ?? 'الريال' }}). النسب المئوية محايدة للعملة وتطبق بنفس النسبة على أي عملة طلب.
                                </small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الحد الأدنى لقيمة الطلب</label>
                                <div class="input-group">
                                    <input type="number" step="0.0001" min="0" name="minimum_order_amount" class="form-control" placeholder="اختياري..." value="{{ old('minimum_order_amount') }}">
                                    <span class="input-group-text">{{ $baseSymbol }}</span>
                                </div>
                                <small class="text-muted">المجموع الفرعي المطلوب بالعملة الأساسية لتفعيل الكوبون.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">السقف الأعلى للخصم (Maximum Cap)</label>
                                <div class="input-group">
                                    <input type="number" step="0.0001" min="0" name="maximum_discount_amount" class="form-control" placeholder="اختياري (لنسب الخصم)..." value="{{ old('maximum_discount_amount') }}">
                                    <span class="input-group-text">{{ $baseSymbol }}</span>
                                </div>
                                <small class="text-muted">أقصى مبلغ مالي بالعملة الأساسية يمكن خصمه في حال النسبة المئوية.</small>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_compatible_with_promotions" value="1" id="is_compatible" {{ old('is_compatible_with_promotions') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_compatible">السماح باستخدام الكوبون مع المنتجات التي عليها عروض ترويجية تلقائية</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">نطاق تطبيق الكوبون</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">نطاق تطبيق الكوبون <span class="text-danger">*</span></label>
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
                        <h5 class="card-title mb-0">حدود الاستخدام والصلاحية</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">إجمالي مرات الاستخدام المسموحة (Global Limit)</label>
                            <input type="number" name="usage_limit" class="form-control" placeholder="غير محدود..." value="{{ old('usage_limit') }}" min="1">
                            <small class="text-muted">أقصى عدد لاستخدام الكوبون لكافة العملاء مجتمعين.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الحد الأقصى لكل عميل (Per Customer)</label>
                            <input type="number" name="per_customer_limit" class="form-control" placeholder="غير محدود..." value="{{ old('per_customer_limit') }}" min="1">
                            <small class="text-muted">أقصى عدد طلبات يمكن لنفس العميل استخدام الكوبون فيها.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تاريخ ووقت البدء</label>
                            <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تاريخ ووقت الانتهاء</label>
                            <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at') }}">
                        </div>
                        <div class="mb-3 form-check form-switch form-switch-lg">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">تفعيل الكوبون فوراً</label>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-success w-100">حفظ الكوبون</button>
                            <a href="{{ route('admin.marketing.coupons.index') }}" class="btn btn-light w-100">إلغاء</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const discountType = document.getElementById('discount_type');
    const badge = document.getElementById('discount_type_badge');
    if (discountType && badge) {
        discountType.addEventListener('change', function() {
            badge.textContent = this.value === 'fixed_amount' ? @json($baseSymbol) : '%';
        });
    }

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
});
</script>
@endsection
