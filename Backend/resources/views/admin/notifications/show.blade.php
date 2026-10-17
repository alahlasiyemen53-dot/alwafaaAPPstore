@extends('layouts.admin')

@section('title', 'تفاصيل الإشعار ' . $notification->business_reference . ' | الوفاء استور')
@section('page-title', 'تفاصيل الإشعار وعمليات التسليم')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="{{ route('admin.notifications.index') }}" class="btn btn-sm btn-light">
                    <i class="ri-arrow-right-line me-1"></i>العودة لسجل الإشعارات
                </a>
            </div>
            <div class="d-flex gap-2">
                @if($notification->status === \App\Enums\NotificationStatus::Failed)
                    @if(auth()->user()->hasPermission('admin.notifications.retry'))
                        <form action="{{ route('admin.notifications.retry', $notification->id) }}" method="POST" onsubmit="return confirm('هل تريد إعادة محاولة إرسال هذا الإشعار؟');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-warning">
                                <i class="ri-restart-line me-1"></i>إعادة المحاولة
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Notification Meta & Content -->
    <div class="col-xl-6">
        <!-- Main Card -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">بيانات الإشعار الأساسية</h5>
                <div>
                    @switch($notification->status)
                        @case(\App\Enums\NotificationStatus::Sent)
                            <span class="badge bg-success-subtle text-success fs-13 px-2 py-1">تم الإرسال بنجاح</span>
                            @break
                        @case(\App\Enums\NotificationStatus::Queued)
                            <span class="badge bg-warning-subtle text-warning fs-13 px-2 py-1">في طابور المعالجة</span>
                            @break
                        @case(\App\Enums\NotificationStatus::Failed)
                            <span class="badge bg-danger-subtle text-danger fs-13 px-2 py-1">فشل الإرسال</span>
                            @break
                        @default
                            <span class="badge bg-secondary-subtle text-secondary fs-13 px-2 py-1">{{ $notification->status->value }}</span>
                    @endswitch
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <th class="text-muted" style="width: 35%;">الرقم المرجعي:</th>
                                <td><strong class="text-primary">{{ $notification->business_reference }}</strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted">النوع (Type):</th>
                                <td><span class="badge bg-info-subtle text-info">{{ $notification->type->value }}</span></td>
                            </tr>
                            <tr>
                                <th class="text-muted">التصنيف (Category):</th>
                                <td>{{ $notification->category->value }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">القناة (Channel):</th>
                                <td>{{ $notification->channel->value }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">مفتاح المطابقة (Idempotency):</th>
                                <td><code class="fs-12">{{ $notification->idempotency_key }}</code></td>
                            </tr>
                            <tr>
                                <th class="text-muted">المستلم:</th>
                                <td>
                                    @if($notification->recipient)
                                        <div class="fw-semibold">{{ $notification->recipient->name }}</div>
                                        <small class="text-muted">{{ $notification->recipient->phone }}</small>
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </td>
                            </tr>
                            @if($notification->scheduled_at)
                                <tr>
                                    <th class="text-primary">الموعد المجدول للإرسال:</th>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary fs-12">
                                            <i class="ri-calendar-schedule-line me-1"></i>{{ $notification->scheduled_at->format('Y-m-d H:i:s') }}
                                        </span>
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <th class="text-muted">وقت الإدراج بالطابور:</th>
                                <td>{{ $notification->queued_at ? $notification->queued_at->format('Y-m-d H:i:s') : '—' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">وقت الإرسال الفعلي:</th>
                                <td>{{ $notification->sent_at ? $notification->sent_at->format('Y-m-d H:i:s') : '—' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">حالة فتح وتفاعل الإشعار:</th>
                                <td>
                                    @if($notification->read_at)
                                        <span class="badge bg-info-subtle text-info fs-12">
                                            <i class="ri-eye-line me-1"></i>تمت القراءة: {{ $notification->read_at->format('Y-m-d H:i:s') }}
                                        </span>
                                    @else
                                        <span class="text-muted fs-12">غير مقروء حتى الآن</span>
                                    @endif
                                </td>
                            </tr>
                            @if($notification->failed_at)
                                <tr>
                                    <th class="text-danger">وقت الفشل:</th>
                                    <td class="text-danger">{{ $notification->failed_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @endif
                            @if($notification->failure_category || $notification->failure_summary)
                                <tr>
                                    <th class="text-danger">سبب الفشل:</th>
                                    <td>
                                        @if($notification->failure_category)
                                            <span class="badge bg-danger-subtle text-danger mb-1">{{ $notification->failure_category->label() }}</span>
                                            <div class="text-muted fs-11">{{ $notification->failure_category->value }}</div>
                                        @endif
                                        @if($notification->failure_summary)
                                            <div class="text-danger mt-1">{{ $notification->failure_summary }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @elseif($notification->status === \App\Enums\NotificationStatus::Failed)
                                @php
                                    $latestFailedDelivery = $notification->deliveries
                                        ->where('status', \App\Enums\NotificationStatus::Failed)
                                        ->sortByDesc('id')
                                        ->first();
                                @endphp
                                @if($latestFailedDelivery?->failure_category || $latestFailedDelivery?->failure_summary)
                                    <tr>
                                        <th class="text-danger">سبب الفشل:</th>
                                        <td>
                                            @if($latestFailedDelivery->failure_category)
                                                <span class="badge bg-danger-subtle text-danger mb-1">{{ $latestFailedDelivery->failure_category->label() }}</span>
                                                <div class="text-muted fs-11">{{ $latestFailedDelivery->failure_category->value }}</div>
                                            @endif
                                            @if($latestFailedDelivery->failure_summary)
                                                <div class="text-danger mt-1">{{ $latestFailedDelivery->failure_summary }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Content Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">محتوى ونص الإشعار</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted fs-12 mb-1">العنوان (Title)</label>
                    <div class="p-2 bg-light rounded fw-bold">{{ $notification->title }}</div>
                </div>
                <div class="mb-3">
                    <label class="text-muted fs-12 mb-1">النص (Body)</label>
                    <div class="p-2 bg-light rounded">{{ $notification->body }}</div>
                </div>
                <div>
                    <label class="text-muted fs-12 mb-1">الحمولة الإضافية (Data Payload - Version {{ $notification->payload_version }})</label>
                    <pre class="bg-dark text-light p-3 rounded fs-12 mb-0" style="max-height: 200px; overflow-y: auto;"><code>{{ json_encode($notification->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Deliveries & Attempts Timeline -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">الأجهزة المستهدفة وسجل التسليم</h5>
                <span class="badge bg-light text-dark border">{{ $notification->deliveries->count() }} جهاز مستهدف</span>
            </div>
            <div class="card-body">
                @forelse($notification->deliveries as $delivery)
                    <div class="border rounded p-3 mb-3 bg-light-subtle">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar-xs">
                                    <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                                        @if($delivery->pushDevice?->platform->value === 'ios')
                                            <i class="ri-apple-fill"></i>
                                        @else
                                            <i class="ri-android-fill"></i>
                                        @endif
                                    </span>
                                </span>
                                <div>
                                    <h6 class="mb-0 fs-13">{{ $delivery->pushDevice?->display_label ?: 'جهاز غير مسمى' }}</h6>
                                    <small class="text-muted">
                                        {{ $delivery->pushDevice?->platform?->value ?? 'unknown' }} &bull;
                                        {{ filled($delivery->pushDevice?->device_identifier) ? $delivery->pushDevice->device_identifier : ('#'.$delivery->pushDevice?->id) }} &bull;
                                        تطبيق: v{{ $delivery->pushDevice?->app_version ?: '—' }}
                                    </small>
                                </div>
                            </div>
                            <div>
                                @switch($delivery->status)
                                    @case(\App\Enums\NotificationStatus::Sent)
                                        <span class="badge bg-success-subtle text-success fs-12">ناجح</span>
                                        @break
                                    @case(\App\Enums\NotificationStatus::Failed)
                                        <span class="badge bg-danger-subtle text-danger fs-12">فشل</span>
                                        @break
                                    @case(\App\Enums\NotificationStatus::Pending)
                                        <span class="badge bg-warning-subtle text-warning fs-12">قيد التنفيذ</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary-subtle text-secondary fs-12">{{ $delivery->status->value }}</span>
                                @endswitch
                            </div>
                        </div>

                        <div class="mb-2 fs-12 text-muted">
                            <strong>التوكن (مقنع أمنياً):</strong>
                            <code>{{ \App\Services\Notifications\TokenMasker::mask($delivery->pushDevice?->token) }}</code>
                            &bull; <strong>حالة الجهاز:</strong>
                            <span class="badge {{ $delivery->pushDevice?->status->value === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} fs-11">
                                {{ $delivery->pushDevice?->status->value }}
                            </span>
                        </div>

                        @if($delivery->provider_reference)
                            <div class="mb-2 fs-12 text-muted">
                                <strong>معرف رسالة المزود (FCM Message ID):</strong>
                                <code class="text-dark">{{ $delivery->provider_reference }}</code>
                            </div>
                        @endif

                        @if($delivery->status === \App\Enums\NotificationStatus::Failed && ($delivery->failure_category || $delivery->failure_summary))
                            <div class="alert alert-danger py-2 px-3 mb-2 fs-12">
                                @if($delivery->failure_category)
                                    <div class="fw-semibold mb-1">{{ $delivery->failure_category->label() }}
                                        <span class="text-muted fw-normal">({{ $delivery->failure_category->value }})</span>
                                    </div>
                                @endif
                                @if($delivery->failure_summary)
                                    <div>{{ $delivery->failure_summary }}</div>
                                @endif
                                @if($delivery->failed_at)
                                    <div class="text-muted mt-1">وقت الفشل: {{ $delivery->failed_at->format('Y-m-d H:i:s') }}</div>
                                @endif
                            </div>
                        @endif

                        <!-- Attempts History Table -->
                        <div class="mt-3">
                            <h6 class="fs-12 text-uppercase fw-bold text-muted mb-2">محاولات الإرسال ({{ $delivery->attempts->count() }})</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white fs-12 mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>الوقت</th>
                                            <th>الحالة</th>
                                            <th>تصنيف الخطأ</th>
                                            <th>سبب الفشل / المرجع</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($delivery->attempts as $attempt)
                                            <tr>
                                                <td>{{ $attempt->attempt_number }}</td>
                                                <td>{{ $attempt->attempted_at ? $attempt->attempted_at->format('Y-m-d H:i:s') : '—' }}</td>
                                                <td>
                                                    @if($attempt->status->value === 'sent')
                                                        <span class="text-success"><i class="ri-checkbox-circle-line me-1"></i>نجاح</span>
                                                    @else
                                                        <span class="text-danger"><i class="ri-close-circle-line me-1"></i>فشل</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($attempt->failure_category)
                                                        <span class="badge bg-danger-subtle text-danger">{{ $attempt->failure_category->label() }}</span>
                                                        <div class="text-muted fs-11 mt-1">{{ $attempt->failure_category->value }}</div>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($attempt->failure_summary)
                                                        <span class="text-danger">{{ $attempt->failure_summary }}</span>
                                                    @elseif($attempt->provider_reference)
                                                        <span class="text-success"><code>{{ $attempt->provider_reference }}</code></span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-2">لا توجد محاولات مسجلة بعد</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4 text-muted">
                        <i class="ri-smartphone-line fs-24 d-block mb-1"></i>
                        <div class="fw-semibold mb-1">لا توجد أجهزة مستهدفة لهذا الإشعار</div>
                        <div class="fs-12">
                            العميل لا يملك جهازاً نشطاً في جدول
                            <code>push_devices</code>
                            (التطبيق لم يُسجّل توكن FCM بعد الدخول).
                        </div>
                        @if($notification->failure_summary)
                            <div class="alert alert-warning mt-3 mb-0 fs-12 text-start">
                                <strong>سبب الفشل:</strong> {{ $notification->failure_summary }}
                            </div>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
