@extends('layouts.admin')
@section('title', 'مجموعات المنتجات | لمّة')
@section('page-title', 'مجموعات المنتجات')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="بحث...">
        <button class="btn btn-outline-secondary">بحث</button>
    </form>
    @if(auth()->user()->hasPermission('admin.product_collections.create'))
        <a href="{{ route('admin.marketing.collections.create') }}" class="btn btn-primary">إضافة مجموعة</a>
    @endif
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>Slug</th>
                    <th>المنتجات</th>
                    <th>الحالة</th>
                    <th class="text-end">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($collections as $collection)
                    <tr>
                        <td>{{ $collection->name_ar }}</td>
                        <td><code>{{ $collection->slug }}</code></td>
                        <td>{{ $collection->items_count }}</td>
                        <td>{{ $collection->is_active ? 'مفعّلة' : 'معطّلة' }}</td>
                        <td class="text-end">
                            @if(auth()->user()->hasPermission('admin.product_collections.view'))
                                <a href="{{ route('admin.marketing.collections.products', $collection) }}" class="btn btn-sm btn-soft-primary">المنتجات</a>
                            @endif
                            @if(auth()->user()->hasPermission('admin.product_collections.update'))
                                <a href="{{ route('admin.marketing.collections.edit', $collection) }}" class="btn btn-sm btn-soft-secondary">تعديل</a>
                            @endif
                            @if(auth()->user()->hasPermission('admin.product_collections.delete'))
                                <form method="POST" action="{{ route('admin.marketing.collections.destroy', $collection) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه المجموعة نهائياً؟ سيتم فصلها عن أقسام الهوم إن وُجدت.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-soft-danger" title="حذف">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">لا توجد مجموعات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($collections->hasPages())
        <div class="card-footer">{{ $collections->links() }}</div>
    @endif
</div>
@endsection
