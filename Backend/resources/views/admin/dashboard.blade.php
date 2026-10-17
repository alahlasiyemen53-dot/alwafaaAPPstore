@extends('layouts.admin')
@section('title', 'الرئيسية | الوفاء استور')
@section('page-title', 'الرئيسية')
@section('content')
@php
    $links = [
        ['admin.cashboxes.view', 'admin.finance.treasury.cashboxes.index', 'ri-safe-2-line', 'إدارة الخزينة والنقدية', 'صناديق الكاش، البنوك، المحافظ، وسندات وحركات الخزينة'],
        ['admin.accounts.view', 'admin.finance.accounts.index', 'ri-book-3-line', 'دليل الحسابات والمالية', 'شجرة الحسابات والقيود اليومية والتقارير الختامية'],
        ['admin.orders.view', 'admin.orders.index', 'ri-shopping-cart-2-line', 'المبيعات والطلبات', 'إدارة ومتابعة طلبات العملاء والمبيعات'],
        ['admin.purchases.view', 'admin.purchases.index', 'ri-file-list-3-line', 'المشتريات والموردين', 'إدارة فواتير الشراء واستلامات البضاعة'],
        ['admin.inventory.view', 'admin.inventory.index', 'ri-archive-line', 'المخزون والمستودعات', 'أرصدة المخزون، التحويلات، والتسويات الجردية'],
        ['admin.customers.view', 'admin.customers.index', 'ri-user-heart-line', 'العملاء', 'إدارة بيانات العملاء وعناوين التوصيل'],
        ['admin.deliveries.view', 'admin.deliveries.index', 'ri-e-bike-2-line', 'عمليات التوصيل والمناديب', 'تتبع رحلات التوصيل وإسنادها للمناديب'],
        ['admin.users.view', 'admin.users.web.index', 'ri-team-line', 'المستخدمون', 'إدارة حسابات فريق العمل وأدواره'],
        ['admin.roles.view', 'admin.roles.web.index', 'ri-shield-user-line', 'الأدوار والصلاحيات', 'ضبط الوصول وفق أقل صلاحية لازمة'],
        ['admin.products.view', 'admin.products.index', 'ri-shopping-bag-3-line', 'الكتالوج والمنتجات', 'إدارة المنتجات، المتغيرات، والخصائص'],
        ['admin.search.view', 'admin.search.index', 'ri-search-eye-line', 'عمليات ومحرك البحث', 'مراقبة فهارس Meilisearch والبحث البصري بالمتجهات ومطابقتها'],
        ['admin.currencies.view', 'admin.master-data.currencies.index', 'ri-money-dollar-circle-line', 'العملات', 'إدارة العملات والعملة الافتراضية'],
        ['admin.exchange_rates.view', 'admin.master-data.exchange-rates', 'ri-exchange-dollar-line', 'أسعار الصرف', 'متابعة الأسعار وتواريخ سريانها'],
        ['admin.locations.view', 'admin.master-data.governorates.index', 'ri-map-pin-2-line', 'المواقع', 'المحافظات والمدن والمديريات'],
        ['admin.brands.view', 'admin.master-data.brands.index', 'ri-price-tag-3-line', 'العلامات التجارية', 'تنظيم العلامات المتاحة في المتجر'],
        ['admin.categories.view', 'admin.master-data.categories.index', 'ri-node-tree', 'التصنيفات', 'إدارة الهيكل الهرمي للتصنيفات'],
        ['admin.warehouses.view', 'admin.master-data.warehouses.index', 'ri-building-4-line', 'المستودعات', 'تعريف مواقع التخزين الأساسية'],
        ['admin.reports.sales.view', 'admin.reports.sales', 'ri-line-chart-line', 'تقرير المبيعات', 'مبيعات مؤكدة حسب العملة والفترة'],
        ['admin.reports.inventory.view', 'admin.reports.inventory', 'ri-stack-line', 'تقرير المخزون', 'أرصدة المتاح والمحجوز حسب المستودع'],
        ['admin.reports.delivery.view', 'admin.reports.delivery', 'ri-truck-line', 'تقرير التوصيل', 'نسبة النجاح وحالات الشحنات'],
        ['admin.reports.driver_cod.view', 'admin.reports.driver-cod', 'ri-wallet-3-line', 'عهدة مندوبي التوصيل', 'المحصل والمسوى والمتبقي لكل مندوب'],
        ['admin.reports.customers.view', 'admin.reports.customers', 'ri-group-line', 'تقرير العملاء', 'العملاء الجدد والموثقون'],
        ['admin.reports.marketing.view', 'admin.reports.marketing', 'ri-price-tag-2-line', 'تقرير التسويق', 'خصومات العروض والكوبونات والباقات'],
        ['admin.reports.purchasing.view', 'admin.reports.purchasing', 'ri-truck-line', 'تقرير المشتريات', 'فواتير الشراء المرحلة حسب المورد'],
        ['admin.reports.returns.view', 'admin.reports.returns', 'ri-arrow-go-back-line', 'تقرير مرتجعات المبيعات', 'المرتجعات المعتمدة حسب العملة'],
        ['admin.reports.loyalty.view', 'admin.reports.loyalty', 'ri-medal-line', 'تقرير نقاط الولاء', 'نشاط الاكتساب والاستبدال'],
        ['admin.reports.customer_wallets.view', 'admin.reports.customer-wallets', 'ri-bank-card-line', 'تقرير محافظ العملاء', 'رصيد الالتزام لكل عملة'],
        ['admin.reports.finance.view', 'admin.finance.reports.integrity', 'ri-shield-check-line', 'مؤشرات السلامة المالية', 'مطابقة الميزان والخزينة والمخزون والمحافظ'],
    ];
    $links = array_values(array_filter($links, fn ($link) => $profile->hasPermission($link[0])));
