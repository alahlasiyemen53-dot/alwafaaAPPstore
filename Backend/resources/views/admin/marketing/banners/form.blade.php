@extends('layouts.admin')
@section('title', ($banner ? 'تعديل بنر' : 'إضافة بنر جديد').' | لمّة')
@section('page-title', $banner ? 'تعديل بنر' : 'إضافة بنر جديد')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-9">
        <form method="POST" action="{{ $banner ? route('admin.marketing.banners.update', $banner) : route('admin.marketing.banners.store') }}" enctype="multipart/form-data">
            @csrf
            @if($banner)
                @method('PUT')
            @endif

            {{-- 1. Basic Info Card --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">المعلومات الأساسية والنصوص</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="title_ar" class="form-label required">العنوان الرئيسي (بالعربية)</label>
                            <input type="text" class="form-control @error('title_ar') is-invalid @enderror" id="title_ar" name="title_ar" value="{{ old('title_ar', $banner?->title_ar) }}" required>
                            @error('title_ar')
                                <span class="text-danger fs-12">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="title_en" class="form-label">العنوان الرئيسي (بالإنجليزية)</label>
                            <input type="text" class="form-control @error('title_en') is-invalid @enderror" id="title_en" name="title_en" value="{{ old('title_en', $banner?->title_en) }}" dir="ltr">
                            @error('title_en')
                                <span class="text-danger fs-12">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="subtitle_ar" class="form-label">النص الترويجي الإضافي (بالعربية)</label>
                            <input type="text" class="form-control @error('subtitle_ar') is-invalid @enderror" id="subtitle_ar" name="subtitle_ar" value="{{ old('subtitle_ar', $banner?->subtitle_ar) }}" placeholder="مثال: خصومات تصل إلى 50% على المنتجات المختارة">
                            @error('subtitle_ar')
                                <span class="text-danger fs-12">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="subtitle_en" class="form-label">النص الترويجي الإضافي (بالإنجليزية)</label>
                            <input type="text" class="form-control @error('subtitle_en') is-invalid @enderror" id="subtitle_en" name="subtitle_en" value="{{ old('subtitle_en', $banner?->subtitle_en) }}" dir="ltr">
                            @error('subtitle_en')
                                <span class="text-danger fs-12">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Banner Media Card --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">صورة البنر</h5>
                    <span class="badge bg-light text-muted border">النسبة الموصى بها: 2:1 (مثل 1200x600 بكسل)</span>
                </div>
                <div class="card-body">
                    @if($banner && $banner->getBannerImageUrl())
                        <div class="mb-3">
                            <label class="form-label d-block text-muted">الصورة الحالية:</label>
                            <img src="{{ $banner->getBannerImageUrl('medium') }}" alt="Current Banner" class="img-fluid rounded border shadow-sm" style="max-height: 200px; width: auto;">
                        </div>
                    @endif

                    <div class="mb-2">
                        <label for="image" class="form-label {{ $banner ? '' : 'required' }}">
                            {{ $banner ? 'استبدال صورة البنر' : 'رفع صورة البنر' }}
                        </label>
                        <input type="file" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/jpeg,image/png,image/webp" {{ $banner ? '' : 'required' }}>
                        <div class="form-text fs-12">الصيغ المدعومة: WEBP, PNG, JPG. الحجم الأقصى: 4 ميغابايت.</div>
                        @error('image')
                            <span class="text-danger fs-12">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- 3. Destination Action Card --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">وجهة التوجيه عند النقر (Destination Action)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted fs-13 mb-3">
                        حدد ماذا يحدث عند نقر العميل على البنر في التطبيق أو المتجر.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="action_type" class="form-label required">نوع الوجهة</label>
                            <select class="form-control @error('action_type') is-invalid @enderror" id="action_type" name="action_type" data-choices data-choices-search-false required>
                                @foreach(\App\Enums\BannerActionType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(old('action_type', $banner?->action_type->value ?? 'none') === $type->value)>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('action_type')
                                <span class="text-danger fs-12">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Dynamic Selectors based on Action Type --}}
                        <div class="col-md-6" id="product_selector_wrapper" style="display: none;">
                            <label for="product_reference" class="form-label">المنتج المرتبط</label>
                            <select class="form-control" id="product_reference" data-choices>
                                <option value="">اختر المنتج...</option>
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}" @selected(old('action_type', $banner?->action_type->value ?? '') === 'product' && (string) old('action_reference', $banner?->action_reference) === (string) $prod->id)>
                                        {{ $prod->name_ar }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6" id="category_selector_wrapper" style="display: none;">
                            <label for="category_reference" class="form-label">التصنيف المرتبط</label>
                            <select class="form-control" id="category_reference" data-choices>
                                <option value="">اختر التصنيف...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('action_type', $banner?->action_type->value ?? '') === 'category' && (string) old('action_reference', $banner?->action_reference) === (string) $cat->id)>
                                        {{ $cat->name_ar }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6" id="promotion_selector_wrapper" style="display: none;">
                            <label for="promotion_reference" class="form-label">العرض الترويجي المرتبط</label>
                            <select class="form-control" id="promotion_reference" data-choices>
                                <option value="">اختر العرض...</option>
                                @foreach($promotions as $promo)
                                    <option value="{{ $promo->id }}" @selected(old('action_type', $banner?->action_type->value ?? '') === 'promotion' && (string) old('action_reference', $banner?->action_reference) === (string) $promo->id)>
                                        {{ $promo->name }} ({{ $promo->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <input type="hidden" name="action_reference" id="action_reference_input" value="{{ old('action_reference', $banner?->action_reference) }}">

                        @error('action_reference')
                            <div class="col-12">
                                <span class="text-danger fs-12">{{ $message }}</span>
                            </div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- 4. Scheduling & Order Card --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">الجدولة والظهور</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="sort_order" class="form-label">ترتيب الظهور</label>
                            <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $banner?->sort_order ?? 0) }}" min="0">
                            <div class="form-text fs-12">الأرقام الأصغر تظهر أولاً (مثال: 0، 1، 2).</div>
                            @error('sort_order')
                                <span class="text-danger fs-12">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="starts_at" class="form-label">تاريخ ووقت بدء العرض (اختياري)</label>
                            <input type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" name="starts_at" value="{{ old('starts_at', $banner?->starts_at?->format('Y-m-d\TH:i')) }}">
                            <div class="form-text fs-12">اتركه فارغاً ليبدأ العرض فوراً.</div>
                            @error('starts_at')
                                <span class="text-danger fs-12">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="ends_at" class="form-label">تاريخ ووقت انتهاء العرض (اختياري)</label>
                            <input type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" name="ends_at" value="{{ old('ends_at', $banner?->ends_at?->format('Y-m-d\TH:i')) }}">
                            <div class="form-text fs-12">اتركه فارغاً ليستمر البنر دون نهاية محددة.</div>
                            @error('ends_at')
                                <span class="text-danger fs-12">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-12 mt-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $banner?->is_active ?? true))>
                                <label class="form-check-label fw-bold text-dark" for="is_active">تفعيل البنر فور الحفظ</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="d-flex justify-content-between align-items-center mb-5">
                <a href="{{ route('admin.marketing.banners.index') }}" class="btn btn-light">إلغاء والعودة</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="ri-save-line me-1"></i> {{ $banner ? 'حفظ التعديلات' : 'إنشاء البنر' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionTypeSelect = document.getElementById('action_type');
    const referenceInput = document.getElementById('action_reference_input');

    const productWrapper = document.getElementById('product_selector_wrapper');
    const categoryWrapper = document.getElementById('category_selector_wrapper');
    const promotionWrapper = document.getElementById('promotion_selector_wrapper');

    const productSelect = document.getElementById('product_reference');
    const categorySelect = document.getElementById('category_reference');
    const promotionSelect = document.getElementById('promotion_reference');

    function updateDestinationVisibility() {
        const val = actionTypeSelect.value;
        productWrapper.style.display = 'none';
        categoryWrapper.style.display = 'none';
        promotionWrapper.style.display = 'none';

        if (val === 'product') {
            productWrapper.style.display = '';
            referenceInput.value = productSelect.value;
        } else if (val === 'category') {
            categoryWrapper.style.display = '';
            referenceInput.value = categorySelect.value;
        } else if (val === 'promotion') {
            promotionWrapper.style.display = '';
            referenceInput.value = promotionSelect.value;
        } else {
            referenceInput.value = '';
        }
    }

    actionTypeSelect.addEventListener('change', updateDestinationVisibility);

    productSelect.addEventListener('change', function () {
        if (actionTypeSelect.value === 'product') {
            referenceInput.value = this.value;
        }
    });

    categorySelect.addEventListener('change', function () {
        if (actionTypeSelect.value === 'category') {
            referenceInput.value = this.value;
        }
    });

    promotionSelect.addEventListener('change', function () {
        if (actionTypeSelect.value === 'promotion') {
            referenceInput.value = this.value;
        }
    });

    updateDestinationVisibility();
});
</script>
@endpush
