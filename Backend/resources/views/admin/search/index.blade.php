@extends('layouts.admin')

@section('title', 'عمليات ومحرك البحث | الوفاء استور')
@section('page-title', 'عمليات ومحرك البحث (Meilisearch & Visual Search)')

@section('content')
<div class="row">
    <!-- Status Card 1: Meilisearch Text Index -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">محرك البحث النصي (Meilisearch)</p>
                    </div>
                    <div class="flex-shrink-0">
                        @if($stats->isHealthy)
                            <span class="badge bg-success-subtle text-success fs-12"><i class="ri-checkbox-circle-line align-middle me-1"></i>متصل ونشط</span>
                        @else
                            <span class="badge bg-warning-subtle text-warning fs-12"><i class="ri-alert-line align-middle me-1"></i>غير متاح محلياً</span>
                        @endif
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-1">{{ number_format($stats->numberOfDocuments) }}</h4>
                        <span class="text-muted fs-13">مستند مفهرس ({{ $stats->indexName }})</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded fs-3">
                            <i class="ri-search-eye-line text-primary"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Card 2: Visual Embeddings Total -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">المتجهات البصرية المفهرسة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge bg-info-subtle text-info fs-12">{{ $stats->vectorDriver }}</span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-1 text-info">{{ number_format($stats->indexedEmbeddingsCount) }}</h4>
                        <span class="text-muted fs-13">من أصل {{ number_format($stats->totalEmbeddingsCount) }} متجهات مخزنة</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle rounded fs-3">
                            <i class="ri-image-line text-info"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Card 3: Pending & Failed Embeddings -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">الحالات المعلقة والفاشلة</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-1">
                            <span class="text-warning">{{ number_format($stats->pendingEmbeddingsCount) }}</span>
                            /
                            <span class="text-danger">{{ number_format($stats->failedEmbeddingsCount) }}</span>
                        </h4>
                        <span class="text-muted fs-13">معلق قيد التوليد / فاشل يحتاج إعادة محاولة</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle rounded fs-3">
                            <i class="ri-refresh-line text-warning"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Card 4: Last Index Activity -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">آخر عملية فهرسة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge bg-secondary-subtle text-secondary fs-12">{{ $stats->textDriver }}</span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h5 class="fs-15 fw-semibold mb-1">{{ $stats->lastIndexedAt ? \Carbon\Carbon::parse($stats->lastIndexedAt)->diffForHumans() : 'لم تُفهرس بعد' }}</h5>
                        <span class="text-muted fs-13">{{ $stats->lastIndexedAt ? \Carbon\Carbon::parse($stats->lastIndexedAt)->format('Y-m-d H:i') : '-' }}</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded fs-3">
                            <i class="ri-time-line text-success"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Management Actions Toolbar -->
