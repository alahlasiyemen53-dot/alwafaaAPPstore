@extends('layouts.admin')
@section('title', ($product ? 'تعديل منتج' : 'منتج جديد').' | لمّة')
@section('page-title', $product ? 'تعديل منتج' : 'منتج جديد')
@section('content')
@if($product)
<div class="page-heading">
    <div>
        <h2 class="fs-18 mb-1">تعديل: {{ $product->name_ar }}</h2>
        <p dir="ltr" class="text-muted mb-0">{{ $product->slug }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light" href="{{ route('admin.products.index') }}">العودة للمنتجات</a>
        @if(auth()->user()->hasPermission('admin.products.view'))
            <a class="btn btn-soft-info" href="{{ route('admin.products.show', $product) }}">
                <i class="ri-eye-line me-1"></i> عرض المنتج
            </a>
        @endif
    </div>
</div>
@endif
<div class="row justify-content-center"><div class="col-xl-10"><form method="POST" action="{{ $product ? route('admin.products.update', $product) : route('admin.products.store') }}">@csrf @if($product) @method('PUT') @endif
<div class="card"><div class="card-header"><h2 class="card-title mb-0">المعلومات الأساسية</h2></div><div class="card-body"><div class="row g-3">
    <div class="col-md-6"><label for="name_ar" class="form-label required">الاسم العربي</label><input class="form-control @error('name_ar') is-invalid @enderror" id="name_ar" name="name_ar" value="{{ old('name_ar', $product?->name_ar) }}" required>@error('name_ar')<span class="field-error">{{ $message }}</span>@enderror</div>
    <div class="col-md-6"><label for="name_en" class="form-label">الاسم الإنجليزي</label><input class="form-control @error('name_en') is-invalid @enderror" id="name_en" name="name_en" value="{{ old('name_en', $product?->name_en) }}" dir="ltr">@error('name_en')<span class="field-error">{{ $message }}</span>@enderror</div>
    @if($product)
    <div class="col-md-6">
        <label class="form-label">المعرّف النصي (Slug)</label>
        <div class="input-group">
            <input type="text" class="form-control bg-light" value="{{ $product->slug }}" readonly dir="ltr">
            <span class="input-group-text text-muted"><i class="ri-lock-line" title="غير قابل للتعديل"></i></span>
        </div>
        <small class="text-muted">المعرّف النصي ثابت ولا يتغير عند تعديل اسم المنتج.</small>
    </div>
    @endif
    <div class="col-md-3"><label for="brand_id" class="form-label required">العلامة التجارية</label><select class="form-control @error('brand_id') is-invalid @enderror" id="brand_id" name="brand_id" data-choices required><option value="">اختر العلامة التجارية</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string) old('brand_id', $product?->brand_id) === (string) $brand->id)>{{ $brand->name_ar }}</option>@endforeach</select>@error('brand_id')<span class="field-error">{{ $message }}</span>@enderror</div>
    <div class="col-md-3"><label for="category_id" class="form-label required">التصنيف</label><select class="form-control @error('category_id') is-invalid @enderror" id="category_id" name="category_id" data-choices required><option value="">اختر التصنيف</option>@foreach($categories as $id => $label)<option value="{{ $id }}" @selected((string) old('category_id', $product?->category_id) === (string) $id)>{{ $label }}</option>@endforeach</select>@error('category_id')<span class="field-error">{{ $message }}</span>@enderror</div>
    <div class="col-md-3"><label for="supplier_id" class="form-label">المورد (مستوى المنتج)</label><select class="form-control @error('supplier_id') is-invalid @enderror" id="supplier_id" name="supplier_id" data-choices><option value="">بدون مورد</option>@foreach($suppliers ?? [] as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $product?->supplier_id) === (string) $supplier->id)>{{ $supplier->name }} ({{ $supplier->code }})</option>@endforeach</select><small class="text-muted d-block">يُستخدم للتوريد الداخلي ويُفضّل تعيينه على مستوى المنتج.</small>@error('supplier_id')<span class="field-error">{{ $message }}</span>@enderror</div>
    <div class="col-md-3"><label for="supplier_item_number" class="form-label">رقم الصنف لدى المورد</label><input type="text" maxlength="100" class="form-control @error('supplier_item_number') is-invalid @enderror" id="supplier_item_number" name="supplier_item_number" value="{{ old('supplier_item_number', $product?->supplier_item_number) }}" dir="ltr" placeholder="مرجع المورد للمنتج"><small class="text-muted d-block">على مستوى الصنف الأساسي. يمكن تخصيص رقم مختلف لكل متغير أيضاً.</small>@error('supplier_item_number')<span class="field-error">{{ $message }}</span>@enderror</div>
    @if($product)<div class="col-md-6"><span class="form-label d-block">الحالة الحالية</span>@include('admin.products.partials.status', ['status' => $product->status])<div class="form-text">تُغيّر الحالة من أزرار التفعيل والتعطيل المخصصة.</div></div>@else<div class="col-md-6"><label for="status" class="form-label required">الحالة</label><select class="form-control @error('status') is-invalid @enderror" id="status" name="status" data-choices data-choices-search-false required><option value="draft" @selected(old('status', 'draft') === 'draft')>مسودة</option><option value="active" @selected(old('status') === 'active')>نشط</option><option value="inactive" @selected(old('status') === 'inactive')>معطل</option></select>@error('status')<span class="field-error">{{ $message }}</span>@enderror</div>@endif
    <div class="col-md-6">
        <div class="form-check form-switch mt-4">
            <input type="hidden" name="has_variants" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="has_variants" name="has_variants" value="1" @checked(old('has_variants', $product?->has_variants ?? false))>
            <label class="form-check-label fw-bold" for="has_variants">للمنتج خيارات متعددة ظاهرة للعميل</label>
            <div class="form-text">قم بالتفعيل إذا كان المنتج يتوفر بأحجام، ألوان، أو خيارات مختلفة يختار منها العميل.</div>
        </div>
    </div>

    <div class="col-12" id="variants_attributes_container" style="{{ old('has_variants', $product?->has_variants ?? false) ? '' : 'display: none;' }}">
        <div class="card border border-primary-subtle bg-light mb-0">
            <div class="card-header bg-primary-subtle bg-opacity-25 border-bottom border-primary-subtle py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-node-tree text-primary fs-18"></i>
                    <span class="fw-bold text-primary">الخصائص المتاحة في النظام لخيارات المنتج:</span>
                </div>
                @if(auth()->user()->hasPermission('admin.attributes.view'))
                    <a href="{{ route('admin.attributes.index') }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2 fs-12">
                        <i class="ri-settings-3-line me-1"></i> إدارة الخصائص والقيم
                    </a>
                @endif
            </div>
            <div class="card-body py-3">
                @if(isset($attributes) && $attributes->isNotEmpty())
                    <div class="row g-3">
                        @foreach($attributes as $attr)
                            <div class="col-md-6 col-lg-4">
                                <div class="p-2 rounded bg-white border border-light-subtle shadow-sm h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold text-dark fs-13"><i class="ri-price-tag-3-line text-muted me-1"></i>{{ $attr->name_ar }}</span>
                                        <span class="badge bg-secondary-subtle text-secondary fs-11" dir="ltr">{{ $attr->code }}</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @forelse($attr->values as $val)
                                            <span class="badge bg-light text-body border fs-11">
                                                {{ $val->value_ar }}
                                            </span>
                                        @empty
                                            <span class="text-muted fs-11 fst-italic">لا توجد قيم مضافة لهذه الخاصية</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="alert alert-info py-2 px-3 fs-12 mt-3 mb-0 d-flex align-items-center gap-2">
                        <i class="ri-information-line fs-16"></i>
                        <span>
                            <strong>ملاحظة للمتغيرات:</strong> تفعيل هذا الخيار يجعل المنتج متعدد الخيارات للعميل. بعد حفظ المنتج، يمكنك الانتقال إلى صفحة تفاصيل المنتج لإضافة وتوليد المتغيرات وتخصيص أسعارها ورموز SKU وصورها بناءً على هذه الخصائص.
                        </span>
                    </div>
                @else
                    <div class="text-center py-2 text-muted fs-13">
                        <i class="ri-alert-line text-warning me-1"></i>
                        لا توجد خصائص نشطة مسجلة في النظام حالياً.
                        @if(auth()->user()->hasPermission('admin.attributes.create'))
                            <a href="{{ route('admin.attributes.index') }}" target="_blank" class="text-primary fw-semibold ms-1">أضف خصائص جديدة من هنا</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div></div></div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0"><i class="ri-price-tag-2-line text-primary me-1"></i> العرض والتسويق (Merchandising)</h2>
        <span class="badge bg-light text-muted border">شارات مظهرية للمتجر</span>
    </div>
    <div class="card-body">
        <p class="text-muted fs-13 mb-3">
            حدد الشارات التسويقية الخاصة بالمنتج لتمييزه في المتجر والتطبيقات. هذه الإعدادات إدارية بحتة ولا تغيّر قواعد التسعير أو التوفر أو المخزون.
        </p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light-subtle h-100">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_best_seller" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_best_seller" name="is_best_seller" value="1" @checked(old('is_best_seller', $product?->is_best_seller ?? false))>
                        <label class="form-check-label fw-bold text-dark" for="is_best_seller">
                            <span class="badge bg-info-subtle text-info me-1">الأكثر مبيعاً</span>
                        </label>
                    </div>
                    <div class="form-text fs-12 text-muted">
                        تمييز المنتج بشارة "الأكثر مبيعاً" في قوائم التصفح وواجهات العرض التسويقية.
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="p-3 border rounded bg-light-subtle h-100">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_new" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_new" name="is_new" value="1" @checked(old('is_new', $product?->is_new ?? false))>
                        <label class="form-check-label fw-bold text-dark" for="is_new">
                            <span class="badge bg-primary-subtle text-primary me-1">جديد</span>
                        </label>
                    </div>
                    <div class="form-text fs-12 text-muted">
                        إبراز المنتج كوصل حديثاً في أقسام المنتجات الجديدة وقوائم التوصيات.
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="p-3 border rounded bg-light-subtle h-100">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_exclusive" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_exclusive" name="is_exclusive" value="1" @checked(old('is_exclusive', $product?->is_exclusive ?? false))>
                        <label class="form-check-label fw-bold text-dark" for="is_exclusive">
                            <span class="badge bg-warning-subtle text-warning me-1">حصري</span>
                        </label>
                    </div>
                    <div class="form-text fs-12 text-muted">
                        إضافة وسم حصري لمنصة لمّة (لا يمنع شراءه من أي فئة عملاء محددة).
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card"><div class="card-header"><h2 class="card-title mb-0">الوصف</h2></div><div class="card-body"><div class="row g-3"><div class="col-md-6"><label for="description_ar" class="form-label">الوصف العربي</label><textarea class="form-control @error('description_ar') is-invalid @enderror" id="description_ar" name="description_ar" rows="6">{{ old('description_ar', $product?->description_ar) }}</textarea>@error('description_ar')<span class="field-error">{{ $message }}</span>@enderror</div><div class="col-md-6"><label for="description_en" class="form-label">الوصف الإنجليزي</label><textarea class="form-control @error('description_en') is-invalid @enderror" id="description_en" name="description_en" rows="6" dir="ltr">{{ old('description_en', $product?->description_en) }}</textarea>@error('description_en')<span class="field-error">{{ $message }}</span>@enderror</div></div>
@if($product && ((filled($product->supplier_item_number)) || $product->variants()->whereNotNull('supplier_item_number')->where('supplier_item_number', '!=', '')->exists()))
<div class="alert alert-warning mt-3 mb-0">
    <div class="form-check">
        <input type="hidden" name="confirm_supplier_change" value="0">
        <input class="form-check-input" type="checkbox" id="confirm_supplier_change" name="confirm_supplier_change" value="1" @checked(old('confirm_supplier_change'))>
        <label class="form-check-label fw-semibold" for="confirm_supplier_change">أؤكد تغيير المورد ومسح أرقام الصنف لدى المورد على جميع المتغيرات</label>
    </div>
    <small class="text-muted d-block mt-1">مطلوب عند تغيير المورد بينما توجد أرقام صنف مسجلة أو مهام توفير نشطة.</small>
</div>
@endif
<div class="form-actions mt-4"><a class="btn btn-light" href="{{ $product ? route('admin.products.show', $product) : route('admin.products.index') }}">إلغاء</a><button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i> حفظ المنتج</button></div></div></div>
</form></div></div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const hasVariantsCheckbox = document.getElementById('has_variants');
        const attributesContainer = document.getElementById('variants_attributes_container');
        if (hasVariantsCheckbox && attributesContainer) {
            hasVariantsCheckbox.addEventListener('change', function () {
                if (this.checked) {
                    attributesContainer.style.display = '';
                } else {
                    attributesContainer.style.display = 'none';
                }
            });
        }
    });
</script>
@endpush
