@extends('layouts.admin')
@section('title', 'منتجات المجموعة | لمّة')
@section('page-title', 'منتجات: '.$collection->name_ar)

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.marketing.collections.index') }}" class="btn btn-light btn-sm">رجوع للمجموعات</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">المنتجات المضافة</h5></div>
            <ul class="list-group list-group-flush">
                @forelse($assigned as $item)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $item->product?->name_ar }} <small class="text-muted">#{{ $item->product_id }}</small></span>
                        @if(auth()->user()->hasPermission('admin.product_collections.update'))
                            <form method="POST" action="{{ route('admin.marketing.collections.products.remove', [$collection, $item->product_id]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-soft-danger">إزالة</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">لا منتجات بعد.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="d-flex gap-2">
                    <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="بحث منتجات...">
                    <button class="btn btn-sm btn-outline-secondary">بحث</button>
                </form>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($candidates as $product)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $product->name_ar }}</span>
                        @if(auth()->user()->hasPermission('admin.product_collections.update'))
                            <form method="POST" action="{{ route('admin.marketing.collections.products.add', $collection) }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <button class="btn btn-sm btn-soft-primary">إضافة</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">لا نتائج.</li>
                @endforelse
            </ul>
            @if($candidates->hasPages())
                <div class="card-footer">{{ $candidates->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
