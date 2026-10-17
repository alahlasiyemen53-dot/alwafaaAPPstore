@extends('layouts.admin')
@section('title', 'قوائم رغبات العملاء (المفضلة) | لمّة')
@section('page-title', 'مفضلة العملاء')

@section('content')
<div class="row">
    <div class="col-xl-4 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">إجمالي العناصر المفضلة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle rounded fs-3">
                            <i class="ri-heart-3-line text-danger"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-danger">{{ number_format($metrics['total_items']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">العملاء ذوو المفضلة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded fs-3">
                            <i class="ri-user-heart-line text-primary"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-primary">{{ number_format($metrics['distinct_customers']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">المنتجات المميزة في المفضلة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle rounded fs-3">
                            <i class="ri-shopping-bag-3-line text-warning"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-warning">{{ number_format($metrics['distinct_products']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">
                    <i class="ri-heart-line me-1 text-danger"></i> سجل مفضلة العملاء ({{ $items->total() }})
                </h4>
                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 fs-12">
                    <i class="ri-information-line me-1"></i> استعراض وتدقيق للقراءة فقط
                </span>
            </div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.wishlists.index') }}" class="row g-2 filter-row">
                    <div class="col-md-3">
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="البحث باسم/هاتف العميل أو المنتج...">
                            <i class="ri-search-line search-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="product_status" class="form-select">
                            <option value="">كل حالات المنتجات</option>
                            <option value="active" {{ request('product_status') === 'active' ? 'selected' : '' }}>نشط (Active)</option>
                            <option value="draft" {{ request('product_status') === 'draft' ? 'selected' : '' }}>مسودة (Draft)</option>
                            <option value="inactive" {{ request('product_status') === 'inactive' ? 'selected' : '' }}>معطل (Inactive)</option>
                            <option value="archived" {{ request('product_status') === 'archived' ? 'selected' : '' }}>مؤرشف (Archived)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="from_date" value="{{ request('from_date') }}" placeholder="من تاريخ">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="to_date" value="{{ request('to_date') }}" placeholder="إلى تاريخ">
                    </div>
                    <div class="col-md-1">
                        <select name="direction" class="form-select">
                            <option value="desc" {{ request('direction', 'desc') === 'desc' ? 'selected' : '' }}>الأحدث</option>
                            <option value="asc" {{ request('direction') === 'asc' ? 'selected' : '' }}>الأقدم</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ri-filter-2-line me-1"></i> تصفية
                        </button>
                        <a href="{{ route('admin.wishlists.index') }}" class="btn btn-light" title="إعادة ضبط الفلاتر">
                            <i class="ri-refresh-line"></i>
                        </a>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" style="width: 60px;">#</th>
                                <th scope="col">العميل</th>
                                <th scope="col">المنتج</th>
                                <th scope="col">التصنيف / الماركة</th>
                                <th scope="col">حالة المنتج</th>
                                <th scope="col">تاريخ الإضافة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                <tr>
                                    <td class="text-muted fs-12">{{ $item->id }}</td>
                                    <td>
                                        @if($item->customer)
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs me-2">
                                                    <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-12 fw-bold">
                                                        {{ mb_substr($item->customer->name, 0, 1) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <h6 class="fs-13 mb-0">
                                                        @if(auth()->user()->hasPermission('admin.customers.view'))
                                                            <a href="{{ route('admin.customers.show', $item->customer) }}" class="text-dark">
                                                                {{ $item->customer->name }}
                                                            </a>
                                                        @else
                                                            {{ $item->customer->name }}
                                                        @endif
                                                    </h6>
                                                    <small class="text-muted" dir="ltr">{{ $item->customer->phone }}</small>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">عميل محذوف</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->product)
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-2">
                                                    @if($item->product->getPrimaryImageUrl('thumb'))
                                                        <img src="{{ $item->product->getPrimaryImageUrl('thumb') }}" alt="{{ $item->product->name_ar }}" class="rounded avatar-xs object-fit-cover">
                                                    @else
                                                        <div class="avatar-xs rounded bg-light text-muted d-flex align-items-center justify-content-center">
                                                            <i class="ri-image-line fs-14"></i>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="fs-13 mb-0">
                                                        @if(auth()->user()->hasPermission('admin.products.view'))
                                                            <a href="{{ route('admin.products.show', $item->product) }}" class="text-dark">
                                                                {{ $item->product->name_ar }}
                                                            </a>
                                                        @else
                                                            {{ $item->product->name_ar }}
                                                        @endif
                                                    </h6>
                                                    @if($item->product->name_en)
                                                        <small class="text-muted">{{ $item->product->name_en }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">منتج محذوف</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>
                                            <span class="fs-13 d-block text-muted">
                                                <i class="ri-folder-line me-1"></i> {{ $item->product?->category?->name_ar ?? 'بدون تصنيف' }}
                                            </span>
                                            @if($item->product?->brand)
                                                <small class="text-muted">
                                                    <i class="ri-price-tag-3-line me-1"></i> {{ $item->product->brand->name_ar }}
                                                </small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($item->product)
                                            @php
                                                $statusValue = is_object($item->product->status) ? $item->product->status->value : $item->product->status;
                                            @endphp
                                            @if($statusValue === 'active')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">نشط</span>
                                            @elseif($statusValue === 'draft')
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">مسودة</span>
                                            @elseif($statusValue === 'inactive')
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">معطل</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">{{ $statusValue }}</span>
                                            @endif
                                        @else
                                            <span class="badge bg-light text-muted">غير متوفر</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fs-13">{{ $item->created_at->format('Y-m-d H:i') }}</div>
                                        <small class="text-muted">{{ $item->created_at->diffForHumans() }}</small>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-3">
                                            <span class="avatar-title bg-light text-muted rounded-circle fs-2">
                                                <i class="ri-heart-dislike-line"></i>
                                            </span>
                                        </div>
                                        @if(request()->hasAny(['search', 'product_status', 'from_date', 'to_date']))
                                            <h5 class="text-muted">لا توجد نتائج مطابقة لخيارات الفلترة</h5>
                                            <p class="text-muted mb-3">جرب تعديل شروط البحث أو التواريخ المدخلة.</p>
                                            <a href="{{ route('admin.wishlists.index') }}" class="btn btn-light btn-sm">
                                                <i class="ri-refresh-line me-1"></i> إعادة ضبط الفلاتر
                                            </a>
                                        @else
                                            <h5 class="text-muted">قائمة المفضلة فارغة حالياً</h5>
                                            <p class="text-muted mb-0">لم يقم أي عميل بحفظ منتجات في قائمة رغباته حتى الآن.</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($items->hasPages())
                    <div class="d-flex justify-content-center border-top pt-3 mt-3">
                        {{ $items->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
