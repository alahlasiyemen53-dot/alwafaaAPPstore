@extends('layouts.admin')
@section('title', 'الأمان والجلسات | لمّة')
@section('page-title', 'الأمان والجلسات')
@section('content')
<div class="page-heading">
    <div>
        <h2 class="fs-18 mb-1">الأمان والجلسات</h2>
        <p class="text-muted mb-0">عرض جلسات تسجيل الدخول النشطة وإنهاء الأجهزة الأخرى عند الحاجة.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light" href="{{ route('admin.profile.edit') }}"><i class="ri-user-line me-1"></i> الملف الشخصي</a>
        @if(auth()->user()->hasPermission('admin.password.change'))
            <a class="btn btn-soft-primary" href="{{ route('admin.password.edit') }}"><i class="ri-lock-password-line me-1"></i> تغيير كلمة المرور</a>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="fs-15 mb-1">الجلسات الأخرى</h3>
            <p class="text-muted fs-12 mb-0">إنهاء كل الجلسات ما عدا جلستك الحالية.</p>
        </div>
        @if(auth()->user()->hasPermission('admin.security.sessions.manage'))
            <form method="POST" action="{{ route('admin.security.sessions.destroy-others') }}" data-confirm="سيتم تسجيل الخروج من كل الأجهزة الأخرى. هل تريد المتابعة؟">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-soft-danger"><i class="ri-logout-box-r-line me-1"></i> إنهاء الجلسات الأخرى</button>
            </form>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الجهاز / المتصفح</th>
                        <th>عنوان IP</th>
                        <th>آخر نشاط</th>
                        <th>الحالة</th>
                        <th class="text-end">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr>
                            <td>{{ $session['device'] }}</td>
                            <td dir="ltr">{{ $session['ip_address'] ?: '—' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($session['last_activity'])->diffForHumans() }}</td>
                            <td>
                                @if($session['is_current'])
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">الجلسة الحالية</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">جلسة أخرى</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(! $session['is_current'] && auth()->user()->hasPermission('admin.security.sessions.manage'))
                                    <form method="POST" action="{{ route('admin.security.sessions.destroy', $session['id']) }}" class="d-inline" data-confirm="إنهاء هذه الجلسة؟">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-soft-danger"><i class="ri-close-circle-line"></i> إنهاء</button>
                                    </form>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">لا توجد جلسات مسجّلة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