@endphp
<section class="card dashboard-welcome"><div class="card-body p-4"><div class="row align-items-center"><div class="col-sm"><p class="text-uppercase fw-semibold text-primary mb-2">مساحة عملك اليومية</p><h2 class="fs-24 mb-2">أهلًا بك، {{ $profile->name }}</h2><p class="text-muted mb-0">اختر القسم المطلوب. لا تظهر هنا إلا الأدوات التي تسمح بها صلاحيات حسابك.</p></div><div class="col-sm-auto d-none d-sm-block"><i class="ri-sparkling-2-line welcome-icon" aria-hidden="true"></i></div></div></div></section>

@if(!empty($dashboard['alerts']))
<div class="row mt-3">
    <div class="col-12">
        @foreach($dashboard['alerts'] as $alert)
            <div class="alert alert-{{ $alert['level'] === 'danger' ? 'danger' : ($alert['level'] === 'warning' ? 'warning' : 'info') }} d-flex align-items-center" role="alert">
                <i class="ri-{{ $alert['level'] === 'danger' ? 'error-warning' : ($alert['level'] === 'warning' ? 'alert' : 'information') }}-fill me-2"></i>
                <div>{{ $alert['message'] }}</div>
            </div>
        @endforeach
    </div>
</div>
@endif

@if(!empty($dashboard['kpis']))
<div class="row mt-1">
    @if(isset($dashboard['kpis']['sales']))
        @foreach($dashboard['kpis']['sales'] as $row)
        <div class="col-md-6 col-xl-3 mb-3">
            <div class="card h-100"><div class="card-body">
                <p class="text-muted mb-1 fs-13">مبيعات مؤكدة ({{ $row['currency_code'] }})</p>
                <h3 class="fs-20 mb-1">{{ $row['net_total'] }}</h3>
                <p class="text-muted mb-0 fs-12">{{ $row['orders_count'] }} طلب — متوسط {{ $row['average_order_value'] }}</p>
            </div></div>
        </div>
        @endforeach
    @endif
    @if(isset($dashboard['kpis']['inventory']))
        <div class="col-md-6 col-xl-3 mb-3">
            <div class="card h-100"><div class="card-body">
                <p class="text-muted mb-1 fs-13">حالة المخزون</p>
                <h3 class="fs-20 mb-1">{{ $dashboard['kpis']['inventory']['positions_count'] }} موقف</h3>
                <p class="text-muted mb-0 fs-12">{{ $dashboard['kpis']['inventory']['out_of_stock_count'] }} بدون رصيد متاح</p>
            </div></div>
        </div>
    @endif
    @if(isset($dashboard['kpis']['delivery']))
        <div class="col-md-6 col-xl-3 mb-3">
            <div class="card h-100"><div class="card-body">
                <p class="text-muted mb-1 fs-13">نسبة نجاح التوصيل</p>
                <h3 class="fs-20 mb-1">{{ $dashboard['kpis']['delivery']['success_rate'] }}%</h3>
                <p class="text-muted mb-0 fs-12">مُسلّم {{ $dashboard['kpis']['delivery']['status_counts']['delivered'] }} — تعذّر {{ $dashboard['kpis']['delivery']['status_counts']['failed'] }}</p>
            </div></div>
        </div>
    @endif
    @if(isset($dashboard['kpis']['customers']))
        <div class="col-md-6 col-xl-3 mb-3">
            <div class="card h-100"><div class="card-body">
                <p class="text-muted mb-1 fs-13">عملاء جدد</p>
                <h3 class="fs-20 mb-1">{{ $dashboard['kpis']['customers']['new_customers'] }}</h3>
                <p class="text-muted mb-0 fs-12">{{ $dashboard['kpis']['customers']['verified_customers'] }} موثّق الهاتف</p>
            </div></div>
        </div>
    @endif
    @if(isset($dashboard['kpis']['integrity']))
        <div class="col-12 mb-3">
            <div class="card"><div class="card-body">
                <h3 class="fs-15 mb-3">مؤشرات السلامة المالية</h3>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge {{ $dashboard['kpis']['integrity']['trial_balance']['is_balanced'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">ميزان المراجعة: {{ $dashboard['kpis']['integrity']['trial_balance']['is_balanced'] ? 'متوازن' : 'غير متوازن' }}</span>
                    <span class="badge {{ $dashboard['kpis']['integrity']['treasury']['status'] === 'OK' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">الخزينة: {{ $dashboard['kpis']['integrity']['treasury']['status'] }}</span>
                    <span class="badge {{ $dashboard['kpis']['integrity']['cod']['status'] === 'MATCHED' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">عهد المناديب: {{ $dashboard['kpis']['integrity']['cod']['status'] }}</span>
                    <span class="badge {{ $dashboard['kpis']['integrity']['inventory']['status'] === 'OK' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">المخزون: {{ $dashboard['kpis']['integrity']['inventory']['status'] }}</span>
                    <span class="badge {{ $dashboard['kpis']['integrity']['wallet']['status'] === 'OK' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">محافظ العملاء: {{ $dashboard['kpis']['integrity']['wallet']['status'] }}</span>
                </div>
            </div></div>
        </div>
    @endif
</div>
@endif

<div class="row mt-1">
    <div class="col-xl-9"><div class="page-heading"><div><h2 class="fs-18 mb-1">اختصارات الإدارة</h2><p>وصول مباشر إلى الوحدات المنفّذة.</p></div><span class="badge bg-primary-subtle text-primary">{{ count($links) }} أقسام متاحة</span></div><div class="row">
        @forelse($links as $link)<div class="col-md-6 col-xl-4 mb-3"><a class="card quick-link text-reset text-decoration-none" href="{{ route($link[1]) }}"><div class="card-body"><div class="d-flex align-items-start justify-content-between"><span class="avatar-sm"><span class="avatar-title bg-primary-subtle text-primary rounded"><i class="{{ $link[2] }}"></i></span></span><i class="ri-arrow-left-line text-muted"></i></div><h3 class="fs-15 mt-3">{{ $link[3] }}</h3><p class="text-muted mb-0 fs-13">{{ $link[4] }}</p></div></a></div>@empty<div class="col-12"><div class="card"><div class="card-body text-center py-5"><div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-lock-line"></i></div></div><h5 class="fs-16 mb-1">لا توجد أقسام متاحة</h5><p class="text-muted mb-0">راجع مسؤول النظام لمنح الصلاحيات المناسبة.</p></div></div></div>@endforelse
    </div></div>
    <div class="col-xl-3"><div class="card"><div class="card-header"><h2 class="card-title mb-0 fs-15">ملفك الإداري</h2></div><div class="card-body text-center"><span class="avatar-lg mx-auto"><span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-24">{{ mb_substr($profile->name, 0, 1) }}</span></span><h3 class="fs-15 mt-3 mb-1">{{ $profile->name }}</h3><bdi class="text-muted fs-12">{{ $profile->email ?: $profile->phone }}</bdi><div class="d-flex flex-wrap justify-content-center gap-1 mt-3">@forelse($profile->roles as $role)<span class="badge bg-info-subtle text-info">{{ $role->display_name ?: $role->name }}</span>@empty<span class="text-muted">لا توجد أدوار</span>@endforelse</div></div></div></div>
</div>
@endsection
