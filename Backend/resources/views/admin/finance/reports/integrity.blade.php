@extends('layouts.admin')
@section('title', 'مؤشرات السلامة المالية | الوفاء استور')
@section('page-title', 'مؤشرات السلامة المالية')

@section('content')
<div class="card">
    <div class="card-header border-0 pb-0"><h5 class="card-title mb-0">مطابقة المصادر المالية</h5></div>
    <div class="card-body">
        @include('admin.reports.partials.filters')

        <div class="row">
            <div class="col-md-6 col-xl-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted mb-0 fs-13">ميزان المراجعة</p>
                        <span class="badge {{ $result['trial_balance']['is_balanced'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $result['trial_balance']['is_balanced'] ? 'متوازن' : 'غير متوازن' }}</span>
                    </div>
                    <p class="text-muted mb-0 fs-12 mt-2">الفارق: {{ $result['trial_balance']['difference'] }}</p>
                    <p class="text-muted mb-0 fs-12">فترة مفتوحة: {{ $result['trial_balance']['has_open_period'] ? 'نعم' : 'لا' }}</p>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted mb-0 fs-13">عزل عملة الخزينة</p>
                        <span class="badge {{ $result['treasury']['status'] === 'OK' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $result['treasury']['status'] }}</span>
                    </div>
                    <p class="text-muted mb-0 fs-12 mt-2">سطور غير مطابقة: {{ $result['treasury']['mismatched_currency_lines'] }}</p>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted mb-0 fs-13">عهد مندوبي التوصيل</p>
                        <span class="badge {{ $result['cod']['status'] === 'MATCHED' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ $result['cod']['status'] }}</span>
                    </div>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted mb-0 fs-13">حركات المخزون</p>
                        <span class="badge {{ $result['inventory']['status'] === 'OK' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ $result['inventory']['status'] }}</span>
                    </div>
                    <p class="text-muted mb-0 fs-12 mt-2">مواقف غير مطابقة: {{ $result['inventory']['mismatched_positions'] }}</p>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted mb-0 fs-13">محافظ العملاء مقابل الأستاذ العام</p>
                        <span class="badge {{ $result['wallet']['status'] === 'OK' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $result['wallet']['status'] }}</span>
                    </div>
                    <p class="text-muted mb-0 fs-12 mt-2">دفتر المحافظ: {{ $result['wallet']['wallet_ledger_total'] }}</p>
                    <p class="text-muted mb-0 fs-12">الأستاذ العام: {{ $result['wallet']['gl_liability_total'] }}</p>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-4 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted mb-0 fs-13">استخدام الكوبونات</p>
                        <span class="badge {{ $result['coupons']['status'] === 'OK' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ $result['coupons']['status'] }}</span>
                    </div>
                    <p class="text-muted mb-0 fs-12 mt-2">استخدامات نشطة: {{ $result['coupons']['active_usages'] }}</p>
                    <p class="text-muted mb-0 fs-12">طلبات مؤهلة: {{ $result['coupons']['eligible_confirmed_orders'] }}</p>
                </div></div>
            </div>
        </div>
    </div>
</div>
@endsection
