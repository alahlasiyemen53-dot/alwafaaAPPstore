<div class="card mb-4" id="productMediaCard">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="card-title mb-1"><i class="ri-image-2-line me-1 text-primary"></i> معرض صور المنتج ({{ count($productMedia) }})</h3>
            <p class="text-muted fs-12 mb-0">الصور الترويجية للمنتج. يتم تحويل كافة الصور تلقائياً إلى صيغة WebP بـ 4 مقاسات محسّنة.</p>
        </div>
        @if(auth()->user()->hasPermission('admin.products.media.upload') || auth()->user()->hasPermission('admin.products.media.view'))
            @if(auth()->user()->hasPermission('admin.products.media.upload'))
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#uploadProductMediaCollapse">
                <i class="ri-upload-cloud-line me-1"></i> رفع صورة جديدة
            </button>
            @endif
        @endif
    </div>

    @if(auth()->user()->hasPermission('admin.products.media.upload'))
        <div class="collapse border-bottom" id="uploadProductMediaCollapse">
            <div class="card-body bg-light-subtle">
                <form action="{{ route('admin.products.media.upload', $product) }}" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">اختر ملف الصورة <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                        <small class="text-muted">الامتدادات المقبولة: JPG, PNG, WEBP (الحد الأقصى: 5 ميغابايت)</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">عنوان / وصف الصورة (اختياري)</label>
                        <input type="text" name="name" class="form-control" placeholder="مثال: الواجهة الأمامية للمنتج">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="productMediaPrimaryCheck">
                            <label class="form-check-label fw-semibold" for="productMediaPrimaryCheck">تعيين كصورة رئيسية</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-upload-2-line me-1"></i> بدء الرفع والمعالجة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card-body">
        @if(empty($productMedia))
            <div class="text-center py-4">
                <img src="{{ $mediaPresenter->getDefaultPlaceholderUrl() }}" alt="No image" class="img-thumbnail mb-2" style="max-height: 120px;">
                <p class="text-muted mb-0">لا توجد صور مرفوعة لهذا المنتج حتى الآن.</p>
            </div>
        @else
            <div class="row g-3 row-cols-2 row-cols-md-4 row-cols-lg-6" id="productMediaGrid">
                @foreach($productMedia as $index => $media)
                    <div class="col" data-media-id="{{ $media->id }}">
                        <div class="card h-100 border shadow-none mb-0 position-relative overflow-hidden group-hover-action">
                            <div class="position-relative bg-light text-center p-2" style="height: 160px; display: flex; align-items: center; justify-content: center;">
                                <a href="{{ $media->largeUrl }}" target="_blank" title="عرض الصورة بالحجم الكامل">
                                    <img src="{{ $media->smallUrl }}" alt="{{ $media->name }}" class="img-fluid rounded" style="max-height: 140px; object-fit: contain;">
                                </a>

                                @if($media->isPrimary)
                                    <span class="badge bg-success position-absolute top-0 end-0 m-2 shadow-sm">
                                        <i class="ri-star-fill me-1"></i> رئيسية
                                    </span>
                                @endif
                                <span class="badge bg-dark-subtle text-dark position-absolute bottom-0 start-0 m-2 fs-10">
                                    #{{ $index + 1 }}
                                </span>
                            </div>

                            <div class="card-footer p-2 bg-white d-flex justify-content-between align-items-center">
                                <div class="d-flex gap-1">
                                    @if(!$media->isPrimary && auth()->user()->hasPermission('admin.products.media.update'))
                                        <form action="{{ route('admin.products.media.primary', [$product, $media->id]) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-ghost-warning p-1" title="تعيين كصورة رئيسية">
                                                <i class="ri-star-line fs-14"></i>
                                            </button>
                                        </form>
                                    @endif

                                    @if(auth()->user()->hasPermission('admin.products.media.update'))
                                        {{-- Move Left / Previous in RTL --}}
                                        @if($index > 0)
                                            <button type="button" class="btn btn-sm btn-ghost-secondary p-1 move-media-btn" data-direction="prev" data-media-id="{{ $media->id }}" title="تحريك للخلف">
                                                <i class="ri-arrow-right-s-line fs-14"></i>
                                            </button>
                                        @endif
                                        {{-- Move Right / Next in RTL --}}
                                        @if($index < count($productMedia) - 1)
                                            <button type="button" class="btn btn-sm btn-ghost-secondary p-1 move-media-btn" data-direction="next" data-media-id="{{ $media->id }}" title="تحريك للأمام">
                                                <i class="ri-arrow-left-s-line fs-14"></i>
                                            </button>
                                        @endif
                                    @endif
                                </div>

                                @if(auth()->user()->hasPermission('admin.products.media.delete'))
                                    <form action="{{ route('admin.products.media.destroy', [$product, $media->id]) }}" method="POST" data-confirm="هل أنت متأكد من حذف هذه الصورة؟">
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

            @if(auth()->user()->hasPermission('admin.products.media.update'))
                <form id="productReorderForm" action="{{ route('admin.products.media.reorder', $product) }}" method="POST" class="d-none">
                    @csrf
                    <div id="productReorderInputs"></div>
                </form>
            @endif
        @endif
    </div>
</div>
