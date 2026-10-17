@extends('layouts.admin')
@section('title', 'تفاصيل التقييم #' . $review->id . ' | الوفاء استور')
@section('page-title', 'تفاصيل التقييم والمراجعة')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-sm-0">تفاصيل التقييم #{{ $review->id }}</h4>
                <p class="text-muted mb-0">مراجعة مقدّمة للمنتج: <a href="{{ route('admin.products.show', $review->product_id) }}" class="fw-medium">{{ $review->product->name_ar }}</a></p>
            </div>
            <div class="page-title-right d-flex gap-2">
                <a href="{{ route('admin.reviews.index') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-right-line me-1"></i> العودة للقائمة
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Review Content & Customer/Product Details -->
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header align-items-center d-flex justify-content-between">
                <h4 class="card-title mb-0 flex-grow-1">نص المراجعة والتقييم</h4>
                <div>
                    <span class="badge {{ $review->status->badgeClass() }} fs-12 px-3 py-2">
                        {{ $review->status->label() }}
                    </span>
                    @if($review->is_verified_purchase)
                        <span class="badge bg-info-subtle text-info border border-info-subtle fs-12 px-2 py-1 ms-1">
                            <i class="ri-checkbox-circle-fill me-1"></i> شراء مؤكد
                        </span>
                    @else
                        <span class="badge bg-secondary-subtle text-muted fs-12 px-2 py-1 ms-1">
                            شراء غير مؤكد
                        </span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <!-- Rating Score Stars -->
                <div class="d-flex align-items-center mb-4 p-3 bg-light rounded">
                    <div class="me-3">
                        <span class="display-6 fw-bold text-dark">{{ $review->rating }}</span>
                        <span class="text-muted fs-16"> / 5</span>
                    </div>
                    <div>
                        <div class="fs-18 text-warning mb-1">
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= $review->rating)
                                    <i class="ri-star-fill"></i>
                                @else
                                    <i class="ri-star-line text-muted"></i>
                                @endif
                            @endfor
                        </div>
                        <span class="text-muted fs-13">تاريخ الإرسال: {{ $review->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                </div>

                @if($review->title)
                    <div class="mb-3">
                        <h5 class="fw-semibold text-primary">{{ $review->title }}</h5>
                    </div>
                @endif

                <div class="mb-4">
                    <label class="form-label text-muted fs-12">تعليق العميل:</label>
                    <div class="p-3 border rounded bg-white fs-15 text-dark lh-base" style="min-height: 100px; white-space: pre-wrap;">
                        {{ $review->comment ?: 'لا يوجد تعليق مكتوب مع هذا التقييم.' }}
                    </div>
                </div>

                <div class="row g-3 text-muted fs-13 border-top pt-3">
                    <div class="col-sm-6">
                        <i class="ri-calendar-event-line me-1"></i> <strong>تاريخ الإنشاء:</strong> {{ $review->created_at->format('Y-m-d H:i:s') }}
                    </div>
                    <div class="col-sm-6">
                        <i class="ri-time-line me-1"></i> <strong>آخر تحديث:</strong> {{ $review->updated_at->format('Y-m-d H:i:s') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Comments & Admin Replies -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h4 class="card-title mb-0">
                    <i class="ri-chat-3-line me-1 text-primary"></i>
                    التعليقات والردود
                    <span class="badge bg-primary-subtle text-primary ms-1">{{ $review->comments->count() }}</span>
                </h4>
            </div>
            <div class="card-body">
                @if($review->comments->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="ri-chat-off-line fs-24 d-block mb-2"></i>
                        لا توجد تعليقات على هذا التقييم بعد.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>الكاتب</th>
                                    <th>النوع</th>
                                    <th>النص</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th class="text-end">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($review->comments as $comment)
                                    <tr>
                                        <td>{{ $comment->id }}</td>
                                        <td>
                                            @if($comment->isOfficialAdminReply())
                                                <span class="fw-medium">{{ $comment->adminUser?->name ?: 'إدارة الوفاء استور' }}</span>
                                                <div class="text-muted fs-12">{{ $comment->adminUser?->email }}</div>
                                            @else
                                                <span class="fw-medium">{{ $comment->customer?->name ?: 'عميل' }}</span>
                                                <div class="text-muted fs-12" dir="ltr">{{ $comment->customer?->phone }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($comment->isOfficialAdminReply())
                                                <span class="badge bg-primary-subtle text-primary">رد إدارة</span>
                                            @else
                                                <span class="badge bg-info-subtle text-info">تعليق عميل</span>
                                            @endif
                                        </td>
                                        <td style="max-width: 280px; white-space: pre-wrap;">{{ $comment->body }}</td>
                                        <td>
                                            <span class="badge {{ $comment->status->badgeClass() }}">
                                                {{ $comment->status->label() }}
                                            </span>
                                        </td>
                                        <td class="fs-13 text-muted">{{ $comment->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="text-end">
                                            @if(auth()->user()->hasPermission('admin.reviews.moderate'))
                                                @if($comment->status === \App\Enums\ReviewCommentStatus::Approved)
                                                    <form action="{{ route('admin.reviews.comments.moderate', [$review, $comment]) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="hide">
                                                        <button type="submit" class="btn btn-sm btn-soft-warning" onclick="return confirm('إخفاء هذا التعليق؟')">
                                                            إخفاء
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('admin.reviews.comments.moderate', [$review, $comment]) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="restore">
                                                        <button type="submit" class="btn btn-sm btn-soft-success" onclick="return confirm('إظهار هذا التعليق؟')">
                                                            إظهار
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(auth()->user()->hasPermission('admin.reviews.moderate'))
                    <div class="border-top mt-4 pt-3">
                        <h5 class="fs-15 mb-3">الرد باسم الإدارة</h5>
                        <form action="{{ route('admin.reviews.comments.reply', $review) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="admin_reply_body" class="form-label">اكتب رد الإدارة هنا...</label>
                                <textarea
                                    id="admin_reply_body"
                                    name="body"
                                    rows="3"
                                    maxlength="1000"
                                    class="form-control @error('body') is-invalid @enderror"
                                    required
                                >{{ old('body') }}</textarea>
                                @error('body')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-send-plane-line me-1"></i> إرسال الرد
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <!-- Moderation Details / Audit -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="ri-shield-check-line me-1 text-primary"></i> سجل وقرار الإشراف</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <th scope="row" style="width: 200px;">حالة الإشراف الحالية:</th>
                                <td>
                                    <span class="badge {{ $review->status->badgeClass() }} fs-13 px-2 py-1">
                                        {{ $review->status->label() }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">المشرف المسؤول:</th>
                                <td>
                                    @if($review->moderator)
                                        <span class="fw-medium text-dark">{{ $review->moderator->name }}</span>
                                        <span class="text-muted fs-12">({{ $review->moderator->email }})</span>
                                    @else
                                        <span class="text-muted">— لم يتم الإشراف بعد —</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">تاريخ اتخاذ القرار:</th>
                                <td>
                                    @if($review->moderated_at)
                                        <span>{{ $review->moderated_at->format('Y-m-d H:i:s') }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">سبب الرفض / الإخفاء أو الملاحظات:</th>
                                <td>
                                    @if($review->moderation_reason)
                                        <div class="alert alert-warning mb-0 fs-13">
                                            {{ $review->moderation_reason }}
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar / Context info & Actions -->
    <div class="col-xl-4">
        <!-- Moderation Actions Card -->
        @if(auth()->user()->hasPermission('admin.reviews.moderate'))
            <div class="card border-top border-3 border-primary">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="ri-settings-4-line me-1"></i> إجراءات الإشراف</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted fs-13 mb-3">يمكنك تغيير حالة هذا التقييم بناءً على سياسات المحتوى المتبعة.</p>

                    <div class="d-grid gap-2">
                        @if($review->status !== \App\Enums\ProductReviewStatus::Approved)
                            <form action="{{ route('admin.reviews.moderate', $review) }}" method="POST">
                                @csrf
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="btn btn-success w-100" onclick="return confirm('هل أنت متأكد من اعتماد هذا التقييم ونشره للعملاء؟')">
                                    <i class="ri-checkbox-circle-line me-1"></i> اعتماد التقييم
                                </button>
                            </form>
                        @endif

                        @if($review->status !== \App\Enums\ProductReviewStatus::Rejected)
                            <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="ri-close-circle-line me-1"></i> رفض التقييم
                            </button>
                        @endif

                        @if($review->status !== \App\Enums\ProductReviewStatus::Hidden)
                            <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#hideModal">
                                <i class="ri-eye-off-line me-1"></i> إخفاء التقييم
                            </button>
                        @endif

                        @if($review->status !== \App\Enums\ProductReviewStatus::Pending)
                            <form action="{{ route('admin.reviews.moderate', $review) }}" method="POST">
                                @csrf
                                <input type="hidden" name="status" value="pending">
                                <button type="submit" class="btn btn-outline-secondary w-100" onclick="return confirm('هل أنت متأكد من إعادة هذا التقييم إلى قيد المراجعة؟')">
                                    <i class="ri-history-line me-1"></i> إعادة لحالة قيد الانتظار
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Product Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="ri-shopping-bag-line me-1"></i> المنتج المرتبط</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar-sm bg-light rounded p-1 me-3 d-flex align-items-center justify-content-center">
                        <i class="ri-shopping-cart-2-line text-primary fs-20"></i>
                    </div>
                    <div>
                        <h6 class="fs-14 mb-1">
                            <a href="{{ route('admin.products.show', $review->product_id) }}" class="text-dark fw-semibold">
                                {{ $review->product->name_ar }}
                            </a>
                        </h6>
                        <span class="text-muted fs-12">{{ $review->product->slug }}</span>
                    </div>
                </div>
                <div class="border-top pt-2">
                    <div class="d-flex justify-content-between py-1 fs-13">
                        <span class="text-muted">التصنيف:</span>
                        <span class="fw-medium">{{ $review->product->category?->name_ar ?: '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 fs-13">
                        <span class="text-muted">العلامة التجارية:</span>
                        <span class="fw-medium">{{ $review->product->brand?->name_ar ?: '—' }}</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('admin.products.show', $review->product_id) }}" class="btn btn-sm btn-soft-primary w-100">
                            <i class="ri-external-link-line me-1"></i> عرض تفاصيل المنتج
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="ri-user-3-line me-1"></i> بيانات العميل</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar-sm bg-info-subtle rounded-circle p-1 me-3 d-flex align-items-center justify-content-center">
                        <i class="ri-user-smile-line text-info fs-20"></i>
                    </div>
                    <div>
                        <h6 class="fs-14 mb-1">
                            <a href="{{ route('admin.customers.show', $review->customer_id) }}" class="text-dark fw-semibold">
                                {{ $review->customer->name }}
                            </a>
                        </h6>
                        <span class="text-muted fs-12" dir="ltr">{{ $review->customer->phone }}</span>
                    </div>
                </div>
                <div class="border-top pt-2">
                    <div class="d-flex justify-content-between py-1 fs-13">
                        <span class="text-muted">البريد الإلكتروني:</span>
                        <span class="fw-medium" dir="ltr">{{ $review->customer->email ?: '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 fs-13">
                        <span class="text-muted">حالة الحساب:</span>
                        <span class="badge bg-success-subtle text-success">نشط</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('admin.customers.show', $review->customer_id) }}" class="btn btn-sm btn-soft-info w-100">
                            <i class="ri-user-shared-line me-1"></i> ملف العميل
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.reviews.moderate', $review) }}" method="POST">
            @csrf
            <input type="hidden" name="status" value="rejected">
            <div class="modal-content">
                <div class="modal-header bg-danger-subtle">
                    <h5 class="modal-title text-danger" id="rejectModalLabel"><i class="ri-close-circle-line me-1"></i> رفض التقييم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">أنت على وشك رفض هذا التقييم. لن يظهر هذا التقييم في المتجر العام.</p>
                    <div class="mb-3">
                        <label for="reject_reason" class="form-label required">سبب الرفض <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_reason" name="reason" rows="3" placeholder="اذكر سبب الرفض بالتفصيل (مثل: مخالفة لسياسة النشر، محتوى غير لائق...)" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hide Modal -->
<div class="modal fade" id="hideModal" tabindex="-1" aria-labelledby="hideModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.reviews.moderate', $review) }}" method="POST">
            @csrf
            <input type="hidden" name="status" value="hidden">
            <div class="modal-content">
                <div class="modal-header bg-warning-subtle">
                    <h5 class="modal-title text-warning" id="hideModalLabel"><i class="ri-eye-off-line me-1"></i> إخفاء التقييم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">سيتم إخفاء هذا التقييم مؤقتاً ولن يتم احتسابه في متوسط التقييم العام للمنتج.</p>
                    <div class="mb-3">
                        <label for="hide_reason" class="form-label required">سبب الإخفاء <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="hide_reason" name="reason" rows="3" placeholder="اذكر سبب الإخفاء (مثال: بلاغ قيد المراجعة، نزاع قائم...)" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning">تأكيد الإخفاء</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
