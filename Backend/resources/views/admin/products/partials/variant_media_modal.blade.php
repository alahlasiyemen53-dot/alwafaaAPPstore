@php
    $variantMediaList = $variant->getMedia('variant_images')->sortBy('order_column')->values();
@endphp

<div class="modal fade" id="variantMediaModal{{ $variant->id }}" tabindex="-1" aria-labelledby="variantMediaModalLabel{{ $variant->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title mb-0" id="variantMediaModalLabel{{ $variant->id }}">
                        <i class="ri-image-edit-line me-1 text-primary"></i> صور المتغير: <code dir="ltr">{{ $variant->sku }}</code>
                    </h5>
                    @if($variant->attributeValues->isNotEmpty())
                        <div class="d-flex gap-1 mt-1">
                            @foreach($variant->attributeValues as $val)
                                <span class="badge bg-light text-muted border fs-11">
                                    {{ $val->attribute?->name_ar }}: {{ $val->value_ar }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>

            <div class="modal-body p-4">
                @if(auth()->user()->hasPermission('admin.variants.media.upload'))
                    <div class="card border border-dashed mb-4">
                        <div class="card-body bg-light-subtle">
                            <h6 class="fw-bold mb-2"><i class="ri-upload-cloud-2-line me-1 text-primary"></i> رفع صورة مخصصة لهذا المتغير</h6>
                            <form action="{{ route('admin.products.variants.media.upload', [$product, $variant]) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-md-6">
                                    <label class="form-label fs-12 mb-1">ملف الصورة <span class="text-danger">*</span></label>
                                    <input type="file" name="file" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" required>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="variantMediaPrimaryCheck{{ $variant->id }}">
                                        <label class="form-check-label fs-12" for="variantMediaPrimaryCheck{{ $variant->id }}">صورة رئيسية للمتغير</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">
                                        <i class="ri-upload-line me-1"></i> رفع الصورة
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <h6 class="fw-bold mb-3"><i class="ri-gallery-line me-1 text-secondary"></i> الصور المخصصة ({{ $variantMediaList->count() }})</h6>

                @if($variantMediaList->isEmpty())
                    <div class="alert alert-info d-flex align-items-center gap-3">
                        <img src="{{ $mediaPresenter->getPrimaryUrlForProduct($product, 'thumb') }}" alt="Parent image" class="rounded border" style="width: 60px; height: 60px; object-fit: cover;">
                        <div>
                            <h6 class="alert-heading mb-1 fs-13 fw-bold">يستخدم المتغير حالياً الصورة الرئيسية للمنتج</h6>
                            <p class="mb-0 fs-12 text-muted">لم يتم رفع صور مخصصة لهذا المتغير بعد، لذلك سيتم عرض الصورة الأساسية للمنتج عند معاينة هذا المتغير.</p>
                        </div>
                    </div>
                @else
                    <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-lg-4" id="variantMediaGrid{{ $variant->id }}">
                        @foreach($variantMediaList as $index => $vMedia)
                            @php
                                $isPrimary = (bool) $vMedia->getCustomProperty('is_primary', false);
                                $thumb = $vMedia->hasGeneratedConversion('thumb') ? $vMedia->getUrl('thumb') : $vMedia->getUrl();
                                $large = $vMedia->hasGeneratedConversion('large') ? $vMedia->getUrl('large') : $vMedia->getUrl();
                            @endphp
                            <div class="col" data-media-id="{{ $vMedia->id }}">
                                <div class="card h-100 border shadow-none mb-0 position-relative">
                                    <div class="text-center p-2 bg-light position-relative" style="height: 130px; display: flex; align-items: center; justify-content: center;">
                                        <a href="{{ $large }}" target="_blank">
                                            <img src="{{ $thumb }}" alt="{{ $vMedia->name }}" class="img-fluid rounded" style="max-height: 110px; object-fit: contain;">
                                        </a>
                                        @if($isPrimary)
                                            <span class="badge bg-success position-absolute top-0 end-0 m-1 fs-10">
                                                <i class="ri-star-fill me-1"></i> رئيسية
                                            </span>
                                        @endif
                                    </div>
                                    <div class="card-footer p-2 bg-white d-flex justify-content-between align-items-center">
                                        <div class="d-flex gap-1">
                                            @if(!$isPrimary && auth()->user()->hasPermission('admin.variants.media.update'))
                                                <form action="{{ route('admin.products.variants.media.primary', [$product, $variant, $vMedia->id]) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-ghost-warning p-1" title="تعيين كصورة رئيسية للمتغير">
                                                        <i class="ri-star-line fs-14"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if(auth()->user()->hasPermission('admin.variants.media.update'))
                                                @if($index > 0)
                                                    <button type="button" class="btn btn-sm btn-ghost-secondary p-1 move-variant-media-btn" data-variant-id="{{ $variant->id }}" data-direction="prev" data-media-id="{{ $vMedia->id }}" title="تحريك للخلف">
                                                        <i class="ri-arrow-right-s-line fs-14"></i>
                                                    </button>
                                                @endif
                                                @if($index < $variantMediaList->count() - 1)
                                                    <button type="button" class="btn btn-sm btn-ghost-secondary p-1 move-variant-media-btn" data-variant-id="{{ $variant->id }}" data-direction="next" data-media-id="{{ $vMedia->id }}" title="تحريك للأمام">
                                                        <i class="ri-arrow-left-s-line fs-14"></i>
                                                    </button>
                                                @endif
                                            @endif
                                        </div>

                                        @if(auth()->user()->hasPermission('admin.variants.media.delete'))
                                            <form action="{{ route('admin.products.variants.media.destroy', [$product, $variant, $vMedia->id]) }}" method="POST" data-confirm="هل تريد حذف هذه الصورة؟">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-ghost-danger p-1" title="حذف الصورة">
                                                    <i class="ri-delete-bin-line fs-14"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if(auth()->user()->hasPermission('admin.variants.media.update'))
                        <form id="variantReorderForm{{ $variant->id }}" action="{{ route('admin.products.variants.media.reorder', [$product, $variant]) }}" method="POST" class="d-none">
                            @csrf
                            <div id="variantReorderInputs{{ $variant->id }}"></div>
                        </form>
                    @endif
                @endif
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
