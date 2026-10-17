@extends('layouts.admin')
@section('title', 'المنتجات | لمّة')
@section('page-title', 'المنتجات')
@section('content')
<div class="page-heading"><div><h2 class="fs-18 mb-1">كتالوج المنتجات</h2><p>إدارة هوية المنتج وارتباطه بالعلامة التجارية والتصنيف.</p></div>@if(auth()->user()->hasPermission('admin.products.create'))<div class="d-flex gap-2"><a class="btn btn-soft-primary" href="{{ route('admin.products.import.create') }}"><i class="ri-file-excel-2-line me-1"></i> استيراد Excel</a><a class="btn btn-primary" href="{{ route('admin.products.create') }}"><i class="ri-add-line me-1"></i> منتج جديد</a></div>@endif</div>
<div class="card"><div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end filter-row">
    <div class="col-lg-4"><label for="search" class="form-label">البحث</label><input class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="الاسم أو المعرّف النصي"></div>
    <div class="col-sm-6 col-lg-2"><label for="brand_id" class="form-label">العلامة التجارية</label><select class="form-control" data-choices id="brand_id" name="brand_id"><option value="">الكل</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string) request('brand_id') === (string) $brand->id)>{{ $brand->name_ar }}</option>@endforeach</select></div>
    <div class="col-sm-6 col-lg-2"><label for="category_id" class="form-label">التصنيف</label><select class="form-control" data-choices id="category_id" name="category_id"><option value="">الكل</option>@foreach($categories as $id => $label)<option value="{{ $id }}" @selected((string) request('category_id') === (string) $id)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-sm-4 col-lg-2"><label for="status" class="form-label">الحالة</label><select class="form-control" data-choices data-choices-search-false id="status" name="status"><option value="">الكل</option><option value="draft" @selected(request('status') === 'draft')>مسودة</option><option value="active" @selected(request('status') === 'active')>نشط</option><option value="inactive" @selected(request('status') === 'inactive')>معطل</option></select></div>
    <div class="col-sm-4 col-lg-2"><label for="best_seller" class="form-label">الأكثر مبيعاً</label><select class="form-control" data-choices data-choices-search-false id="best_seller" name="best_seller"><option value="all">الكل</option><option value="yes" @selected(request('best_seller') === 'yes' || request('best_seller') === '1')>نعم</option><option value="no" @selected(request('best_seller') === 'no' || request('best_seller') === '0')>لا</option></select></div>
    <div class="col-sm-4 col-lg-2"><label for="new" class="form-label">جديد</label><select class="form-control" data-choices data-choices-search-false id="new" name="new"><option value="all">الكل</option><option value="yes" @selected(request('new') === 'yes' || request('new') === '1')>نعم</option><option value="no" @selected(request('new') === 'no' || request('new') === '0')>لا</option></select></div>
    <div class="col-sm-4 col-lg-2"><label for="exclusive" class="form-label">حصري</label><select class="form-control" data-choices data-choices-search-false id="exclusive" name="exclusive"><option value="all">الكل</option><option value="yes" @selected(request('exclusive') === 'yes' || request('exclusive') === '1')>نعم</option><option value="no" @selected(request('exclusive') === 'no' || request('exclusive') === '0')>لا</option></select></div>
    <div class="col-sm-4 col-lg-2"><label for="sort" class="form-label">الترتيب</label><select class="form-control" data-choices data-choices-search-false id="sort" name="sort"><option value="created_at" @selected(request('sort', 'created_at') === 'created_at')>الأحدث</option><option value="name_ar" @selected(request('sort') === 'name_ar')>الاسم العربي</option><option value="name_en" @selected(request('sort') === 'name_en')>الاسم الإنجليزي</option><option value="status" @selected(request('sort') === 'status')>الحالة</option></select></div>
    <div class="col-sm-6 col-lg-2"><label for="direction" class="form-label">الاتجاه</label><select class="form-control" data-choices data-choices-search-false id="direction" name="direction"><option value="desc" @selected(request('direction', 'desc') === 'desc')>تنازلي</option><option value="asc" @selected(request('direction') === 'asc')>تصاعدي</option></select></div>
    <div class="col-sm-6 col-lg-2"><button class="btn btn-soft-primary w-100" type="submit"><i class="ri-search-line me-1"></i> تطبيق</button></div>
