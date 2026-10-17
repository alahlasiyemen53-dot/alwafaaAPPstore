@extends('layouts.admin')

@section('title', 'تقرير مطابقة أرصدة الخزينة مع الأستاذ العام (Reconciliation)')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">تقرير مطابقة أرصدة الخزينة مع الأستاذ العام (GL Reconciliation)</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item active">مطابقة الخزينة</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center" role="alert">
        <i class="ri-information-fill fs-3 me-3 text-info"></i>
        <div>
            <h6 class="alert-heading mb-1 fw-bold">آلية المطابقة المالية الصارمة</h6>
            <span>يعرض التقرير كل صف كـ حساب × عملة. الرصيد بالعملة الأجنبية مشتق من مبالغ الحركة، والمكافئ بالعملة الأساسية من مدين/دائن القيد بعد المصارفة. حساب الأستاذ قد يكون مشتركاً بين عملات نفس الأب.</span>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">نوع الحساب</label>
                    <select name="account_type" class="form-select">
                        <option value="all" @selected(($accountTypeFilter ?? 'all') === 'all')>الكل</option>
                        <option value="cashbox" @selected(($accountTypeFilter ?? '') === 'cashbox')>صندوق نقدي</option>
                        <option value="bank_account" @selected(($accountTypeFilter ?? '') === 'bank_account')>حساب بنكي</option>
                        <option value="electronic_wallet" @selected(($accountTypeFilter ?? '') === 'electronic_wallet')>محفظة إلكترونية</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">تصفية</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">جدول مطابقة الحسابات التشغيلية (حساب × عملة)</h5>
            <span class="badge bg-soft-success text-success">تحديث لحظي</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>النوع</th>
                            <th>كود الحساب</th>
                            <th>اسم الحساب التشغيلي</th>
                            <th>حساب الأستاذ العام (GL)</th>
                            <th>العملة</th>
                            <th class="text-end">الرصيد بالعملة</th>
                            <th class="text-end">المكافئ بالعملة الأساسية</th>
                            <th class="text-center">الحركات المرحلة</th>
                            <th class="text-center">حالة المطابقة والسلامة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reconciliationRows as $row)
                            <tr>
                                <td>
                                    <span class="badge bg-soft-secondary text-secondary">
                                        {{ $row['type_name'] }}
                                    </span>
                                </td>
                                <td><code>{{ $row['code'] }}</code></td>
                                <td class="fw-semibold">{{ $row['name'] }}</td>
                                <td>
                                    <code>{{ $row['gl_code'] }}</code> - {{ $row['gl_name'] }}
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $row['currency'] }}</span>
                                </td>
                                <td class="text-end fw-bold {{ (float)$row['gl_balance'] < 0 ? 'text-danger' : 'text-primary' }}">
                                    {{ number_format((float)$row['gl_balance'], 2) }}
                                </td>
                                <td class="text-end fw-semibold {{ (float)($row['base_balance'] ?? 0) < 0 ? 'text-danger' : 'text-dark' }}">
                                    {{ number_format((float)($row['base_balance'] ?? 0), 2) }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-soft-info text-info">{{ $row['posted_trx_count'] }} حركة</span>
                                </td>
                                <td class="text-center">
                                    @if($row['is_consistent'])
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="ri-checkbox-circle-fill me-1"></i> سليم ومتطابق
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">
                                            <i class="ri-error-warning-fill me-1"></i> يوجد خلل ({{ $row['orphan_count'] }} غير مرتبط)
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">لم يتم إيجاد أي حسابات خزينة تشغيلية مسجلة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
