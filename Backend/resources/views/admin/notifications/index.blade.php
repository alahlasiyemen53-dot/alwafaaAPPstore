@extends('layouts.admin')

@section('title', 'عمليات وسجل الإشعارات | لمّة')
@section('page-title', 'عمليات وسجل الإشعارات الفورية (FCM Notifications)')

@section('content')
<div class="row">
    <!-- Card 1: Total Notifications -->
    <div class="col-xl col-md-4 col-sm-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">إجمالي الإشعارات</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-1">{{ number_format($metrics['total']) }}</h4>
                        <span class="text-muted fs-13">سجل جميع الإشعارات</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded fs-3">
                            <i class="ri-notification-3-line text-primary"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Sent Successfully -->
    <div class="col-xl col-md-4 col-sm-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">تم الإرسال بنجاح</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge bg-success-subtle text-success fs-12">
                            <i class="ri-checkbox-circle-line align-middle me-1"></i>ناجح
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-1 text-success">{{ number_format($metrics['sent']) }}</h4>
                        <span class="text-muted fs-13">تم تسليمها للمزود</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded fs-3">
                            <i class="ri-check-double-line text-success"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Open Rate / CTR (معدل القراءة والتفاعل) -->
    <div class="col-xl col-md-4 col-sm-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">معدل الفتح والقراءة (CTR)</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge bg-info-subtle text-info fs-12">
                            <i class="ri-cursor-line align-middle me-1"></i>تفاعل
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-1 text-info">{{ $metrics['open_rate'] }}%</h4>
                        <span class="text-muted fs-13">{{ number_format($metrics['read_count']) }} إشعار مفتوح ومقروء</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle rounded fs-3">
                            <i class="ri-eye-line text-info"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Queued & Scheduled -->
    <div class="col-xl col-md-6 col-sm-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">قيد الانتظار والجدولة</p>
                    </div>
                    @if($metrics['scheduled_count'] > 0)
                        <div class="flex-shrink-0">
                            <span class="badge bg-primary-subtle text-primary fs-12">
                                {{ $metrics['scheduled_count'] }} مجدول
                            </span>
                        </div>
                    @endif
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-1 text-warning">{{ number_format($metrics['queued']) }}</h4>
                        <span class="text-muted fs-13">في طابور الإرسال (Queue)</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle rounded fs-3">
                            <i class="ri-time-line text-warning"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 5: Failed & Invalidated Devices -->
    <div class="col-xl col-md-6 col-sm-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0">فشل الإرسال / أجهزة ملغاة</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-1">
                            <span class="text-danger">{{ number_format($metrics['failed']) }}</span>
                            /
                            <span class="text-secondary">{{ number_format($metrics['invalid_devices']) }}</span>
                        </h4>
                        <span class="text-muted fs-13">إشعار فاشل / جهاز ملغى</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle rounded fs-3">
                            <i class="ri-error-warning-line text-danger"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Health & Status Banner -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-xs">
                        <span class="avatar-title bg-soft-info text-info rounded-circle">
                            <i class="ri-server-line"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-0 fs-14">حالة البنية التحتية للإشعارات (FCM Service)</h6>
                        <small class="text-muted">
                            المزود: Firebase Cloud Messaging (HTTP v1 / Kreait) &bull;
                            طابور المهام: <code>{{ $health['queue_connection'] }}</code>
                            / قائمة <code>notifications</code>
                        </small>
                        <div class="text-muted fs-12 mt-1">
                            للإرسال الفعلي شغّل:
                            <code>php artisan queue:work --queue=notifications</code>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($health['enabled'])
                        <span class="badge bg-success-subtle text-success fs-12 px-2 py-1"><i class="ri-checkbox-circle-fill me-1"></i>خدمة FCM مفعلة</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary fs-12 px-2 py-1"><i class="ri-pause-circle-line me-1"></i>FCM معطل تجريبياً (Mock/Log Mode)</span>
                    @endif

                    @if($health['has_credentials'])
                        <span class="badge bg-primary-subtle text-primary fs-12 px-2 py-1"><i class="ri-key-line me-1"></i>بيانات الاعتماد مهيأة</span>
                    @else
                        <span class="badge bg-warning-subtle text-warning fs-12 px-2 py-1"><i class="ri-alert-line me-1"></i>مسار بيانات الاعتماد غير مهيأ</span>
                    @endif

                    @if($health['sender_ready'])
                        <span class="badge bg-success-subtle text-success fs-12 px-2 py-1"><i class="ri-shield-check-line me-1"></i>مرسل FCM جاهز</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1"><i class="ri-error-warning-line me-1"></i>مرسل FCM غير جاهز</span>
                    @endif

                    @if(auth()->user()->hasPermission('admin.notifications.send_test') || auth()->user()->hasPermission('admin.notifications.manage'))
                        <a href="{{ route('admin.notifications.compose') }}" class="btn btn-sm btn-primary ms-2">
                            <i class="ri-send-plane-line me-1"></i>إنشاء وإرسال إشعار
                        </a>
                    @endif
                </div>
            </div>
            @if($health['enabled'] && ! $health['sender_ready'] && ! empty($health['configuration_error']))
                <div class="alert alert-danger mb-0 mt-3 fs-12">
                    <strong>سبب تعطيل الإرسال:</strong> {{ $health['configuration_error'] }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom-dashed">
                <h5 class="card-title mb-0">تصفية وبحث الإشعارات</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.notifications.index') }}" method="GET" class="row g-3 filter-row">
                    <div class="col-md-3">
                        <label for="search" class="form-label fs-13">بحث نصي</label>
                        <input type="text" class="form-control form-control-sm" id="search" name="search"
                               placeholder="الرقم المرجعي، العنوان، مفتاح المطابقة..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label fs-13">نوع الإشعار</label>
                        <select class="form-select form-select-sm" id="type" name="type">
                            <option value="">جميع الأنواع</option>
                            @foreach(\App\Enums\NotificationType::cases() as $typeCase)
                                <option value="{{ $typeCase->value }}" {{ ($filters['type'] ?? '') === $typeCase->value ? 'selected' : '' }}>
                                    {{ $typeCase->value }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label fs-13">الحالة</label>
                        <select class="form-select form-select-sm" id="status" name="status">
                            <option value="">جميع الحالات</option>
                            @foreach(\App\Enums\NotificationStatus::cases() as $statusCase)
                                <option value="{{ $statusCase->value }}" {{ ($filters['status'] ?? '') === $statusCase->value ? 'selected' : '' }}>
                                    {{ $statusCase->value }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="from_date" class="form-label fs-13">من تاريخ</label>
                        <input type="date" class="form-control form-control-sm" id="from_date" name="from_date"
                               value="{{ $filters['from_date'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label for="to_date" class="form-label fs-13">إلى تاريخ</label>
                        <input type="date" class="form-control form-control-sm" id="to_date" name="to_date"
                               value="{{ $filters['to_date'] ?? '' }}">
                    </div>
                    <div class="col-md-1 d-flex align-items-end gap-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100" title="تصفية">
                            <i class="ri-filter-3-line"></i>
                        </button>
                        <a href="{{ route('admin.notifications.index') }}" class="btn btn-sm btn-light w-100" title="إعادة ضبط">
                            <i class="ri-refresh-line"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Notifications Table Card -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header align-items-center d-flex justify-content-between">
                <h4 class="card-title mb-0">سجل الإشعارات المجدولة والمرسلة</h4>
                <span class="text-muted fs-13">عرض {{ $notifications->count() }} من أصل {{ $notifications->total() }} إشعار</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">المرجع</th>
                                <th scope="col">المستلم</th>
                                <th scope="col">النوع / القناة</th>
                                <th scope="col">العنوان والمحتوى</th>
                                <th scope="col">حالة الإشعار</th>
                                <th scope="col">تسليم الأجهزة</th>
                                <th scope="col">وقت الجدولة</th>
                                <th scope="col" class="text-end">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notifications as $item)
                                <tr>
                                    <td>{{ $item->id }}</td>
                                    <td>
                                        <a href="{{ route('admin.notifications.show', $item->id) }}" class="fw-medium text-primary">
                                            {{ $item->business_reference }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($item->recipient)
                                            <div>{{ $item->recipient->name }}</div>
                                            <small class="text-muted">{{ $item->recipient->phone }}</small>
                                        @else
                                            <span class="text-muted fs-12">مستلم غير محدد</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info fs-11">{{ $item->type->value }}</span>
                                        <br>
                                        <small class="text-muted">{{ $item->channel->value }}</small>
                                    </td>
                                    <td style="max-width: 250px;">
                                        <div class="fw-semibold text-truncate">{{ $item->title }}</div>
                                        <small class="text-muted text-truncate d-block">{{ $item->body }}</small>
                                    </td>
                                    <td>
                                        @if($item->scheduled_at && $item->status === \App\Enums\NotificationStatus::Queued)
                                            <span class="badge bg-primary-subtle text-primary fs-12">
                                                <i class="ri-calendar-schedule-line align-middle me-1"></i>مجدول
                                            </span>
                                        @else
                                            @switch($item->status)
                                                @case(\App\Enums\NotificationStatus::Sent)
                                                    <span class="badge bg-success-subtle text-success fs-12">تم الإرسال</span>
                                                    @break
                                                @case(\App\Enums\NotificationStatus::Queued)
                                                    <span class="badge bg-warning-subtle text-warning fs-12">في الطابور</span>
                                                    @break
                                                @case(\App\Enums\NotificationStatus::Failed)
                                                    <span class="badge bg-danger-subtle text-danger fs-12">فشل</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-secondary-subtle text-secondary fs-12">{{ $item->status->value }}</span>
                                            @endswitch
                                        @endif

                                        @if($item->read_at)
                                            <span class="badge bg-info-subtle text-info fs-11 mt-1 d-block" title="تم فتح الإشعار وتفاعله">
                                                <i class="ri-eye-line me-1"></i>تمت القراءة
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $totalDeliveries = $item->deliveries->count();
                                            $sentDeliveries = $item->deliveries->where('status', \App\Enums\NotificationStatus::Sent)->count();
                                        @endphp
                                        @if($totalDeliveries > 0)
                                            <span class="badge bg-light text-dark border">
                                                {{ $sentDeliveries }} / {{ $totalDeliveries }} جهاز
                                            </span>
                                        @else
                                            <span class="text-muted fs-12">لا توجد أجهزة</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->scheduled_at)
                                            <div class="text-primary fw-medium">
                                                <i class="ri-calendar-event-line me-1"></i>{{ $item->scheduled_at->format('Y-m-d H:i') }}
                                            </div>
                                            <small class="text-muted">موعد مجدول</small>
                                        @else
                                            <div>{{ $item->queued_at ? $item->queued_at->format('Y-m-d H:i') : $item->created_at->format('Y-m-d H:i') }}</div>
                                            @if($item->sent_at)
                                                <small class="text-success">أُرسل: {{ $item->sent_at->format('H:i:s') }}</small>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('admin.notifications.show', $item->id) }}" class="btn btn-sm btn-soft-primary" title="عرض التفاصيل">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @if($item->status === \App\Enums\NotificationStatus::Failed)
                                                @if(auth()->user()->hasPermission('admin.notifications.retry'))
                                                    <form action="{{ route('admin.notifications.retry', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد إعادة محاولة إرسال هذا الإشعار؟');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-soft-warning" title="إعادة المحاولة">
                                                            <i class="ri-restart-line"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                                        لا توجد إشعارات تطابق معايير البحث
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($notifications->hasPages())
                <div class="card-footer py-2">
                    <div class="d-flex justify-content-center">
                        {{ $notifications->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
