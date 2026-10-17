<header id="page-topbar">
    <div class="layout-width"><div class="navbar-header">
        <div class="d-flex align-items-center">
            <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger material-shadow-none" id="topnav-hamburger-icon" aria-label="فتح أو إغلاق القائمة" aria-controls="navbar-nav" aria-expanded="false"><span class="hamburger-icon"><span></span><span></span><span></span></span></button>
            <div class="d-none d-md-block ms-3"><span class="text-muted fs-12">مساحة إدارة المتجر</span><div class="fw-semibold">مرحبًا، {{ auth()->user()->name }}</div></div>
        </div>
        <div class="dropdown topbar-head-dropdown ms-1 header-item">
            <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="قائمة الحساب"><span class="avatar-title bg-primary-subtle text-primary rounded-circle">{{ mb_substr(auth()->user()->name, 0, 1) }}</span></button>
            <div class="dropdown-menu dropdown-menu-end">
                <h6 class="dropdown-header">{{ auth()->user()->name }}</h6>
                @if(auth()->user()->hasPermission('admin.profile.view'))<a class="dropdown-item" href="{{ route('admin.profile.edit') }}"><i class="ri-user-line text-muted fs-16 align-middle me-1"></i> الملف الشخصي</a>@endif
                @if(auth()->user()->hasPermission('admin.security.sessions.view'))<a class="dropdown-item" href="{{ route('admin.security.sessions.index') }}"><i class="ri-shield-keyhole-line text-muted fs-16 align-middle me-1"></i> الأمان والجلسات</a>@endif
                @if(auth()->user()->hasPermission('admin.password.change'))<a class="dropdown-item" href="{{ route('admin.password.edit') }}"><i class="ri-lock-password-line text-muted fs-16 align-middle me-1"></i> تغيير كلمة المرور</a>@endif
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="ri-logout-box-r-line fs-16 align-middle me-1"></i> تسجيل الخروج</button></form>
            </div>
        </div>
    </div></div>
</header>
