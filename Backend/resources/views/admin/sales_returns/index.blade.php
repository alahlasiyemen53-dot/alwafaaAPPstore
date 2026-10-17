@extends('layouts.admin')

@section('title', 'مرتجعات المبيعات | الوفاء استور')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h4 class="card-title mb-0">
                        <i class="ri-refund-2-line me-1 text-primary"></i> مرتجعات المبيعات ({{ $salesReturns->total() }})
                    </h4>
                    @if(auth()->user()->hasPermission('admin.sales_returns.create'))
                        <a href="{{ route('admin.sales-returns.create') }}" class="btn btn-primary">
                            <i class="ri-add-line me-1"></i> إنشاء مرتجع جديد
                        </a>
                    @endif
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.sales-returns.index') }}" class="row g-3 align-items-end filter-row" id="salesReturnsFilterForm">
                    <div class="col-md-3">
                        <div>
                            <label for="filter_search" class="form-label mb-1">البحث (مرتجع / طلب / عميل)</label>
                            <input type="text" id="filter_search" name="search" class="form-control" placeholder="ابحث برقم المرتجع أو الطلب أو العميل..." value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div>
                            <label for="filter_warehouse_id" class="form-label mb-1">مستودع الاستلام</label>
                            <select id="filter_warehouse_id" name="warehouse_id" class="form-select" data-choices data-choices-search-false>
                                <option value="">جميع المستودعات</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-1">
                        <div>
                            <label for="filter_status" class="form-label mb-1">الحالة</label>
                            <select id="filter_status" name="status" class="form-select" data-choices data-choices-search-false>
                                <option value="">الكل</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div>
                            <label for="filter_from_date" class="form-label mb-1">من تاريخ</label>
                            <input type="text" id="filter_from_date" name="from_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('from_date')) }}" placeholder="يوم-شهر-سنة">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div>
                            <label for="filter_to_date" class="form-label mb-1">إلى تاريخ</label>
                            <input type="text" id="filter_to_date" name="to_date" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('to_date')) }}" placeholder="يوم-شهر-سنة">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ri-search-line me-1"></i> تصفية
                        </button>
                        @if(request()->hasAny(['search', 'warehouse_id', 'status', 'from_date', 'to_date']))
                            <a href="{{ route('admin.sales-returns.index') }}" class="btn btn-soft-danger" title="إعادة ضبط">
                                <i class="ri-refresh-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" style="width: 140px;">رقم المرتجع</th>
                                <th scope="col" style="width: 130px;">رقم الطلب</th>
                                <th scope="col">العميل</th>
                                <th scope="col">مستودع الاستلام</th>
                                <th scope="col" class="text-center">البنود</th>
                                <th scope="col">قيمة المرتجع</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">تاريخ الإنشاء</th>
                                <th scope="col" class="text-end" style="width: 130px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($salesReturns as $return)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.sales-returns.show', $return) }}" class="fw-semibold text-primary">
                                            {{ $return->return_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $return->order_id) }}" class="fw-medium text-secondary">
                                            <i class="ri-shopping-cart-2-line me-1"></i>{{ $return->order?->order_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fw-medium text-dark">{{ $return->order?->customer_name_snapshot ?? $return->order?->customer?->name ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="ri-store-2-line me-1 text-muted"></i>{{ $return->warehouse?->name_ar }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            {{ $return->items_count }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark">
                                            {{ number_format((float) $return->total, 2) }}
                                        </span>
                                        <small class="text-muted">{{ $return->currency?->symbol ?? $return->currency?->code }}</small>
                                    </td>
                                    <td>
                                        <span class="{{ $return->status->badgeClass() }}">
                                            {{ $return->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                         <span class="text-muted fs-12">{{ $return->created_at->format('d-m-Y H:i') }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown d-inline-block">
                                            <button class="btn btn-subtle-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="ri-more-fill align-middle"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.sales-returns.show', $return) }}">
                                                        <i class="ri-eye-line me-2 text-primary"></i> عرض التفاصيل
                                                    </a>
                                                </li>
                                                @if($return->canBeEdited() && auth()->user()->hasPermission('admin.sales_returns.update'))
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('admin.sales-returns.edit', $return) }}">
                                                            <i class="ri-edit-line me-2 text-warning"></i> تعديل المسودة
                                                        </a>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="ri-refund-2-line fs-32 text-muted"></i>
                                        </div>
                                        <h5 class="text-muted">لا توجد مرتجعات مبيعات مسجلة</h5>
                                        <p class="text-muted mb-3 fs-13">يمكنك تسجيل وإدارة مرتجعات البضائع المستلمة من الطلبات المنصرفة.</p>
                                        @if(auth()->user()->hasPermission('admin.sales_returns.create'))
                                            <a href="{{ route('admin.sales-returns.create') }}" class="btn btn-sm btn-primary">
                                                <i class="ri-add-line me-1"></i> إنشاء أول مرتجع
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($salesReturns->hasPages())
                <div class="card-footer py-3 border-top-0">
                    {{ $salesReturns->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