</form></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th style="width: 70px;">الصورة</th><th>اسم المنتج</th><th>العلامة التجارية</th><th>التصنيف</th><th>الحالة</th><th>تاريخ الإنشاء</th><th class="text-end">الإجراءات</th></tr></thead><tbody>
@forelse($products as $product)<tr>
<td style="width: 70px;">
    @php($imgUrl = $product->getPrimaryImageUrl('thumb') ?: $product->getPrimaryImageUrl())
    @if($imgUrl)
        <a href="{{ route('admin.products.show', $product) }}">
            <img src="{{ $imgUrl }}" alt="{{ $product->name_ar }}" class="avatar-sm rounded object-fit-cover border shadow-sm" style="width: 48px; height: 48px;">
        </a>
    @else
        <div class="avatar-sm rounded bg-light d-flex align-items-center justify-content-center text-muted border" style="width: 48px; height: 48px;">
            <i class="ri-image-line fs-18"></i>
        </div>
    @endif
</td>
<td>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <strong>{{ $product->name_ar }}</strong>
        @if($product->is_best_seller)
            <span class="badge bg-info-subtle text-info border border-info-subtle fs-11">الأكثر مبيعاً</span>
        @endif
        @if($product->is_new)
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">جديد</span>
        @endif
        @if($product->is_exclusive)
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-11">حصري</span>
        @endif
    </div>
    @if($product->name_en && trim($product->name_en) !== trim($product->name_ar))
        <div class="text-muted fs-12" dir="ltr">{{ $product->name_en }}</div>
    @endif
</td><td>{{ $product->brand?->name_ar ?: 'بدون علامة' }}</td><td>{{ $product->category->name_ar }}</td><td>@include('admin.products.partials.status', ['status' => $product->status])</td><td>{{ $product->created_at?->format('Y-m-d') }}</td><td class="actions-cell text-end">
    <a class="btn btn-sm btn-soft-info" href="{{ route('admin.products.show', $product) }}" aria-label="عرض {{ $product->name_ar }}"><i class="ri-eye-line"></i></a>
    @if(auth()->user()->hasPermission('admin.products.update'))<a class="btn btn-sm btn-soft-primary" href="{{ route('admin.products.edit', $product) }}" aria-label="تعديل {{ $product->name_ar }}"><i class="ri-pencil-line"></i></a>@endif
    @if($product->status !== \App\Enums\ProductStatus::Active && auth()->user()->hasPermission('admin.products.activate'))<form method="POST" action="{{ route('admin.products.activate', $product) }}" data-confirm="سيتم نشر المنتج {{ $product->name_ar }} وإتاحته للاستخدام. هل تريد المتابعة؟">@csrf @method('PATCH')<button class="btn btn-sm btn-soft-success" type="submit" aria-label="تفعيل {{ $product->name_ar }}"><i class="ri-checkbox-circle-line"></i></button></form>@endif
    @if($product->status !== \App\Enums\ProductStatus::Inactive && auth()->user()->hasPermission('admin.products.deactivate'))<form method="POST" action="{{ route('admin.products.deactivate', $product) }}" data-confirm="سيتم تعطيل المنتج {{ $product->name_ar }} ومنع استخدامه في العمليات الجديدة. هل تريد المتابعة؟">@csrf @method('PATCH')<button class="btn btn-sm btn-soft-danger" type="submit" aria-label="تعطيل {{ $product->name_ar }}"><i class="ri-forbid-line"></i></button></form>@endif
</td></tr>
@empty<tr><td colspan="7"><div class="text-center py-5"><div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-inbox-2-line"></i></div></div><h5 class="fs-16 mb-1">لا توجد منتجات مطابقة</h5><p class="text-muted mb-0">غيّر عوامل البحث أو أضف أول منتج إلى الكتالوج.</p></div></td></tr>@endforelse
</tbody></table></div></div><div class="card-footer">{{ $products->links() }}</div></div>
@endsection
