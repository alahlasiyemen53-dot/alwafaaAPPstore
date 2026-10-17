@extends('layouts.admin')
@section('title', 'مطابقة ذمم الموردين مع الأستاذ العام | الوفاء استور')
@section('page-title', 'مطابقة حساب الموردين والذمم الدائنة')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">تشخيص ومطابقة الذمم الدائنة (Supplier Ledger ↔ GL)</h5>
                    <a href="{{ route('admin.finance.reports.general-ledger') }}" class="btn btn-light btn-sm">
                        <i class="ri-arrow-right-line me-1"></i> العودة للأستاذ العام
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($reconciliation['is_matched'])
                    <div class="alert alert-success d-flex align-items-center p-3 mb-4">
                        <i class="ri-checkbox-circle-fill fs-24 me-3 text-success"></i>
                        <div>
                            <h5 class="alert-heading mb-1 text-success fw-bold">تطابق تام بنسبة 100%</h5>
                            <p class="mb-0">إجمالي التزامات الموردين في سجل الموردين التشغيلي (سبرنت 7) يتطابق بالكامل وبدقة مع رصيد حساب الذمم الدائنة في الأستاذ العام، ولا توجد أي ازدواجية أو فروقات محاسبية.</p>
                        </div>
                    </div>
                @else
                    <div class="alert alert-danger d-flex align-items-center p-3 mb-4">
                        <i class="ri-error-warning-fill fs-24 me-3 text-danger"></i>
                        <div>
                            <h5 class="alert-heading mb-1 text-danger fw-bold">تنبيه: يوجد فرق في المطابقة المحاسبية</h5>
                            <p class="mb-0">هناك اختلاف بين رصيد سجل الموردين التشغيلي ورصيد حساب الذمم الدائنة في الأستاذ العام بفارق: <strong class="font-monospace">{{ $reconciliation['difference'] }}</strong></p>
                        </div>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card bg-light border-0">
                            <div class="card-body p-4 text-center">
                                <span class="text-muted d-block mb-1">إجمالي رصيد التزامات الموردين (سجل الموردين - سبرنت 7)</span>
                                <h3 class="fw-bold font-monospace text-primary mb-0">
                                    {{ number_format((float)$reconciliation['supplier_subledger_total_base'], 2) }}
                                </h3>
                                <small class="text-muted">صافي الدائن بالعملة الأساسية</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card bg-light border-0">
                            <div class="card-body p-4 text-center">
                                <span class="text-muted d-block mb-1">إجمالي رصيد الذمم الدائنة (الأستاذ العام - سبرنت 13)</span>
                                <h3 class="fw-bold font-monospace text-success mb-0">
                                    {{ number_format((float)$reconciliation['gl_accounts_payable_total_base'], 2) }}
                                </h3>
                                <small class="text-muted">صافي الدائن بحساب الموردين 2110</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 text-center">
                        <div class="p-3 bg-light rounded border d-inline-block px-5">
                            <span class="text-muted d-block small">الفارق الحسابي</span>
                            <h4 class="font-monospace fw-bold mb-0 {{ $reconciliation['is_matched'] ? 'text-success' : 'text-danger' }}">
                                {{ number_format((float)$reconciliation['difference'], 4) }}
                            </h4>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top text-muted small">
                    <i class="ri-information-line me-1"></i> يتم إجراء المطابقة استناداً إلى الحركات المرحلة فقط (POSTED Facts). لا يُسمح بإجراء تسويات تلقائية صامتة وفقاً لمحددات سبرنت 13 الصارمة.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
