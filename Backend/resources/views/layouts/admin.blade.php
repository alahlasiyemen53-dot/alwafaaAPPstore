<!doctype html>
<html lang="ar" dir="rtl" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة الإدارة | الوفاء استور')</title>
    <link rel="icon" href="{{ asset('assets/images/favicon.svg') }}" type="image/svg+xml">
    <script src="{{ asset('assets/js/layout.js') }}"></script>
    <link href="{{ asset('assets/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/libs/simplebar/simplebar.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/cairo.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/bootstrap-rtl.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/app-rtl.min.css') }}" rel="stylesheet" type="text/css">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @stack('styles')
</head>
<body>
@auth
    <a class="skip-link" href="#main-content">انتقل إلى المحتوى الرئيسي</a>
    <div id="layout-wrapper">
        @include('admin.partials.topbar')
        @include('admin.partials.sidebar')
        <div class="main-content">
            <main class="page-content" id="main-content" tabindex="-1">
                <div class="container-fluid">
                    @include('admin.partials.page-title')
                    @include('admin.partials.feedback')
                    @yield('content')
                </div>
            </main>
            @include('admin.partials.footer')
        </div>
    </div>
    <button type="button" class="btn btn-danger btn-icon" id="back-to-top" aria-label="العودة إلى أعلى الصفحة"><i class="ri-arrow-up-line"></i></button>
@else
    @yield('content')
@endauth
<script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ asset('assets/js/pages/plugins/lord-icon-2.1.0.js') }}"></script>
@stack('scripts')
</body>
</html>
