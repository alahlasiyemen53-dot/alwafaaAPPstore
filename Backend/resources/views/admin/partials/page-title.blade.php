@hasSection('page-title')
<div class="row"><div class="col-12"><div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent"><h1 class="mb-sm-0 fs-16">@yield('page-title')</h1><ol class="breadcrumb m-0" aria-label="مسار الصفحة"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li><li class="breadcrumb-item active" aria-current="page">@yield('page-title')</li></ol></div></div></div>
@endif
