@extends('layouts.admin')
@section('title', 'محادثات الدعم | لمّة')
@section('page-title', 'محادثات الدعم المباشر')
@section('content')
@php
    $filters = [
        '' => ['label' => 'الكل', 'badge' => $tabCounts[''] ?? null, 'color' => 'secondary'],
        'unread' => ['label' => 'غير مقروء', 'badge' => $tabCounts['unread'] ?? null, 'color' => 'danger'],
        'mine' => ['label' => 'مسند لي', 'badge' => $tabCounts['mine'] ?? null, 'color' => 'primary'],
        'closed' => ['label' => 'مغلقة', 'badge' => $tabCounts['closed'] ?? null, 'color' => 'light text-muted'],
    ];
@endphp
<div
    id="support-workspace"
    class="support-workspace"
    data-filter="{{ $filter }}"
    data-search="{{ $search }}"
    data-selected-uuid="{{ $selectedUuid }}"
    data-live-index-url="{{ route('admin.support-chat.live-index') }}"
    data-unread-url="{{ route('admin.support-chat.unread-count') }}"
    data-firebase-token-url="{{ route('admin.support-chat.firebase-token') }}"
    data-reply-url-template="{{ url('/admin/support-chat/__UUID__/reply') }}"
    data-assign-url-template="{{ url('/admin/support-chat/__UUID__/assign') }}"
    data-close-url-template="{{ url('/admin/support-chat/__UUID__/close') }}"
    data-reopen-url-template="{{ url('/admin/support-chat/__UUID__/reopen') }}"
    data-messages-url-template="{{ url('/admin/support-chat/__UUID__/live') }}"
    data-fb-api-key="{{ $firebaseWeb['apiKey'] }}"
    data-fb-auth-domain="{{ $firebaseWeb['authDomain'] }}"
    data-fb-project-id="{{ $firebaseWeb['projectId'] }}"
    data-fb-storage-bucket="{{ $firebaseWeb['storageBucket'] }}"
    data-fb-sender-id="{{ $firebaseWeb['messagingSenderId'] }}"
    data-fb-app-id="{{ $firebaseWeb['appId'] }}"
