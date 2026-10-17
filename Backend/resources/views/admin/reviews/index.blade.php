@extends('layouts.admin')
@section('title', 'تقييمات ومراجعات المنتجات | الوفاء استور')
@section('page-title', 'مراجعات المنتجات')

@section('content')
<!-- Metric Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">إجمالي التقييمات</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded fs-3">
                            <i class="ri-star-line text-primary"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-primary">{{ number_format($metrics['total']) }}</h4>
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
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">بانتظار المراجعة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle rounded fs-3">
                            <i class="ri-time-line text-warning"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-warning">{{ number_format($metrics['pending']) }}</h4>
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
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">التقييمات المعتمدة</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded fs-3">
                            <i class="ri-checkbox-circle-line text-success"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-success">{{ number_format($metrics['approved']) }}</h4>
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
                        <p class="text-uppercase fw-medium text-muted mb-0 fs-13">المرفوضة والمخفية</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle rounded fs-3">
                            <i class="ri-eye-off-line text-danger"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-0 text-danger">{{ number_format($metrics['rejected'] + $metrics['hidden']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">
                    <i class="ri-star-half-line me-1 text-warning"></i> سجل تقييمات ومراجعات المنتجات ({{ $reviews->total() }})
                </h4>
                <span class="badge bg-light text-muted border px-2 py-1 fs-12">
                    <i class="ri-shield-user-line me-1"></i> إدارة وإشراف محصن بالصلاحيات
                </span>
            </div>

            <!-- Filter Bar -->
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.reviews.index') }}" class="row g-2 filter-row">
                    <div class="col-md-3">
                        <div class="search-box">
                            <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="البحث بالعميل، المنتج، أو نص المراجعة...">
                            <i class="ri-search-line search-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">كل الحالات</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>بانتظار المراجعة</option>
                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>معتمد</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>مرفوض</option>
                            <option value="hidden" {{ request('status') === 'hidden' ? 'selected' : '' }}>مخفي</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="rating" class="form-select">
                            <option value="">كل التقييمات</option>
                            <option value="5" {{ request('rating') == '5' ? 'selected' : '' }}>5 نجوم ★★★★★</option>
                            <option value="4" {{ request('rating') == '4' ? 'selected' : '' }}>4 نجوم ★★★★☆</option>
                            <option value="3" {{ request('rating') == '3' ? 'selected' : '' }}>3 نجوم ★★★☆☆</option>
                            <option value="2" {{ request('rating') == '2' ? 'selected' : '' }}>نجمتان ★★☆☆☆</option>
                            <option value="1" {{ request('rating') == '1' ? 'selected' : '' }}>نجمة واحدة ★☆☆☆☆</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="from_date" value="{{ request('from_date') }}" placeholder="من تاريخ">
                    </div>
                    <div class="col-md-1">
                        <input type="date" class="form-control" name="to_date" value="{{ request('to_date') }}" placeholder="إلى تاريخ">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ri-filter-2-line me-1"></i> تصفية
                        </button>
                        <a href="{{ route('admin.reviews.index') }}" class="btn btn-light" title="إعادة ضبط">
                            <i class="ri-refresh-line"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" style="width: 50px;">#</th>
                                <th scope="col">العميل</th>
                                <th scope="col">المنتج</th>
                                <th scope="col">التقييم</th>
                                <th scope="col" style="max-width: 250px;">المراجعة</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">تاريخ الإرسال</th>
                                <th scope="col" class="text-center" style="width: 150px;">إجراءات الإشراف</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reviews as $review)
                                <tr>
                                    <td class="text-muted fs-12">{{ $review->id }}</td>
                                    <td>
                                        @if($review->customer)
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs me-2">
                                                    <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-12 fw-bold">
                                                        {{ mb_substr($review->customer->name, 0, 1) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <h6 class="fs-13 mb-0">
                                                        @if(auth()->user()->hasPermission('admin.customers.view'))
                                                            <a href="{{ route('admin.customers.show', $review->customer) }}" class="text-dark">
                                                                {{ $review->customer->name }}
                                                            </a>
                                                        @else
                                                            {{ $review->customer->name }}
                                                        @endif
                                                    </h6>
                                                    <small class="text-muted" dir="ltr">{{ $review->customer->phone }}</small>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">عميل محذوف</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($review->product)
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-2">
                                                    @if($review->product->getPrimaryImageUrl('thumb'))
                                                        <img src="{{ $review->product->getPrimaryImageUrl('thumb') }}" alt="{{ $review->product->name_ar }}" class="rounded avatar-xs object-fit-cover">
                                                    @else
                                                        <div class="avatar-xs rounded bg-light text-muted d-flex align-items-center justify-content-center">
                                                            <i class="ri-image-line fs-14"></i>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="fs-13 mb-0">
                                                        @if(auth()->user()->hasPermission('admin.products.view'))
                                                            <a href="{{ route('admin.products.show', $review->product) }}" class="text-dark">
                                                                {{ $review->product->name_ar }}
                                                            </a>
                                                        @else
                                                            {{ $review->product->name_ar }}
                                                        @endif
                                                    </h6>
                                                    <small class="text-muted">{{ $review->product->category?->name_ar ?? '—' }}</small>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">منتج محذوف</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            @for($s = 1; $s <= 5; $s++)
                                                <i class="ri-star-fill fs-14 {{ $s <= $review->rating ? 'text-warning' : 'text-muted opacity-25' }}"></i>
                                            @endfor
                                            <span class="fw-semibold fs-12 ms-1">({{ $review->rating }})</span>
                                        </div>
                                    </td>
                                    <td style="max-width: 250px;" class="text-truncate">
                                        @if($review->title)
                                            <strong class="d-block text-dark fs-13 text-truncate">{{ $review->title }}</strong>
                                        @endif
                                        @if($review->body)
                                            <span class="text-muted fs-12 text-truncate d-block">{{ $review->body }}</span>
                                        @elseif(! $review->title)
                                            <span class="text-muted fst-italic fs-12">تقييم بالنجوم فقط</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $review->status->badgeClass() }} px-2 py-1">
                                            {{ $review->status->label() }}
                                        </span>
                                        @if($review->moderation_reason)
                                            <i class="ri-information-line text-muted ms-1" data-bs-toggle="tooltip" title="السبب: {{ $review->moderation_reason }}"></i>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fs-12 text-dark">{{ $review->created_at->format('Y-m-d H:i') }}</div>
                                        <small class="text-muted">{{ $review->created_at->diffForHumans() }}</small>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="{{ route('admin.reviews.show', $review) }}" class="btn btn-sm btn-subtle-info" title="عرض التفاصيل الكاملة">
                                                <i class="ri-eye-line"></i>
                                            </a>

                                            @if(auth()->user()->hasPermission('admin.reviews.moderate'))
                                                @if($review->isPending())
                                                    <!-- Approve Button -->
                                                    <form action="{{ route('admin.reviews.moderate', $review) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-sm btn-subtle-success" title="اعتماد المراجعة" onclick="return confirm('هل أنت متأكد من اعتماد ونشر هذه المراجعة؟')">
                                                            <i class="ri-check-line"></i>
                                                        </button>
                                                    </form>

                                                    <!-- Reject Button triggers modal -->
                                                    <button type="button" class="btn btn-sm btn-subtle-danger" title="رفض المراجعة" data-bs-toggle="modal" data-bs-target="#reasonModal" data-action="reject" data-url="{{ route('admin.reviews.moderate', $review) }}" data-title="رفض المراجعة">
                                                        <i class="ri-close-line"></i>
                                                    </button>
                                                @elseif($review->isApproved())
                                                    <!-- Hide Button triggers modal -->
                                                    <button type="button" class="btn btn-sm btn-subtle-warning" title="إخفاء المراجعة" data-bs-toggle="modal" data-bs-target="#reasonModal" data-action="hide" data-url="{{ route('admin.reviews.moderate', $review) }}" data-title="إخفاء المراجعة من العرض العام">
                                                        <i class="ri-eye-off-line"></i>
                                                    </button>
                                                @elseif($review->isHidden())
                                                    <!-- Restore Button -->
                                                    <form action="{{ route('admin.reviews.moderate', $review) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="restore">
                                                        <button type="submit" class="btn btn-sm btn-subtle-success" title="إعادة النشر والعرض" onclick="return confirm('هل تريد استعادة هذه المراجعة ونشرها مجدداً؟')">
                                                            <i class="ri-restart-line"></i>
                                                        </button>
                                                    </form>
                                                @elseif($review->isRejected())
                                                    <!-- Re-review Button -->
                                                    <form action="{{ route('admin.reviews.moderate', $review) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="restore">
                                                        <input type="hidden" name="reason" value="إعادة مراجعة">
                                                        <button type="submit" class="btn btn-sm btn-subtle-secondary" title="إعادة للنظر (قيد المراجعة)" onclick="return confirm('هل تريد إعادة هذه المراجعة لحالة قيد النظر؟')">
                                                            <i class="ri-history-line"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-3">
                                            <span class="avatar-title bg-light text-muted rounded-circle fs-2">
                                                <i class="ri-star-line"></i>
                                            </span>
                                        </div>
                                        @if(request()->hasAny(['search', 'status', 'rating', 'from_date', 'to_date']))
                                            <h5 class="text-muted">لا توجد مراجعات مطابقة لشروط الفلترة</h5>
                                            <p class="text-muted mb-3">جرب تعديل شروط البحث أو الفلاتر المحددة.</p>
                                            <a href="{{ route('admin.reviews.index') }}" class="btn btn-light btn-sm">
                                                <i class="ri-refresh-line me-1"></i> إعادة ضبط الفلاتر
                                            </a>
                                        @else
                                            <h5 class="text-muted">لا توجد مراجعات حتى الآن</h5>
                                            <p class="text-muted mb-0">عندما يقوم العملاء بتقييم مشترياتهم، ستظهر التقييمات هنا للإشراف والمراجعة.</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($reviews->hasPages())
                    <div class="d-flex justify-content-center border-top pt-3 mt-3">
                        {{ $reviews->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal: Moderation Reason (for Reject and Hide actions) -->
<div class="modal fade" id="reasonModal" tabindex="-1" aria-labelledby="reasonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="reasonForm" method="POST" action="">
                @csrf
                <input type="hidden" name="action" id="modalAction" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="reasonModalLabel"><i class="ri-feedback-line me-1 text-primary"></i> <span id="modalTitleText">سبب الإشراف</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modalReasonInput" class="form-label required">يرجى كتابة سبب هذا الإجراء (إلزامي للتوثيق والتدقيق):</label>
                        <textarea class="form-control" name="reason" id="modalReasonInput" rows="3" required placeholder="مثال: محتوى غير ملائم، يحتوي على ألفاظ غير لائقة، طلب غير مطابق..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger" id="modalSubmitBtn">تأكيد الإجراء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reasonModal = document.getElementById('reasonModal');
    if (reasonModal) {
        reasonModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const action = button.getAttribute('data-action');
            const url = button.getAttribute('data-url');
            const title = button.getAttribute('data-title');

            document.getElementById('reasonForm').action = url;
            document.getElementById('modalAction').value = action;
            document.getElementById('modalTitleText').textContent = title || 'سبب الإشراف';
            document.getElementById('modalReasonInput').value = '';

            const submitBtn = document.getElementById('modalSubmitBtn');
            if (action === 'hide') {
                submitBtn.className = 'btn btn-warning';
                submitBtn.textContent = 'تأكيد الإخفاء';
            } else {
                submitBtn.className = 'btn btn-danger';
                submitBtn.textContent = 'تأكيد الرفض';
            }
        });
    }
});
</script>
@endsection
