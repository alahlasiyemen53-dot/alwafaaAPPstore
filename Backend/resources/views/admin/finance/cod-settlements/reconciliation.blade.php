@extends('layouts.admin')
@section('title', 'مطابقة عهد وتحصيلات COD المالية | الوفاء استور')
@section('page-title', 'مطابقة عهد وتحصيلات الدفع عند الاستلام')

@section('content')
<div class="row">
    <div class="col-12">
        <!-- Status Header Card -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <div class="avatar-md me-3">
                            <span class="avatar-title rounded-circle {{ $reconciliation['status'] === 'MATCHED' ? 'bg-success text-white' : 'bg-danger text-white' }} fs-24">
                                <i class="{{ $reconciliation['status'] === 'MATCHED' ? 'ri-shield-check-line' : 'ri-error-warning-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h4 class="mb-1">
                                حالة المطابقة المالية: 
                                @if($reconciliation['status'] === 'MATCHED')
                                    <span class="text-success fw-bold">متطابقة بالكامل 100% (MATCHED)</span>
                                @else
                                    <span class="text-danger fw-bold">يوجد فروقات تتطلب المراجعة (DISCREPANCY)</span>
                                @endif
                            </h4>
                            <p class="text-muted mb-0">تشخيص مطابقة وقائع التحصيل التشغيلي مع مخصصات التسويات وقيود اليومية العامة وأرصدة الخزينة.</p>
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('admin.finance.cod-settlements.reconciliation') }}" class="btn btn-outline-primary btn-sm">
                            <i class="ri-refresh-line me-1"></i> إعادة تشغيل فحص المطابقة
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($reconciliation['discrepancies']))
            <div class="alert alert-danger" role="alert">
                <h5 class="alert-heading mb-2"><i class="ri-alert-line me-1"></i> الفروقات المرصودة في النظام (عدم تطابق):</h5>
                <ul class="mb-0">
                    @foreach($reconciliation['discrepancies'] as $disc)
                        <li>{{ $disc }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Summary Counters -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-animate mb-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">التسويات المعتمدة والمرحلة</p>
                                <h4 class="fs-22 fw-semibold mb-0 mt-2 text-success">{{ $reconciliation['summary']['total_posted_settlements'] }}</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                                    <i class="ri-check-double-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-animate mb-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">التسويات المسودة (بدون أثر مالي)</p>
                                <h4 class="fs-22 fw-semibold mb-0 mt-2 text-warning">{{ $reconciliation['summary']['total_draft_settlements'] }}</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                                    <i class="ri-draft-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-animate mb-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">التسويات المعكوسة / الملغاة</p>
                                <h4 class="fs-22 fw-semibold mb-0 mt-2 text-danger">{{ $reconciliation['summary']['total_reversed_settlements'] }}</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle text-danger rounded fs-3">
                                    <i class="ri-close-circle-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Currencies Reconciliation Breakdown -->
        <div class="card">
            <div class="card-header border-bottom-dashed">
                <h5 class="card-title mb-0">المطابقة المحاسبية والتشغيلية حسب العملات</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>العملة</th>
                                <th class="text-end">إجمالي تحصيلات الشحنات (Fact)</th>
                                <th class="text-end">إجمالي التسويات المرحلة (Settled)</th>
                                <th class="text-end">الرصيد المستحق (Outstanding)</th>
                                <th class="text-center">عدد التسويات</th>
                                <th class="text-center">حالة المطابقة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reconciliation['currencies_summary'] as $currSummary)
                                <tr>
                                    <td>
                                        <strong>{{ $currSummary['currency_name'] }}</strong>
                                        <span class="badge bg-light text-body border ms-1">{{ $currSummary['currency_code'] }}</span>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        {{ number_format((float)$currSummary['total_collected'], 2) }}
                                    </td>
                                    <td class="text-end fw-semibold text-success">
                                        {{ number_format((float)$currSummary['total_settled'], 2) }}
                                    </td>
                                    <td class="text-end fw-bold {{ $currSummary['total_outstanding'] > 0 ? 'text-primary' : 'text-muted' }}">
                                        {{ number_format((float)$currSummary['total_outstanding'], 2) }}
                                    </td>
                                    <td class="text-center">{{ $currSummary['settlements_count'] }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="ri-check-line me-1"></i> متطابق
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rules and Explanations Card -->
        <div class="card">
            <div class="card-header border-bottom-dashed">
                <h6 class="card-title mb-0"><i class="ri-information-line me-1 text-primary"></i> محددات وضوابط المطابقة المالية لعهد المناديب</h6>
            </div>
            <div class="card-body fs-13 text-muted">
                <ul class="mb-0">
                    <li>المعادلة المحاسبية الأساسية: <code>الرصيد المستحق على المندوب = إجمالي تحصيلات الشحنات المسلمة - إجمالي التسويات المعتمدة والمرحلة</code>.</li>
                    <li>حالات التسوية المسودة (Draft) والمعكوسة (Reversed) لا تترتب عليها أي حركة نقدية أو تخفيض في الرصيد المستحق على المندوب.</li>
                    <li>كل تسوية مرحلة مرتبطة بقيد يومية متوازن إلزامي في دفتر الأستاذ العام (GL) بحساب وسيط عهد المناديب (1160) وحساب الخزينة المستلمة.</li>
                    <li>لا يتم تطبيق أي تصحيح صامت عند حدوث فروقات؛ ويتم رصد وتسجيل أي انحراف في تقرير المطابقة للتدقيق المالي.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
