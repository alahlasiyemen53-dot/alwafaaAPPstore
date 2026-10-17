@extends('layouts.admin')
@section('title', $product->name_ar.' | لمّة')
@section('page-title', 'تفاصيل المنتج')
@section('content')
<div class="page-heading">
    <div>
        <h2 class="fs-18 mb-1">{{ $product->name_ar }}</h2>
        <p dir="ltr" class="text-muted mb-0">{{ $product->slug }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light" href="{{ route('admin.products.index') }}">العودة للمنتجات</a>
        @if(auth()->user()->hasPermission('admin.products.update'))
            <a class="btn btn-primary" href="{{ route('admin.products.edit', $product) }}"><i class="ri-pencil-line me-1"></i> تعديل المنتج</a>
        @endif
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">المعلومات الأساسية</h3>
            </div>
            <div class="card-body">
                <dl class="row mb-0 g-3">
                    <dt class="col-sm-4">الاسم العربي</dt>
                    <dd class="col-sm-8 fw-semibold">{{ $product->name_ar }}</dd>
                    <dt class="col-sm-4">الاسم الإنجليزي</dt>
                    <dd class="col-sm-8" dir="ltr">{{ $product->name_en ?: '—' }}</dd>
                    <dt class="col-sm-4">المعرّف النصي</dt>
                    <dd class="col-sm-8"><code dir="ltr">{{ $product->slug }}</code></dd>
                    <dt class="col-sm-4">العلامة التجارية</dt>
                    <dd class="col-sm-8">{{ $product->brand?->name_ar ?: 'بدون علامة' }}</dd>
                    <dt class="col-sm-4">التصنيف</dt>
                    <dd class="col-sm-8">{{ $product->category->name_ar }}</dd>
                    <dt class="col-sm-4">المورد</dt>
                    <dd class="col-sm-8">{{ $product->supplier?->name ?: '—' }}</dd>
                    <dt class="col-sm-4">رقم الصنف لدى المورد</dt>
                    <dd class="col-sm-8">
                        @if($product->supplier_item_number)
                            <code dir="ltr">{{ $product->supplier_item_number }}</code>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4">الحالة</dt>
                    <dd class="col-sm-8">@include('admin.products.partials.status', ['status' => $product->status])</dd>
                    <dt class="col-sm-4">خيارات متعددة</dt>
                    <dd class="col-sm-8">
                        @if($product->has_variants)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">نعم (متعدد الخيارات)</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">لا (منتج بسيط)</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">العرض والتسويق</dt>
                    <dd class="col-sm-8">
                        <div class="d-flex gap-1 flex-wrap">
                            @if($product->is_best_seller)
                                <span class="badge bg-info-subtle text-info border border-info-subtle">الأكثر مبيعاً</span>
                            @endif
                            @if($product->is_new)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">جديد</span>
                            @endif
                            @if($product->is_exclusive)
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">حصري</span>
                            @endif
                            @if(! $product->is_best_seller && ! $product->is_new && ! $product->is_exclusive)
                                <span class="text-muted fs-13">بدون شارات تسويقية</span>
                            @endif
                        </div>
                    </dd>
                    <dt class="col-sm-4">تاريخ الإنشاء</dt>
                    <dd class="col-sm-8">{{ $product->created_at?->format('Y-m-d H:i') }}</dd>
                    <dt class="col-sm-4">آخر تحديث</dt>
                    <dd class="col-sm-8">{{ $product->updated_at?->format('Y-m-d H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">الوصف</h3>
            </div>
            <div class="card-body">
                <h4 class="fs-14 fw-semibold text-muted">العربي</h4>
                <p class="preserve-lines">{{ $product->description_ar ?: 'لا يوجد وصف عربي.' }}</p>
                <hr>
                <h4 class="fs-14 fw-semibold text-muted">الإنجليزي</h4>
                <p class="preserve-lines" dir="ltr">{{ $product->description_en ?: 'لا يوجد وصف إنجليزي.' }}</p>
            </div>
        </div>

        <div class="d-flex gap-2">
            @if($product->status !== \App\Enums\ProductStatus::Active && auth()->user()->hasPermission('admin.products.activate'))
                <form method="POST" action="{{ route('admin.products.activate', $product) }}" class="flex-grow-1" data-confirm="سيتم نشر المنتج {{ $product->name_ar }} وإتاحته للاستخدام. هل تريد المتابعة؟">
                    @csrf @method('PATCH')
                    <button class="btn btn-success w-100" type="submit"><i class="ri-check-line me-1"></i> تفعيل المنتج</button>
                </form>
            @endif
            @if($product->status !== \App\Enums\ProductStatus::Inactive && auth()->user()->hasPermission('admin.products.deactivate'))
                <form method="POST" action="{{ route('admin.products.deactivate', $product) }}" class="flex-grow-1" data-confirm="سيتم تعطيل المنتج {{ $product->name_ar }} ومنع استخدامه في العمليات الجديدة. هل تريد المتابعة؟">
                    @csrf @method('PATCH')
                    <button class="btn btn-danger w-100" type="submit"><i class="ri-forbid-line me-1"></i> تعطيل المنتج</button>
                </form>
            @endif
        </div>
    </div>
</div>

@include('admin.products.partials.media_gallery')

<!-- Product Reviews & Ratings Summary Section -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="ri-star-line me-1 text-warning"></i> تقييمات ومراجعات العملاء</h3>
        @if(auth()->user()->hasPermission('admin.reviews.view'))
            <a href="{{ route('admin.reviews.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-outline-primary">
                <i class="ri-eye-line me-1"></i> إدارة مراجعات المنتج ({{ $ratingSummary['reviews_count'] }})
            </a>
        @endif
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-4 text-center border-end">
                <div class="display-5 fw-bold text-primary mb-1">{{ number_format($ratingSummary['average_rating'], 1) }}</div>
                <div class="text-warning fs-18 mb-2">
                    @for($i = 1; $i <= 5; $i++)
                        @if($i <= round($ratingSummary['average_rating']))
                            <i class="ri-star-fill"></i>
                        @else
                            <i class="ri-star-line text-muted"></i>
                        @endif
                    @endfor
                </div>
                <p class="text-muted mb-0">بناءً على {{ number_format($ratingSummary['reviews_count']) }} مراجعة معتمدة</p>
            </div>
            <div class="col-md-8">
                <div class="d-flex flex-column gap-2">
                    @foreach([5, 4, 3, 2, 1] as $star)
                        @php
                            $cnt = $ratingSummary['distribution'][$star] ?? 0;
                            $pct = $ratingSummary['reviews_count'] > 0 ? round(($cnt / $ratingSummary['reviews_count']) * 100) : 0;
                        @endphp
                        <div class="d-flex align-items-center">
                            <span class="fs-13 text-muted me-2" style="width: 50px;">{{ $star }} نجوم</span>
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $pct }}%" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="fs-13 text-muted ms-2" style="width: 60px; text-align: left;">{{ $cnt }} ({{ $pct }}%)</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Variants & Pricing Section -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="card-title mb-1"><i class="ri-stack-line me-1 text-primary"></i> متغيرات المنتج والتسعير ({{ $variants->count() }})</h3>
            <p class="text-muted fs-12 mb-0">وحدة البيع والكتالوج الأساسية. يتم تحديد السعر بالعملة الأساسية للنظام وتُحسب بقية العملات بأسعار الصرف السارية.</p>
        </div>
        @if(auth()->user()->hasPermission('admin.variants.create'))
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                <i class="ri-add-line me-1"></i> إضافة متغير جديد
            </button>
        @endif
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">الصورة</th>
                        <th>رمز التخزين (SKU)</th>
                        <th>رقم الصنف لدى المورد</th>
                        <th>الباركود</th>
                        <th>طريقة التوفير</th>
                        <th>الخصائص والتركيبة</th>
                        <th>السعر الأساسي</th>
                        <th>المعاينة بالعملات الأخرى</th>
                        <th>الحالة</th>
                        <th>افتراضي</th>
                        <th class="text-end" style="width: 220px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($variants as $variant)
                        @php
                            $basePriceRec = $variant->base_price_record;
                            $previews = $variant->converted_previews ?? [];
                            $hasCustomMedia = $variant->getMedia('variant_images')->isNotEmpty();
                            $variantThumbUrl = $mediaPresenter->getPrimaryUrlForVariant($variant, 'thumb');
                        @endphp
                        <tr @if(auth()->user()->hasPermission('admin.variants.update'))
                                class="variant-row-clickable"
                                role="button"
                                tabindex="0"
                                data-edit-modal-target="#editVariantModal{{ $variant->id }}"
                                title="اضغط لتعديل المتغير"
                            @endif>
                            <td>
                                <div class="position-relative d-inline-block">
                                    <img src="{{ $variantThumbUrl }}" alt="{{ $variant->sku }}" class="rounded border" style="width: 44px; height: 44px; object-fit: cover;">
                                    @if(!$hasCustomMedia && $product->getMedia('product_images')->isNotEmpty())
                                        <span class="badge bg-secondary-subtle text-secondary position-absolute bottom-0 end-0 p-0 px-1 border" style="font-size: 8px;" title="صورة موروثة من المنتج">منتج</span>
                                    @endif
                                </div>
                            </td>
                            <td><code class="fw-bold text-dark fs-13">{{ $variant->sku }}</code></td>
                            <td>
                                @if($variant->supplier_item_number)
                                    <code class="text-muted fs-12" dir="ltr">{{ $variant->supplier_item_number }}</code>
                                @elseif($product->supplier_item_number)
                                    <code class="text-muted fs-12" dir="ltr">{{ $product->supplier_item_number }}</code>
                                    <div class="text-muted fs-11">من المنتج</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($variant->barcode)
                                    <span class="badge bg-light text-body border" dir="ltr"><i class="ri-barcode-line me-1"></i>{{ $variant->barcode }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($variant->isOnDemand())
                                    <span class="badge {{ \App\Enums\ProductVariantFulfillmentMode::OnDemand->badgeClass() }}">
                                        <i class="ri-truck-line me-1"></i>{{ \App\Enums\ProductVariantFulfillmentMode::OnDemand->label() }}
                                    </span>
                                    @if($variant->supplier)
                                        <div class="small text-muted mt-1"><i class="ri-store-2-line me-1"></i>{{ $variant->supplier->name }}</div>
                                    @endif
                                @else
                                    <span class="badge {{ \App\Enums\ProductVariantFulfillmentMode::Stocked->badgeClass() }}">
                                        <i class="ri-archive-line me-1"></i>{{ \App\Enums\ProductVariantFulfillmentMode::Stocked->label() }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($variant->attributeValues->isNotEmpty())
                                    <div class="d-flex gap-1 flex-wrap">
                                        @foreach($variant->attributeValues as $val)
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-12">
                                                {{ $val->attribute?->name_ar }}: <strong>{{ $val->value_ar }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">متغير أساسي للمنتج</span>
                                @endif
                            </td>
                            <td>
                                @if($basePriceRec)
                                    <div class="fw-bold text-success fs-14">
                                        {{ number_format((float)$basePriceRec->price, $basePriceRec->currency?->decimal_places ?? 0) }} {{ $basePriceRec->currency?->symbol ?? $basePriceRec->currency?->code }}
                                    </div>
                                    @if($basePriceRec->compare_at_price)
                                        <small class="text-decoration-line-through text-muted">
                                            {{ number_format((float)$basePriceRec->compare_at_price, $basePriceRec->currency?->decimal_places ?? 0) }}
                                        </small>
                                    @endif
                                @else
                                    <span class="text-muted">لم يحدد</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    @foreach($previews as $code => $conv)
                                        @if(!$conv->isBase)
                                            <div class="fs-12">
                                                <span class="badge bg-light text-dark border">{{ $code }}:</span>
                                                @if($conv->isAvailable)
                                                    <span class="fw-semibold text-primary">{{ $conv->formatted }}</span>
                                                @else
                                                    <span class="text-muted fst-italic">غير متاح</span>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if($variant->status === \App\Enums\ProductVariantStatus::Active)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">معطل</span>
                                @endif
                            </td>
                            <td>
                                @if($variant->is_default)
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="ri-star-fill me-1"></i> افتراضي</span>
                                @elseif(auth()->user()->hasPermission('admin.variants.set_default'))
                                    <form method="POST" action="{{ route('admin.products.variants.set-default', [$product, $variant]) }}" data-confirm="تعيين هذا المتغير كمتغير افتراضي للمنتج؟">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-ghost-warning py-0 px-1" title="تعيين كافتراضي">
                                            <i class="ri-star-line"></i> تعيين
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1 flex-wrap variant-actions">
                                    @if(auth()->user()->hasPermission('admin.variants.media.view') || auth()->user()->hasPermission('admin.variants.media.upload'))
                                        <button type="button" class="btn btn-sm btn-soft-info" data-bs-toggle="modal" data-bs-target="#variantMediaModal{{ $variant->id }}" title="صور المتغير">
                                            <i class="ri-image-line"></i>
                                            @if($variant->getMedia('variant_images')->isNotEmpty())
                                                <span class="badge bg-info text-white fs-10 p-1">{{ $variant->getMedia('variant_images')->count() }}</span>
                                            @endif
                                        </button>
                                    @endif
                                    @if(auth()->user()->hasPermission('admin.variant_prices.update') || auth()->user()->hasPermission('admin.variant_prices.view'))
                                        <button type="button" class="btn btn-sm btn-soft-success" data-bs-toggle="modal" data-bs-target="#editPriceModal{{ $variant->id }}" title="{{ auth()->user()->hasPermission('admin.variant_prices.update') ? 'تعديل السعر' : 'عرض السعر' }}">
                                            <i class="ri-money-dollar-circle-line"></i>
                                        </button>
                                    @endif
                                    @if(auth()->user()->hasPermission('admin.variants.update'))
                                        <button type="button" class="btn btn-sm btn-soft-primary" data-bs-toggle="modal" data-bs-target="#editVariantModal{{ $variant->id }}" title="تعديل المتغير">
                                            <i class="ri-pencil-line"></i>
                                        </button>
                                    @endif
                                    @if($variant->status === \App\Enums\ProductVariantStatus::Active && auth()->user()->hasPermission('admin.variants.deactivate'))
                                        <form method="POST" action="{{ route('admin.products.variants.deactivate', [$product, $variant]) }}" class="d-inline" data-confirm="هل تريد تعطيل المتغير '{{ $variant->sku }}'؟">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-soft-danger" title="تعطيل"><i class="ri-forbid-line"></i></button>
                                        </form>
                                    @elseif($variant->status === \App\Enums\ProductVariantStatus::Inactive && auth()->user()->hasPermission('admin.variants.activate'))
                                        <form method="POST" action="{{ route('admin.products.variants.activate', [$product, $variant]) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-soft-success" title="تفعيل"><i class="ri-check-line"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="text-center py-4">
                                    <div class="avatar-md mx-auto mb-3">
                                        <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                            <i class="ri-stack-line"></i>
                                        </div>
                                    </div>
                                    <h5 class="fs-15 mb-1">لا توجد متغيرات مسجلة لهذا المنتج بعد</h5>
                                    <p class="text-muted fs-12 mb-0">أضف متغيراً لتفعيل إمكانية بيع المنتج وإدارة مخزونه.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{--
  Variant modals MUST stay outside .table-responsive.
  Nested modals inside overflow:auto tables get clipped on mobile Safari/Chrome
  and appear broken (dialog not interactive / half-visible).
--}}
@foreach($variants as $variant)
    @php
        $basePriceRec = $variant->base_price_record;
    @endphp

    <div class="modal fade text-start" id="editPriceModal{{ $variant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                @php $canUpdateVariantPrice = auth()->user()->hasPermission('admin.variant_prices.update'); @endphp
                <form method="POST" action="{{ route('admin.products.variants.price.update', [$product, $variant]) }}" class="needs-validation-variant" novalidate @if(!$canUpdateVariantPrice) onsubmit="return false;" @endif>
                    @csrf @method('PUT')
                    <input type="hidden" name="_modal" value="editPriceModal{{ $variant->id }}">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $canUpdateVariantPrice ? 'تعديل السعر للمتغير' : 'عرض السعر للمتغير' }}: {{ $variant->sku }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 fs-12 mb-3">
                            يتم تحديد السعر بالعملة الأساسية للنظام فقط. العملات الأخرى تُحوّل تلقائياً عبر أسعار الصرف.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">السعر بالعملة الأساسية <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0" class="form-control @if(old('_modal') === 'editPriceModal'.$variant->id) @error('price') is-invalid @enderror @endif" name="price" value="{{ old('_modal') === 'editPriceModal'.$variant->id ? old('price', $basePriceRec?->price) : $basePriceRec?->price }}" required @disabled(!$canUpdateVariantPrice)>
                            <div class="invalid-feedback">@if(old('_modal') === 'editPriceModal'.$variant->id) @error('price') {{ $message }} @enderror @endif</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">سعر المقارنة (قبل الخصم - اختياري)</label>
                            <input type="number" step="any" min="0" class="form-control @if(old('_modal') === 'editPriceModal'.$variant->id) @error('compare_at_price') is-invalid @enderror @endif" name="compare_at_price" value="{{ old('_modal') === 'editPriceModal'.$variant->id ? old('compare_at_price', $basePriceRec?->compare_at_price) : $basePriceRec?->compare_at_price }}" @disabled(!$canUpdateVariantPrice)>
                            <small class="text-muted d-block">يجب أن يكون أعلى من السعر الفعلي إن وُجد</small>
                            <div class="invalid-feedback">@if(old('_modal') === 'editPriceModal'.$variant->id) @error('compare_at_price') {{ $message }} @enderror @endif</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ $canUpdateVariantPrice ? 'إلغاء' : 'إغلاق' }}</button>
                        @if($canUpdateVariantPrice)
                            <button type="submit" class="btn btn-primary">حفظ السعر</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade text-start" id="editVariantModal{{ $variant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.products.variants.update', [$product, $variant]) }}" class="needs-validation-variant" novalidate>
                    @csrf @method('PUT')
                    <input type="hidden" name="_modal" value="editVariantModal{{ $variant->id }}">
                    <div class="modal-header">
                        <h5 class="modal-title">تعديل المتغير: {{ $variant->sku }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">رمز التخزين (SKU)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control bg-light" value="{{ $variant->sku }}" readonly dir="ltr">
                                    <span class="input-group-text text-muted"><i class="ri-lock-line" title="غير قابل للتعديل"></i></span>
                                </div>
                                <small class="text-muted">رمز التخزين ثابت وغير قابل للتعديل بعد الإنشاء.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الباركود</label>
                                <input type="text" class="form-control @if(old('_modal') === 'editVariantModal'.$variant->id) @error('barcode') is-invalid @enderror @endif" name="barcode" value="{{ old('_modal') === 'editVariantModal'.$variant->id ? old('barcode', $variant->barcode) : $variant->barcode }}" dir="ltr">
                                <div class="invalid-feedback">@if(old('_modal') === 'editVariantModal'.$variant->id) @error('barcode') {{ $message }} @enderror @endif</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الصنف لدى المورد</label>
                                <input type="text" maxlength="100" class="form-control @if(old('_modal') === 'editVariantModal'.$variant->id) @error('supplier_item_number') is-invalid @enderror @endif" name="supplier_item_number" value="{{ old('_modal') === 'editVariantModal'.$variant->id ? old('supplier_item_number', $variant->supplier_item_number) : $variant->supplier_item_number }}" dir="ltr" placeholder="مرجع المورد الداخلي">
                                <small class="text-muted">للاستخدام الداخلي في الشراء والتوفير — لا يظهر للعميل.</small>
                                <div class="invalid-feedback">@if(old('_modal') === 'editVariantModal'.$variant->id) @error('supplier_item_number') {{ $message }} @enderror @endif</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">السعر بالعملة الأساسية <span class="text-danger">*</span></label>
                                <input type="number" step="any" min="0" class="form-control @if(old('_modal') === 'editVariantModal'.$variant->id) @error('price') is-invalid @enderror @endif" name="price" value="{{ old('_modal') === 'editVariantModal'.$variant->id ? old('price', $basePriceRec?->price) : $basePriceRec?->price }}" required>
                                <div class="invalid-feedback">@if(old('_modal') === 'editVariantModal'.$variant->id) @error('price') {{ $message }} @enderror @endif</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">سعر المقارنة (اختياري)</label>
                                <input type="number" step="any" min="0" class="form-control @if(old('_modal') === 'editVariantModal'.$variant->id) @error('compare_at_price') is-invalid @enderror @endif" name="compare_at_price" value="{{ old('_modal') === 'editVariantModal'.$variant->id ? old('compare_at_price', $basePriceRec?->compare_at_price) : $basePriceRec?->compare_at_price }}">
                                <small class="text-muted d-block">يجب أن يكون أعلى من السعر الفعلي إن وُجد</small>
                                <div class="invalid-feedback">@if(old('_modal') === 'editVariantModal'.$variant->id) @error('compare_at_price') {{ $message }} @enderror @endif</div>
                            </div>

                            <div class="col-12 border-top pt-3">
                                <label class="form-label fw-bold">طريقة التوفير <span class="text-danger">*</span></label>
                                <div class="d-flex flex-column flex-md-row gap-3 gap-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input fulfillment-mode-radio" type="radio" name="fulfillment_mode" id="fulfillment_mode_stocked_{{ $variant->id }}" value="stocked" {{ (old('_modal') === 'editVariantModal'.$variant->id ? old('fulfillment_mode') : $variant->fulfillment_mode->value) === 'stocked' ? 'checked' : '' }} data-target="supplier_wrapper_{{ $variant->id }}">
                                        <label class="form-check-label" for="fulfillment_mode_stocked_{{ $variant->id }}">
                                            <i class="ri-archive-line me-1 text-primary"></i> <strong>مخزني</strong>
                                            <small class="text-muted d-block fs-11">يعتمد على رصيد المخزن المتاح</small>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input fulfillment-mode-radio" type="radio" name="fulfillment_mode" id="fulfillment_mode_ondemand_{{ $variant->id }}" value="on_demand" {{ (old('_modal') === 'editVariantModal'.$variant->id ? old('fulfillment_mode') : $variant->fulfillment_mode->value) === 'on_demand' ? 'checked' : '' }} data-target="supplier_wrapper_{{ $variant->id }}">
                                        <label class="form-check-label" for="fulfillment_mode_ondemand_{{ $variant->id }}">
                                            <i class="ri-truck-line me-1 text-info"></i> <strong>توفير عند الطلب</strong>
                                            <small class="text-muted d-block fs-11">يتم جلبه عبر مورد معتمد عند الطلب</small>
                                        </label>
                                    </div>
                                </div>
                                @if(old('_modal') === 'editVariantModal'.$variant->id)
                                    @error('fulfillment_mode')
                                        <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-12 supplier-selection-wrapper" id="supplier_wrapper_{{ $variant->id }}" style="{{ (old('_modal') === 'editVariantModal'.$variant->id ? old('fulfillment_mode') : $variant->fulfillment_mode->value) === 'on_demand' ? '' : 'display: none;' }}">
                                <label class="form-label">المورد المعتمد للتوفير <span class="text-danger">*</span></label>
                                <select class="form-select @if(old('_modal') === 'editVariantModal'.$variant->id) @error('supplier_id') is-invalid @enderror @endif" name="supplier_id" id="supplier_id_{{ $variant->id }}">
                                    <option value="">-- اختر المورد المعتمد --</option>
                                    @foreach($suppliers ?? [] as $supp)
                                        <option value="{{ $supp->id }}" {{ (string)(old('_modal') === 'editVariantModal'.$variant->id ? old('supplier_id', $variant->supplier_id ?? $product->supplier_id) : ($variant->supplier_id ?? $product->supplier_id)) === (string)$supp->id ? 'selected' : '' }}>
                                            {{ $supp->name }} ({{ $supp->code }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block">إلزامي عند اختيار التوفير عند الطلب. يجب أن يكون المورد نشطاً.</small>
                                <div class="invalid-feedback">@if(old('_modal') === 'editVariantModal'.$variant->id) @error('supplier_id') {{ $message }} @enderror @endif</div>
                            </div>

                            @if($attributes->isNotEmpty())
                                <div class="col-12 border-top pt-3">
                                    <h6 class="fw-bold mb-2">الخصائص المحددة للمتغير</h6>
                                    <div class="row g-2">
                                        @php
                                            $currentValIds = $variant->attributeValues->pluck('id')->all();
                                        @endphp
                                        @foreach($attributes as $attr)
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fs-12 mb-1">{{ $attr->name_ar }}</label>
                                                <select class="form-select form-select-sm" name="attribute_value_ids[]">
                                                    <option value="">بدون تحديد</option>
                                                    @foreach($attr->values as $val)
                                                        <option value="{{ $val->id }}" {{ in_array($val->id, $currentValIds) ? 'selected' : '' }}>
                                                            {{ $val->value_ar }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="col-md-6">
                                <label class="form-label">الحالة</label>
                                <select class="form-control" data-choices data-choices-search-false name="status">
                                    <option value="active" {{ $variant->status === \App\Enums\ProductVariantStatus::Active ? 'selected' : '' }}>نشط</option>
                                    <option value="inactive" {{ $variant->status === \App\Enums\ProductVariantStatus::Inactive ? 'selected' : '' }}>معطل</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_default_{{ $variant->id }}" name="is_default" value="1" {{ $variant->is_default ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="is_default_{{ $variant->id }}">متغير افتراضي للمنتج</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('admin.products.partials.variant_media_modal', ['variant' => $variant])
@endforeach

<!-- Add Variant Modal -->
@if(auth()->user()->hasPermission('admin.variants.create'))
    <div class="modal fade text-start" id="addVariantModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.products.variants.store', $product) }}" class="needs-validation-variant" novalidate>
                    @csrf
                    <input type="hidden" name="_modal" value="addVariantModal">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-add-line me-1 text-primary"></i> إضافة متغير جديد للمنتج</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">رمز التخزين (SKU)</label>
                                <div class="form-control bg-light text-muted d-flex align-items-center justify-content-between">
                                    <span class="fst-italic"><i class="ri-magic-line me-1 text-primary"></i> يتم توليده تلقائياً بواسطة النظام</span>
                                    <span class="badge bg-primary-subtle text-primary">تلقائي</span>
                                </div>
                                <small class="text-muted">يقوم النظام بتعيين رمز SKU فريد وثابت تلقائياً عند الحفظ.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الباركود (اختياري)</label>
                                <input type="text" class="form-control @if(old('_modal') === 'addVariantModal') @error('barcode') is-invalid @enderror @endif" name="barcode" value="{{ old('_modal') === 'addVariantModal' ? old('barcode') : '' }}" dir="ltr">
                                <div class="invalid-feedback">@if(old('_modal') === 'addVariantModal') @error('barcode') {{ $message }} @enderror @endif</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الصنف لدى المورد</label>
                                <input type="text" maxlength="100" class="form-control @if(old('_modal') === 'addVariantModal') @error('supplier_item_number') is-invalid @enderror @endif" name="supplier_item_number" value="{{ old('_modal') === 'addVariantModal' ? old('supplier_item_number') : '' }}" dir="ltr">
                                <small class="text-muted">مرجع داخلي للمورد — اختياري.</small>
                                <div class="invalid-feedback">@if(old('_modal') === 'addVariantModal') @error('supplier_item_number') {{ $message }} @enderror @endif</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">السعر بالعملة الأساسية <span class="text-danger">*</span></label>
                                <input type="number" step="any" min="0" class="form-control @if(old('_modal') === 'addVariantModal') @error('price') is-invalid @enderror @endif" name="price" value="{{ old('_modal') === 'addVariantModal' ? old('price', '0') : '0' }}" required>
                                <small class="text-muted d-block">السعر الأساسي المعتمد في الكتالوج</small>
                                <div class="invalid-feedback">@if(old('_modal') === 'addVariantModal') @error('price') {{ $message }} @enderror @endif</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">سعر المقارنة (قبل الخصم - اختياري)</label>
                                <input type="number" step="any" min="0" class="form-control @if(old('_modal') === 'addVariantModal') @error('compare_at_price') is-invalid @enderror @endif" name="compare_at_price" value="{{ old('_modal') === 'addVariantModal' ? old('compare_at_price') : '' }}">
                                <small class="text-muted d-block">يجب أن يكون أعلى من السعر الفعلي إن وُجد</small>
                                <div class="invalid-feedback">@if(old('_modal') === 'addVariantModal') @error('compare_at_price') {{ $message }} @enderror @endif</div>
                            </div>

                            <div class="col-12 border-top pt-3">
                                <label class="form-label fw-bold">طريقة التوفير <span class="text-danger">*</span></label>
                                <div class="d-flex flex-column flex-md-row gap-3 gap-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input fulfillment-mode-radio" type="radio" name="fulfillment_mode" id="fulfillment_mode_stocked_new" value="stocked" {{ old('fulfillment_mode', 'stocked') === 'stocked' ? 'checked' : '' }} data-target="supplier_wrapper_new">
                                        <label class="form-check-label" for="fulfillment_mode_stocked_new">
                                            <i class="ri-archive-line me-1 text-primary"></i> <strong>مخزني</strong>
                                            <small class="text-muted d-block fs-11">يعتمد على رصيد المخزن المتاح</small>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input fulfillment-mode-radio" type="radio" name="fulfillment_mode" id="fulfillment_mode_ondemand_new" value="on_demand" {{ old('fulfillment_mode') === 'on_demand' ? 'checked' : '' }} data-target="supplier_wrapper_new">
                                        <label class="form-check-label" for="fulfillment_mode_ondemand_new">
                                            <i class="ri-truck-line me-1 text-info"></i> <strong>توفير عند الطلب</strong>
                                            <small class="text-muted d-block fs-11">يتم جلبه عبر مورد معتمد عند الطلب</small>
                                        </label>
                                    </div>
                                </div>
                                @if(old('_modal') === 'addVariantModal')
                                    @error('fulfillment_mode')
                                        <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-12 supplier-selection-wrapper" id="supplier_wrapper_new" style="{{ old('fulfillment_mode') === 'on_demand' ? '' : 'display: none;' }}">
                                <label class="form-label">المورد المعتمد للتوفير <span class="text-danger">*</span></label>
                                <select class="form-select @if(old('_modal') === 'addVariantModal') @error('supplier_id') is-invalid @enderror @endif" name="supplier_id" id="supplier_id_new">
                                    <option value="">-- اختر المورد المعتمد --</option>
                                    @foreach($suppliers ?? [] as $supp)
                                        <option value="{{ $supp->id }}" {{ (string)old('supplier_id', $product->supplier_id) === (string)$supp->id ? 'selected' : '' }}>
                                            {{ $supp->name }} ({{ $supp->code }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block">إلزامي عند اختيار التوفير عند الطلب. يجب أن يكون المورد نشطاً.</small>
                                <div class="invalid-feedback">@if(old('_modal') === 'addVariantModal') @error('supplier_id') {{ $message }} @enderror @endif</div>
                            </div>

                            @if($attributes->isNotEmpty())
                                <div class="col-12 border-top pt-3">
                                    <h6 class="fw-bold mb-2">تحديد قيم الخصائص للمتغير</h6>
                                    <p class="text-muted fs-12 mb-2">اختر القيمة المناسبة لكل خاصية تميز هذا المتغير (مثل اللون أو الحجم).</p>
                                    <div class="row g-2">
                                        @foreach($attributes as $attr)
                                            <div class="col-md-6">
                                                <label class="form-label fs-12 mb-1">{{ $attr->name_ar }}</label>
                                                <select class="form-select form-select-sm" name="attribute_value_ids[]">
                                                    <option value="">بدون تحديد</option>
                                                    @foreach($attr->values as $val)
                                                        <option value="{{ $val->id }}">{{ $val->value_ar }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="col-md-6">
                                <label class="form-label">الحالة</label>
                                <select class="form-control" data-choices data-choices-search-false name="status">
                                    <option value="active" selected>نشط</option>
                                    <option value="inactive">معطل</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_default_new" name="is_default" value="1" {{ $variants->isEmpty() ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="is_default_new">تعيين كمتغير افتراضي</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة المتغير</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if($errors->any() && old('_modal'))
    <div id="active-error-modal" data-modal-id="{{ old('_modal') }}" style="display: none;"></div>
@endif
@endsection

@push('styles')
<style>
    tr.variant-row-clickable {
        cursor: pointer;
        -webkit-tap-highlight-color: rgba(64, 81, 137, 0.12);
    }
    tr.variant-row-clickable:hover > td {
        background-color: rgba(64, 81, 137, 0.04);
    }

    /*
     * Bootstrap's modal-dialog-scrollable expects .modal-body as a direct
     * flex child of .modal-content. Our forms wrap header/body/footer, so
     * without this the body never gets a max-height and lower fields are clipped.
     */
    .modal-dialog-scrollable {
        height: calc(100% - 1rem);
        max-height: calc(100vh - 2rem);
        margin-top: 1rem;
        margin-bottom: 1rem;
    }
    .modal-dialog-scrollable .modal-content {
        max-height: 100%;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .modal-dialog-scrollable .modal-content > form.needs-validation-variant {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        max-height: 100%;
        overflow: hidden;
    }
    .modal-dialog-scrollable .modal-content > form.needs-validation-variant > .modal-header,
    .modal-dialog-scrollable .modal-content > form.needs-validation-variant > .modal-footer {
        flex-shrink: 0;
    }
    .modal-dialog-scrollable .modal-content > form.needs-validation-variant > .modal-body {
        overflow-y: auto !important;
        overflow-x: hidden;
        flex: 1 1 auto;
        min-height: 0;
        -webkit-overflow-scrolling: touch;
    }

    @media (max-width: 767.98px) {
        .variant-actions .btn {
            min-width: 2.25rem;
            min-height: 2.25rem;
        }
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }
        .modal-dialog-scrollable.modal-fullscreen-sm-down {
            height: 100%;
            max-height: 100%;
            margin: 0;
        }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Fulfillment mode radio toggle
    document.querySelectorAll('.fulfillment-mode-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var targetId = this.getAttribute('data-target');
            var wrapper = document.getElementById(targetId);
            if (wrapper) {
                if (this.value === 'on_demand') {
                    wrapper.style.display = '';
                } else {
                    wrapper.style.display = 'none';
                    var select = wrapper.querySelector('select[name="supplier_id"]');
                    if (select) {
                        select.value = '';
                        select.classList.remove('is-invalid');
                    }
                }
            }
        });
    });

    // Client-side validation on variant form submission
    document.querySelectorAll('.needs-validation-variant').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var onDemandRadio = form.querySelector('input[name="fulfillment_mode"][value="on_demand"]');
            var supplierSelect = form.querySelector('select[name="supplier_id"]');
            if (onDemandRadio && onDemandRadio.checked && supplierSelect && !supplierSelect.value) {
                event.preventDefault();
                event.stopPropagation();
                supplierSelect.classList.add('is-invalid');
                supplierSelect.focus();
            }
        });
    });

    // Auto open modal with validation errors
    var errorModalEl = document.getElementById('active-error-modal');
    if (errorModalEl) {
        var modalId = errorModalEl.getAttribute('data-modal-id');
        var targetModal = document.getElementById(modalId);
        if (targetModal && typeof bootstrap !== 'undefined') {
            var modalInstance = new bootstrap.Modal(targetModal);
            modalInstance.show();
        }
    }

    // Open edit-variant modal when clicking a variant row (ignore action controls)
    document.querySelectorAll('tr.variant-row-clickable').forEach(function (row) {
        var openEditModal = function () {
            var targetSelector = row.getAttribute('data-edit-modal-target');
            var targetModal = targetSelector ? document.querySelector(targetSelector) : null;
            if (!targetModal || typeof bootstrap === 'undefined') {
                return;
            }
            bootstrap.Modal.getOrCreateInstance(targetModal).show();
        };

        row.addEventListener('click', function (event) {
            // Ignore taps on action controls so mobile buttons keep working.
            if (event.target.closest('a, button, form, input, select, textarea, label, .modal, .variant-actions')) {
                return;
            }
            openEditModal();
        });

        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openEditModal();
            }
        });
    });
});
</script>
@endpush

