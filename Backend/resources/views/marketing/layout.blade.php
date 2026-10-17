<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'الوفاء استور' }} — AL-Wafa'a Store</title>
    <meta name="description" content="{{ $metaDescription ?? 'متجر رقمي لمستحضرات التجميل والعطور المختارة.' }}">
    <meta name="theme-color" content="#800020">
    <link rel="icon" href="{{ asset('assets/images/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/marketing/css/site.css') }}">
</head>
<body>
@php
    $current = Route::currentRouteName();
@endphp
<header class="site-header">
    <div class="container nav-bar">
        <a class="brand" href="{{ route('marketing.home') }}" aria-label="الوفاء استور">
            <span class="brand-mark" aria-hidden="true">AW</span>
            <span class="brand-text">
                <strong>الوفاء استور</strong>
                <span>AL-Wafa'a Store</span>
            </span>
        </a>

        <button
            class="nav-toggle"
            type="button"
            data-nav-toggle
            aria-expanded="false"
            aria-controls="site-nav"
            aria-label="فتح القائمة"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav id="site-nav" class="nav-panel" data-nav-panel aria-label="التنقل الرئيسي">
            <ul class="nav-links">
                <li><a href="{{ route('marketing.home') }}" @class(['is-active' => $current === 'marketing.home'])>الرئيسية</a></li>
                <li><a href="{{ route('marketing.about') }}" @class(['is-active' => $current === 'marketing.about'])>من نحن</a></li>
                <li><a href="{{ route('marketing.contact') }}" @class(['is-active' => $current === 'marketing.contact'])>تواصل معنا</a></li>
                <li><a href="{{ route('marketing.privacy') }}" @class(['is-active' => $current === 'marketing.privacy'])>الخصوصية</a></li>
                <li><a href="{{ route('marketing.delete-account') }}" @class(['is-active' => $current === 'marketing.delete-account'])>حذف الحساب</a></li>
            </ul>
        </nav>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <strong>الوفاء استور</strong>
                <span>AL-Wafa'a Store</span>
                <p style="margin:0.85rem 0 0;font-size:0.92rem;max-width:28ch;">
                    منصة رقمية لعرض منتجات التجميل والعطور واستقبال الطلبات بعناية من الاكتشاف حتى التوصيل.
                </p>
            </div>
            <div>
                <p style="margin:0 0 0.65rem;color:#fff7fa;font-weight:700;">استكشف</p>
                <ul class="footer-links">
                    <li><a href="{{ route('marketing.home') }}">الصفحة الرئيسية</a></li>
                    <li><a href="{{ route('marketing.about') }}">من نحن</a></li>
                    <li><a href="{{ route('marketing.contact') }}">تواصل معنا</a></li>
                </ul>
            </div>
            <div>
                <p style="margin:0 0 0.65rem;color:#fff7fa;font-weight:700;">قانوني</p>
                <ul class="footer-links">
                    <li><a href="{{ route('marketing.privacy') }}">سياسة الخصوصية</a></li>
                    <li><a href="{{ route('marketing.delete-account') }}">تعليمات حذف الحساب</a></li>
                </ul>
            </div>
        </div>
        <p class="footer-copy">© {{ date('Y') }} الوفاء استور — AL-Wafa'a Store. جميع الحقوق محفوظة.</p>
    </div>
</footer>

<script src="{{ asset('assets/marketing/js/site.js') }}" defer></script>
</body>
</html>
