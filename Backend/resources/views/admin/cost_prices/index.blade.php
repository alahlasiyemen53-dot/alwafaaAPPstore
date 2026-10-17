@extends('layouts.admin')
@section('title', 'أسعار الشراء | الوفاء استور')
@section('page-title', 'إدارة أسعار شراء الأصناف')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">
                    <i class="ri-money-dollar-circle-line me-1 text-primary"></i>
                    أسعار الشراء (منفصلة عن سعر البيع)
                </h4>
                <div class="text-muted fs-12">تُستخدم هذه الأسعار في فاتورة الشراء التلقائية للأصناف تحت الطلب</div>
            </div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.cost-prices.index') }}" class="row g-2">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالاسم أو SKU...">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">بحث</button>
                        <a href="{{ route('admin.cost-prices.index') }}" class="btn btn-light">إعادة ضبط</a>
                    </div>
                </form>
            </div>
            <form method="POST" action="{{ route('admin.cost-prices.update') }}">
                @csrf
                @method('PUT')
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>المنتج</th>
                                <th>SKU</th>
                                <th>المورد</th>
                                <th style="width: 180px;">سعر الشراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($variants as $variant)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $variant->product?->name_ar ?? '—' }}</div>
                                        @if($variant->product?->name_en)
                                            <div class="fs-11 text-muted">{{ $variant->product->name_en }}</div>
                                        @endif
                                    </td>
                                    <td><code>{{ $variant->sku }}</code></td>
                                    <td>
                                        @if($variant->supplier)
                                            <span class="badge bg-light text-dark border">{{ $variant->supplier->code }}</span>
                                            {{ $variant->supplier->name }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="number"
                                               step="0.0001"
                                               min="0"
                                               class="form-control form-control-sm"
                                               name="costs[{{ $variant->id }}]"
                                               value="{{ old('costs.'.$variant->id, $variant->cost_price) }}">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">لا توجد أصناف</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div>{{ $variants->links() }}</div>
                    @if($variants->isNotEmpty())
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i> حفظ أسعار الشراء
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
