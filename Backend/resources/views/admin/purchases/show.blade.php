@extends('layouts.admin')

@section('title', __('تفاصيل فاتورة المشتريات') . ' - ' . $invoice->invoice_number)

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
            <h4 class="mb-sm-0">
                <i class="ri-file-list-3-line align-middle me-1"></i>
                {{ __('فاتورة مشتريات') }}: <span class="text-primary">{{ $invoice->invoice_number }}</span>
            </h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('لوحة التحكم') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.purchases.index') }}">{{ __('فواتير المشتريات') }}</a></li>
                    <li class="breadcrumb-item active">{{ $invoice->invoice_number }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ri-check-double-line me-1 align-middle"></i>
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ri-error-warning-line me-1 align-middle"></i>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-1">
                        {{ __('تفاصيل الفاتورة') }}
                        @if($invoice->status->value === 'draft')
                            <span class="badge bg-warning-subtle text-warning fs-12 ms-2">{{ __('مسودة') }}</span>
                        @elseif($invoice->status->value === 'posted')
                            <span class="badge bg-success-subtle text-success fs-12 ms-2">{{ __('مرحلة ومقيدة على المورد') }}</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger fs-12 ms-2">{{ __('ملغاة') }}</span>
                        @endif
                    </h5>
                    <p class="text-muted mb-0">{{ __('تاريخ الفاتورة') }}: <strong>{{ $invoice->invoice_date->format('d-m-Y') }}</strong></p>
                </div>
                <div class="d-flex gap-2">
                    @if($invoice->status->value === 'draft' && auth()->user()->hasPermission('admin.purchases.post'))
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#postInvoiceModal">
                            <i class="ri-check-line align-bottom me-1"></i> {{ __('ترحيل الفاتورة وإثبات الالتزام') }}
                        </button>
                    @endif

                    @if(auth()->user()->hasPermission('admin.purchases.update'))
                        <a href="{{ route('admin.purchases.edit', $invoice) }}" class="btn btn-primary">
                            <i class="ri-edit-line align-bottom me-1"></i> {{ __('تعديل الفاتورة') }}
                        </a>
                    @endif

                    @if(auth()->user()->hasPermission('admin.purchases.delete'))
                        <button type="button" class="btn btn-soft-danger" data-bs-toggle="modal" data-bs-target="#deleteInvoiceModal">
                            <i class="ri-delete-bin-line align-bottom me-1"></i> {{ __('حذف') }}
                        </button>
                    @endif

                    <a href="{{ route('admin.purchases.index') }}" class="btn btn-soft-secondary">
                        <i class="ri-arrow-right-line align-bottom me-1"></i> {{ __('رجوع') }}
                    </a>
                </div>
            </div>

            <div class="card-body">
                @if($invoice->status->value === 'posted')
                    <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                        <i class="ri-information-line fs-24 me-3"></i>
                        <div>
                            <strong>{{ __('ملاحظة ترحيل:') }}</strong>
                            {{ __('تم ترحيل هذه الفاتورة وإثبات التزام آجل لحساب المورد في كشف الحساب. أي تعديل يتم عليها الآن سيحدث قيد الالتزام آلياً في كشف حساب المورد.') }}
                        </div>
                    </div>
                @endif

                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="p-2 border border-dashed rounded text-center">
                            <p class="text-muted mb-1 fs-12">{{ __('المورد') }}</p>
                            <h6 class="mb-0">
                                <a href="{{ route('admin.suppliers.statement', $invoice->supplier) }}" class="text-primary fw-semibold">
                                    {{ $invoice->supplier->name }}
                                </a>
                            </h6>
                            <small class="text-muted">{{ $invoice->supplier->phone ?: __('بدون هاتف') }}</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="p-2 border border-dashed rounded text-center">
                            <p class="text-muted mb-1 fs-12">{{ __('المستودع المستلم') }}</p>
                            <h6 class="mb-0">{{ $invoice->warehouse->name }}</h6>
                            <small class="text-muted">{{ $invoice->warehouse->code }}</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="p-2 border border-dashed rounded text-center">
                            <p class="text-muted mb-1 fs-12">{{ __('العملة وسعر الصرف') }}</p>
                            <h6 class="mb-0">{{ $invoice->currency->code }}</h6>
                            <small class="text-muted">1 {{ $invoice->currency->code }} = {{ number_format($invoice->exchange_rate, 4) }} YER</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="p-2 border border-dashed rounded text-center">
                            <p class="text-muted mb-1 fs-12">{{ __('رقم فاتورة المورد') }}</p>
                            <h6 class="mb-0">{{ $invoice->supplier_invoice_number ?: '—' }}</h6>
                            <small class="text-muted">{{ __('فاتورة المورد اليدوية') }}</small>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>{{ __('المنتج / المتغير') }}</th>
                                <th>{{ __('الباركود / SKU') }}</th>
                                <th>{{ __('الوحدة') }}</th>
                                <th class="text-center">{{ __('الكمية') }}</th>
                                <th class="text-end">{{ __('سعر الشراء') }} ({{ $invoice->currency->code }})</th>
                                <th class="text-end">{{ __('المجموع الفرعي') }}</th>
                                <th class="text-end">{{ __('الخصم') }}</th>
                                <th class="text-end">{{ __('الإجمالي') }} ({{ $invoice->currency->code }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $item->productVariant->product->name ?? '—' }}</div>
                                        @if($item->productVariant && $item->productVariant->relationLoaded('attributeValues') && $item->productVariant->attributeValues->isNotEmpty())
                                            <div class="text-muted fs-11 mt-1">
                                                @foreach($item->productVariant->attributeValues as $val)
                                                    <span class="badge bg-light text-dark border">{{ $val->value_ar ?? $val->value }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <code>{{ $item->productVariant->sku ?? '—' }}</code>
                                        @if($item->productVariant && $item->productVariant->barcode)
                                            <div class="text-muted fs-11">{{ $item->productVariant->barcode }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info">{{ $item->unit->name_ar ?? $item->unit->name }}</span>
                                    </td>
                                    <td class="text-center fw-semibold">
                                        {{ number_format($item->quantity, 2) }}
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($item->unit_cost, 2) }}
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($item->line_subtotal, 2) }}
                                    </td>
                                    <td class="text-end text-danger">
                                        {{ number_format($item->discount_amount, 2) }}
                                    </td>
                                    <td class="text-end fw-bold text-primary">
                                        {{ number_format($item->line_total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($invoice->notes)
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="fs-13 fw-semibold mb-1">{{ __('ملاحظات الفاتورة:') }}</h6>
                        <p class="text-muted mb-0">{{ $invoice->notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card">
            <div class="card-header border-bottom-dashed">
                <h5 class="card-title mb-0">{{ __('الملخص المالي') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">{{ __('المجموع الفرعي:') }}</td>
                                <td class="text-end fw-semibold">{{ number_format($invoice->subtotal, 2) }} {{ $invoice->currency->code }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('إجمالي الخصم:') }}</td>
                                <td class="text-end text-danger fw-semibold">- {{ number_format($invoice->discount_amount, 2) }} {{ $invoice->currency->code }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('تكاليف إضافية/شحن:') }}</td>
                                <td class="text-end fw-semibold">+ {{ number_format($invoice->other_costs, 2) }} {{ $invoice->currency->code }}</td>
                            </tr>
                            <tr class="border-top border-top-dashed">
                                <th class="fs-15">{{ __('الصافي الإجمالي:') }}</th>
                                <th class="text-end fs-16 text-success">{{ number_format($invoice->total, 2) }} {{ $invoice->currency->code }}</th>
                            </tr>
                            @if($invoice->currency->code !== 'YER')
                                <tr class="table-light">
                                    <td class="text-muted fs-12">{{ __('المعادل بالعملة الأساسية (YER):') }}</td>
                                    <td class="text-end fs-13 fw-bold text-dark">{{ number_format($invoice->total * $invoice->exchange_rate, 2) }} YER</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom-dashed">
                <h5 class="card-title mb-0">{{ __('سجل التدقيق والمتابعة') }}</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-3 d-flex align-items-center">
                        <div class="avatar-xs flex-shrink-0 me-3">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                <i class="ri-user-add-line"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fs-13 mb-0">{{ __('أنشئت بواسطة') }}: {{ $invoice->creator->name ?? '—' }}</h6>
                            <small class="text-muted">{{ $invoice->created_at->format('d-m-Y H:i') }}</small>
                        </div>
                    </li>
                    @if($invoice->posted_at)
                        <li class="d-flex align-items-center">
                            <div class="avatar-xs flex-shrink-0 me-3">
                                <span class="avatar-title bg-success-subtle text-success rounded-circle">
                                    <i class="ri-check-double-line"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fs-13 mb-0">{{ __('رُحلت بواسطة') }}: {{ $invoice->poster->name ?? '—' }}</h6>
                                <small class="text-muted">{{ $invoice->posted_at->format('d-m-Y H:i') }}</small>
                            </div>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Modal Post Invoice --}}
@if($invoice->status->value === 'draft')
<div class="modal fade" id="postInvoiceModal" tabindex="-1" aria-labelledby="postInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.purchases.post', $invoice) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="postInvoiceModalLabel">{{ __('تأكيد ترحيل فاتورة المشتريات') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="avatar-lg mx-auto mb-3">
                    <div class="avatar-title bg-success-subtle text-success display-5 rounded-circle">
                        <i class="ri-file-check-line"></i>
                    </div>
                </div>
                <h5>{{ __('هل أنت متأكد من ترحيل الفاتورة رقم') }} <span class="text-primary">{{ $invoice->invoice_number }}</span>؟</h5>
                <p class="text-muted mt-2">
                    {{ __('سيتم قيد مبلغ إجمالي الفاتورة') }}
                    <strong class="text-success">{{ number_format($invoice->total, 2) }} {{ $invoice->currency->code }}</strong>
                    {{ __('كالتزام آجل (دائن) لصالح المورد') }}
                    <strong>{{ $invoice->supplier->name }}</strong>
                    {{ __('في كشف حسابه مباشرة.') }}
                </p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('إلغاء') }}</button>
                <button type="submit" class="btn btn-success">{{ __('نعم، قم بالترحيل') }}</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Modal Delete Invoice --}}
