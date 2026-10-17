@extends('layouts.admin')
@section('title', 'سجلات رموز التحقق (OTP) | الوفاء استور')
@section('page-title', 'سجلات وتحديات رموز التحقق (OTP)')

@section('content')
<!-- Metric Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-12">إجمالي طلبات OTP</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2">{{ number_format($stats['total']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                            <i class="ri-shield-keyhole-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-12">رموز نشطة قيد الانتظار</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-warning">{{ number_format($stats['pending']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle text-warning rounded fs-3">
                            <i class="ri-time-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-12">تم التحقق بنجاح</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-success">{{ number_format($stats['verified']) }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                            <i class="ri-checkbox-circle-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-12">محاولات مستنفدة / محظورة</p>
                        <h4 class="fs-22 fw-semibold mb-0 mt-2 text-danger">{{ number_format($stats['exhausted']) }}</h4>
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

<div class="alert alert-info border-info-subtle d-flex align-items-center mb-4">
    <i class="ri-shield-check-line fs-20 me-2 flex-shrink-0"></i>
    <div class="fs-13">
        <strong>شاشة تشخيصية آمنة (Read-Only Diagnostics):</strong> لأغراض الأمان والامتثال لسياسات الخصوصية، يتم قناع أرقام الهواتف جزئياً، ولا يتم حفظ أو عرض رموز التحقق الصريحة (Plaintext OTP) أو التجزئة المشفرة تحت أي ظرف.
    </div>
</div>

<!-- Filters & Table Card -->
<div class="card">
    <div class="card-header border-0">
        <form method="GET" action="{{ route('admin.otp-challenges.index') }}" class="row g-3 align-items-end filter-row">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="search-box">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="بحث برقم الهاتف أو معرّف التحدي..." 
                               value="{{ request('search') }}">
                        <i class="ri-search-line search-icon"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">كافة الحالات</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="purpose" class="form-select">
                        <option value="">كافة الأغراض</option>
                        @foreach($purposes as $purpose)
                            <option value="{{ $purpose->value }}" @selected(request('purpose') === $purpose->value)>{{ $purpose->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="ri-filter-line me-1"></i> تصفية</button>
                    <a href="{{ route('admin.otp-challenges.index') }}" class="btn btn-light" title="إعادة تعيين"><i class="ri-refresh-line"></i></a>
                </div>
            </div>
        </form>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive table-card mb-1">
            <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light">
                    <tr>
                        <th>معرّف التحدي (Challenge ID)</th>
                        <th>رقم الهاتف المقنع</th>
                        <th>الغرض</th>
                        <th>الحالة</th>
                        <th>المحاولات</th>
                        <th>تاريخ الإرسال</th>
                        <th>الصلاحية</th>
                        <th>تاريخ الاستخدام</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $challenge)
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark font-monospace fs-11" title="{{ $challenge->challenge_id }}">
                                    {{ substr($challenge->challenge_id, 0, 8) }}...{{ substr($challenge->challenge_id, -4) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold font-monospace" dir="ltr">{{ $challenge->masked_phone }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $challenge->purpose->badgeClass() }}">
                                    <i class="{{ $challenge->purpose->icon() }} me-1"></i>{{ $challenge->purpose->label() }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $challenge->status->badgeClass() }}">
                                    <i class="{{ $challenge->status->icon() }} me-1"></i>{{ $challenge->status->label() }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $challenge->attempts >= $challenge->max_attempts ? 'bg-danger text-white' : 'bg-light text-dark' }}">
                                    {{ $challenge->attempts }} / {{ $challenge->max_attempts }}
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">{{ $challenge->last_sent_at?->format('Y-m-d H:i:s') ?? '-' }}</small>
                            </td>
                            <td>
                                @if($challenge->isExpired())
                                    <span class="badge bg-secondary-subtle text-secondary fs-11">منتهي</span>
                                @else
                                    <small class="text-muted">{{ $challenge->expires_at?->format('Y-m-d H:i:s') ?? '-' }}</small>
                                @endif
                            </td>
                            <td>
                                @if($challenge->consumed_at)
                                    <small class="text-success"><i class="ri-check-line me-1"></i>{{ $challenge->consumed_at->format('Y-m-d H:i:s') }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                                لا توجد سجلات لرموز التحقق مطابقة لشروط البحث.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end mt-3">
            {{ $records->links() }}
        </div>
    </div>
</div>
@endsection
