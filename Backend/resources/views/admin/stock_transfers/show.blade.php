@extends('layouts.admin')

@section('title', 'تفاصيل أمر تحويل مخزون | الوفاء استور')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="card-title mb-0">
                            <i class="ri-arrow-left-right-line me-1 text-primary"></i> أمر تحويل: {{ $transfer->transfer_number }}
                        </h5>
                        <span class="badge {{ $transfer->status->badgeClass() }} fs-12">
                            {{ $transfer->status->label() }}
                        </span>
                    </div>

                    <div class="d-flex gap-2">
                        @if($transfer->status === \App\Enums\StockTransferStatus::Draft)
                            @if(auth()->user()->hasPermission('admin.stock_transfers.update'))
                                <a href="{{ route('admin.stock-transfers.edit', $transfer) }}" class="btn btn-soft-primary btn-sm">
                                    <i class="ri-edit-line me-1"></i> تعديل
                                </a>
                            @endif

                            @if(auth()->user()->hasPermission('admin.stock_transfers.post'))
                                <form method="POST" action="{{ route('admin.stock-transfers.post', $transfer) }}" onsubmit="return confirm('هل أنت متأكد من ترحيل أمر التحويل؟ سيتم خصم الكميات من مستودع المصدر وإضافتها لمستودع الوجهة فوراً.')">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="ri-check-line me-1"></i> ترحيل أمر التحويل
                                    </button>
                                </form>
                            @endif

                            @if(auth()->user()->hasPermission('admin.stock_transfers.delete'))
                                <form method="POST" action="{{ route('admin.stock-transfers.destroy', $transfer) }}" onsubmit="return confirm('هل أنت متأكد من حذف أمر التحويل؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger btn-sm">
                                        <i class="ri-delete-bin-line me-1"></i> حذف
                                    </button>
                                </form>
                            @endif
                        @else
                            <span class="badge bg-success-subtle text-success border border-success-subtle p-2">
                                <i class="ri-check-double-line me-1"></i> تم الترحيل وانتقال المخزون بنجاح
                            </span>
                        @endif

                        <a href="{{ route('admin.stock-transfers.index') }}" class="btn btn-light btn-sm">
                            <i class="ri-arrow-right-line me-1"></i> العودة
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">من مستودع (المصدر)</span>
                            <strong class="text-danger fs-14">
                                <i class="ri-upload-line me-1"></i>{{ $transfer->fromWarehouse?->name_ar ?? '—' }}
                            </strong>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">إلى مستودع (الوجهة)</span>
                            <strong class="text-success fs-14">
                                <i class="ri-download-line me-1"></i>{{ $transfer->toWarehouse?->name_ar ?? '—' }}
                            </strong>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">الحالة</span>
                            <strong class="text-dark">{{ $transfer->status->label() }}</strong>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted fs-12 d-block mb-1">تاريخ الترحيل</span>
                            <strong class="text-dark">
                                {{ $transfer->posted_at?->format('Y-m-d H:i') ?? 'بانتظار الترحيل' }}
                            </strong>
                        </div>
                    </div>
                </div>

                @if($transfer->notes)
                    <div class="alert alert-light border mb-4">
                        <strong>ملاحظات التحويل:</strong> {{ $transfer->notes }}
                    </div>
                @endif

                <h6 class="fw-bold mb-3">
                    <i class="ri-box-3-line me-1 text-primary"></i> الأصناف المحوّلة ({{ $transfer->items->count() }}):
                </h6>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>الصنف / المتغير</th>
                                <th>رمز الصنف (SKU)</th>
                                <th class="text-center">الكمية المحولة (بالوحدة الأساسية)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transfer->items as $i => $item)
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
                                    <td class="text-center fw-bold fs-14 text-dark">
                                        {{ number_format((float) $item->quantity, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4 pt-3 border-top text-muted fs-12">
                    <div class="col-md-6">
                        أنشئ بواسطة: <strong>{{ $transfer->createdBy?->name ?? '—' }}</strong> في {{ $transfer->created_at->format('Y-m-d H:i') }}
                    </div>
                    @if($transfer->posted_by)
                        <div class="col-md-6 text-md-end">
                            تم الترحيل بواسطة: <strong>{{ $transfer->postedBy?->name ?? '—' }}</strong> في {{ $transfer->posted_at?->format('Y-m-d H:i') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