<div class="modal fade" id="deleteInvoiceModal" tabindex="-1" aria-labelledby="deleteInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.purchases.destroy', $invoice) }}" method="POST" class="modal-content">
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteInvoiceModalLabel">{{ __('تأكيد حذف فاتورة المشتريات') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="avatar-lg mx-auto mb-3">
                    <div class="avatar-title bg-danger-subtle text-danger display-5 rounded-circle">
                        <i class="ri-delete-bin-line"></i>
                    </div>
                </div>
                <h5>{{ __('هل أنت متأكد من رغبتك في حذف الفاتورة') }} <span class="text-danger">{{ $invoice->invoice_number }}</span>؟</h5>
                @if($invoice->status->value === 'posted')
                    <div class="alert alert-warning mt-3 text-start">
                        <i class="ri-alert-line me-1"></i>
                        {{ __('تنبيه: هذه الفاتورة مرحلة، وحذفها سيؤدي لحذف قيد الالتزام المرتبط بها من كشف حساب المورد وإلغاء أثره المالي نهائياً.') }}
                    </div>
                @endif
                <p class="text-muted mt-2">{{ __('لا يمكن التراجع عن هذا الإجراء بعد تنفيذه.') }}</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('إلغاء') }}</button>
                <button type="submit" class="btn btn-danger">{{ __('تأكيد الحذف') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
