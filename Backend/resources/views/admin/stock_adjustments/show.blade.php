@extends('layouts.admin')

@section('title', 'تفاصيل أمر تسوية جردية | الوفاء استور')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="card-title mb-0">
                            <i class="ri-equalizer-line me-1 text-primary"></i> أمر تسوية: {{ $adjustment->adjustment_number }}
                        </h5>
                        <span class="badge {{ $adjustment->status->badgeClass() }} fs-12">
                            {{ $adjustment->status->label() }}
                        </span>
                    </div>

                    <div class="d-flex gap-2">
                        @if($adjustment->status === \App\Enums\StockAdjustmentStatus::Draft)
                            @if(auth()->user()->hasPermission('admin.stock_adjustments.update'))
                                <a href="{{ route('admin.stock-adjustments.edit', $adjustment) }}" class="btn btn-soft-primary btn-sm">
                                    <i class="ri-edit-line me-1"></i> تعديل
                                </a>
                            @endif

                            @if(auth()->user()->hasPermission('admin.stock_adjustments.post'))
                                <form method="POST" action="{{ route('admin.stock-adjustments.post', $adjustment) }}" onsubmit="return confirm('تنبيه أمني: هل أنت متأكد من ترحيل أمر التسوية الجردية؟ سيتم تعديل المخزون الفعلي فوراً.')">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="ri-check-line me-1"></i> ترحيل أمر التسوية
                                    </button>
                                </form>
                            @endif

                            @if(auth()->user()->hasPermission('admin.stock_adjustments.delete'))
                                <form method="POST" action="{{ route('admin.stock-adjustments.destroy', $adjustment) }}" onsubmit="return confirm('هل أنت متأكد من حذف أمر التسوية؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger btn-sm">
                                        <i class="ri-delete-bin-line me-1"></i> حذف
                                    </button>
                                </form>
                            @endif
                        @else
                            <span class="badge bg-success-subtle text-success border border-success-subtle p-2">
                                <i class="ri-check-double-line me-1"></i> تم الترحيل وتعديل أرصدة المخزون
                            </span>
                        @endif

                        <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-light btn-sm">
                            <i class="ri-arrow-right-line me-1"></i> العودة
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">المستودع</span>
                            <strong class="text-dark fs-14">
                                <i class="ri-building-4-line me-1 text-primary"></i>{{ $adjustment->warehouse?->name_ar ?? '—' }}
                            </strong>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">سبب التسوية</span>
                            <strong>{{ $adjustment->reason_code->label() }}</strong>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">الحالة</span>
                            <strong>{{ $adjustment->status->label() }}</strong>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">تاريخ الترحيل</span>
                            <strong class="text-dark">
                                {{ $adjustment->posted_at?->format('Y-m-d H:i') ?? 'بانتظار الترحيل' }}
                            </strong>
                        </div>
                    </div>
                </div>

                @if($adjustment->notes)
                    <div class="alert alert-light border mb-4">
                        <strong>ملاحظات التسوية:</strong> {{ $adjustment->notes }}
                    </div>
                @endif

                <h6 class="fw-bold mb-3">
                    <i class="ri-box-3-line me-1 text-primary"></i> بنود التسوية ({{ $adjustment->items->count() }}):
                </h6>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>الصنف / المتغير</th>
                                <th>رمز الصنف (SKU)</th>
                                <th class="text-center">نوع الحركة</th>
                                <th class="text-center">الكمية</th>
                                <th>الملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($adjustment->items as $i => $item)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <strong>{{ $item->productVariant?->product?->name_ar ?? '—' }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-body border" dir="ltr">
                                            {{ $item->productVariant?->sku ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $item->direction->badgeClass() }}">
                                            {{ $item->direction->label() }}
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold fs-14 {{ $item->direction->value === 'in' ? 'text-success' : 'text-danger' }}">
                                        {{ $item->direction->value === 'in' ? '+' : '-' }}{{ number_format((float) $item->quantity, 2) }}
                                    </td>
                                    <td class="text-muted">{{ $item->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4 pt-3 border-top text-muted fs-12">
                    <div class="col-md-6">
                        أنشئ بواسطة: <strong>{{ $adjustment->createdBy?->name ?? '—' }}</strong> في {{ $adjustment->created_at->format('Y-m-d H:i') }}
                    </div>
                    @if($adjustment->posted_by)
                        <div class="col-md-6 text-md-end">
                            تم الترحيل بواسطة: <strong>{{ $adjustment->postedBy?->name ?? '—' }}</strong> في {{ $adjustment->posted_at?->format('Y-m-d H:i') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