@if(auth()->user()->hasPermission('admin.search.manage'))
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="card-title mb-1">إجراءات المزامنة وإعادة الفهرسة</h5>
                <p class="text-muted mb-0 fs-13">يمكنك تشغيل المزامنة اليدوية أو مطابقة الفهارس النصية والبصرية مع قاعدة بيانات MySQL.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form action="{{ route('admin.search.reindex_text') }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد بالتأكيد إعادة فهرسة جميع المنتجات النصية في Meilisearch؟');">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-restart-line align-middle me-1"></i> إعادة فهرسة الكتالوج النصي
                    </button>
                </form>

                <form action="{{ route('admin.search.reconcile_text') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="ri-check-double-line align-middle me-1"></i> مطابقة الفهرس النصي
                    </button>
                </form>

                <form action="{{ route('admin.search.reindex_visual') }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد بالتأكيد إعادة توليد متجهات جميع صور المنتجات؟');">
                    @csrf
                    <button type="submit" class="btn btn-info">
                        <i class="ri-image-edit-line align-middle me-1"></i> توليد المتجهات البصرية
                    </button>
                </form>

                <form action="{{ route('admin.search.reconcile_visual') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-info">
                        <i class="ri-magic-line align-middle me-1"></i> مطابقة وتصحيح المتجهات
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Search Testing Workbench -->
<div class="row">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-search-line align-middle me-1 text-primary"></i> اختبار البحث النصي المباشر</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.search.index') }}" method="GET" class="row g-3 align-items-end filter-row mb-4">
                    <div class="row g-2">
                        <div class="col-8">
                            <input type="text" name="q" class="form-control" placeholder="ابحث باسم المنتج (عربي أو إنجليزي)، أو الرمز (SKU)..." value="{{ $currentQuery ?? '' }}" required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ri-search-2-line align-middle me-1"></i> استعلام
                            </button>
                        </div>
                    </div>
                </form>

                @if($testType === 'text' && $testResults)
                    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3">
                        <span>النتائج المسترجعة: <strong>{{ $testResults->items->count() }}</strong> (إجمالي: {{ $testResults->total }})</span>
                        <span class="badge bg-secondary-subtle text-secondary">زمن الاستجابة: {{ $testLatencyMs }} ms</span>
                    </div>

                    @if($testResults->items->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="ri-inbox-line fs-36 d-block mb-2"></i>
                            لا توجد نتائج مطابقة للاستعلام "{{ $currentQuery }}"
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>الصورة</th>
                                        <th>اسم المنتج</th>
                                        <th>العلامة / التصنيف</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($testResults->items as $product)
                                    <tr>
                                        <td style="width: 50px;">
                                            @if($product->getPrimaryImageUrl('thumb') || $product->getPrimaryImageUrl())
                                                <img src="{{ $product->getPrimaryImageUrl('thumb') ?? $product->getPrimaryImageUrl() }}" class="rounded avatar-xs" alt="{{ $product->name_ar }}">
                                            @else
                                                <span class="avatar-xs d-inline-block bg-light rounded text-center lh-base text-muted"><i class="ri-image-line"></i></span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.products.show', $product->id) }}" class="fw-medium text-body">{{ $product->name_ar }}</a>
                                            <br><small class="text-muted">{{ $product->name_en }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">{{ $product->brand?->name_ar ?? '-' }}</span>
                                            <small class="text-muted d-block">{{ $product->category?->name_ar ?? '-' }}</small>
                                        </td>
                                        <td>
                                            @if($product->status->value === 'active')
                                                <span class="badge bg-success-subtle text-success">نشط</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">{{ $product->status->value }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Visual Search Testing Workbench -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-image-search-line align-middle me-1 text-info"></i> اختبار البحث البصري بالصورة (Visual Search)</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.search.test_visual') }}" method="POST" enctype="multipart/form-data" class="mb-4">
                    @csrf
                    <div class="row g-2">
                        <div class="col-8">
                            <input type="file" name="image" class="form-control" accept="image/*" required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-info w-100">
                                <i class="ri-scan-2-line align-middle me-1"></i> فحص وتشابه
                            </button>
                        </div>
                    </div>
                </form>

                @if($testType === 'visual' && $testResults)
                    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3">
                        <span>المرشحات المطابقة: <strong>{{ $testResults->items->count() }}</strong> (من أصل {{ $testResults->totalCandidates }} متجهات)</span>
                        <span class="badge bg-info-subtle text-info">الزمن: {{ $testLatencyMs }} ms ({{ $testResults->modelVersion }})</span>
                    </div>

                    @if($testResults->items->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="ri-image-line fs-36 d-block mb-2"></i>
                            لم يتم العثور على أي منتج متطابق بنسبة ثقة كافية (>= 50%)
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>المنتج</th>
                                        <th>نسبة التشابه البصري</th>
                                        <th>الحالة في MySQL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($testResults->items as $idx => $product)
                                    @php($candidate = $testResults->candidates[$idx] ?? null)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                @if($product->getPrimaryImageUrl('thumb') || $product->getPrimaryImageUrl())
                                                    <img src="{{ $product->getPrimaryImageUrl('thumb') ?? $product->getPrimaryImageUrl() }}" class="rounded avatar-xs" alt="{{ $product->name_ar }}">
                                                @else
                                                    <span class="avatar-xs d-inline-block bg-light rounded text-center lh-base text-muted"><i class="ri-image-line"></i></span>
                                                @endif
                                                <div>
                                                    <a href="{{ route('admin.products.show', $product->id) }}" class="fw-medium text-body">{{ $product->name_ar }}</a>
                                                    <small class="text-muted d-block">{{ $product->category?->name_ar ?? '' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($candidate)
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress progress-sm flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ round($candidate->similarity * 100) }}%"></div>
                                                    </div>
                                                    <span class="badge bg-success-subtle text-success fs-11">{{ round($candidate->similarity * 100, 1) }}%</span>
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success">نشط (متوفر)</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Recent Embeddings Lifecycle Table -->
<div class="card mt-4">
    <div class="card-header border-bottom d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">سجل التضمينات البصرية ومحاذاة الصور الأخيرة</h5>
        <span class="badge bg-light text-dark fs-12">{{ $recentEmbeddings->count() }} عملية حديثة</span>
    </div>
    <div class="card-body">
        @if($recentEmbeddings->isEmpty())
            <div class="text-center py-4 text-muted">
                <i class="ri-file-list-3-line fs-36 d-block mb-2"></i>
                لا توجد متجهات صور مسجلة حتى الآن.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>معرف المتجه</th>
                            <th>المنتج المرتبط</th>
                            <th>المزود / النموذج</th>
                            <th>الأبعاد</th>
                            <th>الحالة</th>
                            <th>تاريخ الفهرسة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentEmbeddings as $emb)
                        <tr>
                            <td><code>{{ $emb->vector_id }}</code></td>
                            <td>
                                @if($emb->product)
                                    <a href="{{ route('admin.products.show', $emb->product_id) }}" class="fw-medium text-body">{{ $emb->product->name_ar }}</a>
                                @else
                                    <span class="text-muted">منتج #{{ $emb->product_id }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">{{ $emb->provider }}</span>
                                <small class="text-muted">({{ $emb->model_version }})</small>
                            </td>
                            <td>{{ $emb->vector_dimension }} dim</td>
                            <td>
                                @if($emb->status === 'indexed')
                                    <span class="badge bg-success-subtle text-success">مفهرس</span>
                                @elseif($emb->status === 'processing')
                                    <span class="badge bg-warning-subtle text-warning">قيد المعالجة</span>
                                @elseif($emb->status === 'failed')
                                    <span class="badge bg-danger-subtle text-danger" title="{{ $emb->last_error }}">فشل</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">{{ $emb->status }}</span>
                                @endif
                            </td>
                            <td>{{ $emb->indexed_at ? $emb->indexed_at->diffForHumans() : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