>
<script type="application/json" id="support-bootstrap">@json($bootstrapJson, JSON_UNESCAPED_UNICODE)</script>

    <!-- Top Status Bar -->
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="support-live-dot" id="support-live-dot"></span>
                <span class="badge bg-success-subtle text-success fs-12 px-2 py-1" id="support-live-badge">جاري الاتصال…</span>
                <span
                    class="badge bg-danger-subtle text-danger fs-12{{ ($unreadCount ?? 0) > 0 ? '' : ' d-none' }}"
                    data-support-unread-badge
                ><i class="ri-mail-unread-line me-1"></i> {{ $unreadCount }} غير مقروء</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="form-check form-switch mb-0 d-flex align-items-center gap-1">
                    <input class="form-check-input" type="checkbox" id="support-sound-toggle" checked>
                    <label class="form-check-label fs-12 cursor-pointer" for="support-sound-toggle">
                        <i class="ri-volume-up-line text-primary me-1"></i> صوت التنبيه
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- 3-Pane Chat Shell -->
    <div class="support-shell" id="support-shell">
        <!-- 1. Left Sidebar: Conversations List -->
        <aside class="support-list-pane">
            <div class="support-list-toolbar">
                <div class="support-filter-tabs" role="tablist">
                    @foreach($filters as $key => $info)
                        <button
                            type="button"
                            class="btn btn-sm {{ ($filter ?: '') === $key ? 'btn-primary active' : 'btn-soft-secondary' }}"
                            data-support-filter="{{ $key }}"
                        >
                            <span>{{ $info['label'] }}</span>
                            @if(!is_null($info['badge']) && $info['badge'] > 0)
                                <span class="badge rounded-pill bg-{{ $info['color'] }} fs-10 px-1 py-0" data-filter-count="{{ $key }}">{{ $info['badge'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
                <div class="position-relative">
                    <input
                        type="search"
                        id="support-search"
                        class="form-control form-control-sm ps-4"
                        value="{{ $search }}"
                        placeholder="بحث بالاسم أو الهاتف أو الرسالة..."
                    >
                    <i class="ri-search-line position-absolute top-50 start-0 translate-middle-y ms-2 text-muted fs-13"></i>
                </div>
            </div>
            <div class="support-list-scroll" id="support-list"></div>
        </aside>

        <!-- 2. Middle Pane: Active Conversation & Messages -->
        <section class="support-thread-pane">
            <div id="support-thread-empty" class="d-flex flex-column align-items-center justify-content-center text-muted h-100 p-5">
                <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mb-3 text-primary">
                    <i class="ri-chat-smile-2-line fs-36"></i>
                </div>
                <h5 class="fs-15 text-dark fw-semibold mb-1">اختر محادثة لبدء الدردشة</h5>
                <p class="fs-12 text-muted text-center" style="max-width: 300px;">
                    انقر على أي عميل من القائمة الجانبية لعرض تاريخ الرسائل، وتفاصيل الطلب، ومتابعة الدعم الفني المباشر.
                </p>
            </div>

            <div id="support-thread-pane" class="d-none flex-column h-100" style="display:none">
                <!-- Thread Header -->
                <div class="support-thread-header">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <div class="avatar-sm bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-14" id="support-thread-avatar">
                            ع
                        </div>
                        <div class="min-w-0">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="fs-14 mb-0 text-truncate fw-bold text-dark" id="support-thread-title">محادثة</h5>
                                <span class="badge" id="support-thread-status"></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 text-muted fs-11 mt-1 flex-wrap" id="support-thread-meta"></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-subtle-primary" id="support-btn-assign" title="إسناد المحادثة إليك">
                            <i class="ri-user-shared-line me-1"></i> إسناد لي
                        </button>
                        <button type="button" class="btn btn-sm btn-subtle-danger" id="support-btn-close" title="إغلاق تذكرة المحادثة">
                            <i class="ri-check-double-line me-1"></i> إغلاق
                        </button>
                        <button type="button" class="btn btn-sm btn-subtle-success d-none" id="support-btn-reopen" title="إعادة فتح المحادثة">
                            <i class="ri-refresh-line me-1"></i> إعادة فتح
                        </button>
                        <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon" id="support-btn-toggle-info" title="تفاصيل العميل والطلب">
                            <i class="ri-layout-right-line fs-16"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages Stream -->
                <div class="support-messages" id="support-messages"></div>

                <!-- Canned Responses / Quick Replies Bar -->
                <div class="support-canned-bar" id="support-canned-bar">
                    <span class="text-muted fs-11 align-self-center me-1"><i class="ri-flashlight-line text-warning"></i> ردود سريعة:</span>
                    @foreach($quickReplies ?? [] as $reply)
                        <button type="button" class="support-canned-chip" data-quick-text="{{ $reply }}">
                            {{ Str::limit($reply, 32) }}
                        </button>
                    @endforeach
                </div>

                <!-- Composer -->
                <form class="support-composer" id="support-composer" enctype="multipart/form-data">
                    <div id="support-reply-image-preview" class="d-none mb-2">
                        <div class="d-inline-block position-relative border rounded p-1 bg-light">
                            <img src="" alt="معاينة المرفق" id="support-reply-image-preview-img" class="rounded support-reply-preview">
                            <button type="button" class="btn btn-xs btn-danger position-absolute top-0 end-0 m-1 rounded-circle" id="support-reply-image-clear" title="حذف الصورة">×</button>
                        </div>
                    </div>
                    <div class="d-flex align-items-end gap-2">
                        <div class="position-relative flex-grow-1">
                            <textarea
                                id="support-reply-body"
                                class="form-control"
                                rows="2"
                                maxlength="4000"
                                placeholder="اكتب رد الدعم هنا… (Enter للإرسال، Shift+Enter لسطر جديد)"
                            ></textarea>
                        </div>
                        <div class="d-flex flex-column gap-1">
                            <label for="support-reply-image" class="btn btn-light btn-icon border mb-0" title="إرفاق صورة">
                                <i class="ri-image-add-line fs-16 text-muted"></i>
                                <input type="file" id="support-reply-image" class="d-none" accept="image/jpeg,image/png,image/webp">
                            </label>
                            <button class="btn btn-primary btn-icon" type="submit" id="support-reply-submit" title="إرسال الرد">
                                <i class="ri-send-plane-2-fill fs-16"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- 3. Right Sidebar: Customer & Order Details Inspector -->
        <aside class="support-info-pane" id="support-info-pane">
            <div class="support-info-header">
                <h6 class="fs-13 fw-bold mb-0 text-dark"><i class="ri-user-star-line text-primary me-1"></i> معلومات العميل والطلب</h6>
                <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon" id="support-btn-close-info" title="إغلاق اللوحة">
                    <i class="ri-close-line fs-16"></i>
                </button>
            </div>
            <div class="support-info-body">
                <!-- Customer Card -->
                <div class="card border shadow-none mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="avatar-sm bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center fw-bold fs-14" id="support-info-avatar">
                                ع
                            </div>
                            <div class="min-w-0">
                                <h6 class="fs-13 fw-bold mb-0 text-truncate text-dark" id="support-info-name">—</h6>
                                <span class="badge bg-light text-muted fs-11" id="support-info-tier">عميل</span>
                            </div>
                        </div>
                        <div class="fs-12 text-muted mb-1 d-flex align-items-center justify-content-between">
                            <span>الهاتف:</span>
                            <span class="fw-semibold text-dark" id="support-info-phone" dir="ltr">—</span>
                        </div>
                        <div class="fs-12 text-muted mb-3 d-flex align-items-center justify-content-between">
                            <span>البريد:</span>
                            <span class="text-truncate text-dark" id="support-info-email">—</span>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="#" target="_blank" class="btn btn-xs btn-subtle-primary flex-grow-1" id="support-info-customer-link">
                                <i class="ri-user-3-line me-1"></i> عرض ملف العميل
                            </a>
                            <a href="#" target="_blank" rel="noopener" class="btn btn-xs btn-subtle-success" id="support-info-whatsapp-link" title="محادثة واتساب">
                                <i class="ri-whatsapp-line fs-14"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Related Order Card (Optional) -->
                <div class="card border shadow-none mb-3 d-none" id="support-info-order-card">
                    <div class="card-header bg-light-subtle py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold fs-12 text-dark"><i class="ri-shopping-bag-3-line text-warning me-1"></i> الطلب المرتبط</span>
                        <span class="badge" id="support-info-order-status"></span>
                    </div>
                    <div class="card-body p-3">
                        <div class="fs-12 text-muted mb-1 d-flex justify-content-between">
                            <span>رقم الطلب:</span>
                            <span class="fw-bold text-dark font-monospace" id="support-info-order-num">—</span>
                        </div>
                        <div class="fs-12 text-muted mb-3 d-flex justify-content-between">
                            <span>الإجمالي:</span>
                            <span class="fw-bold text-success fs-13" id="support-info-order-total">—</span>
                        </div>
                        <a href="#" target="_blank" class="btn btn-xs btn-outline-primary w-100" id="support-info-order-link">
                            <i class="ri-external-link-line me-1"></i> فتح تفاصيل الطلب
                        </a>
                    </div>
                </div>

                <!-- Audit Log Timeline -->
                <div class="card border shadow-none mb-0">
                    <div class="card-header bg-light-subtle py-2">
                        <span class="fw-semibold fs-12 text-dark"><i class="ri-history-line text-info me-1"></i> سجل الإجراءات والتحديثات</span>
                    </div>
                    <div class="card-body p-3">
                        <div id="support-actions-log" class="support-timeline"></div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div class="modal fade" id="supportImagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-white fs-14"><i class="ri-image-line me-1"></i> معاينة الصورة</h5>
                <div class="d-flex gap-2">
                    <a href="" download class="btn btn-sm btn-outline-light" id="support-image-download-btn" title="تنزيل الصورة">
                        <i class="ri-download-2-line"></i> تنزيل
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
            </div>
            <div class="modal-body text-center p-3">
                <img src="" alt="معاينة المرفق" id="support-image-preview" class="img-fluid rounded-3" style="max-height: 75vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore-compat.js"></script>
<script src="{{ asset('assets/js/pages/admin-support-chat.js') }}?v=4"></script>
@endpush
