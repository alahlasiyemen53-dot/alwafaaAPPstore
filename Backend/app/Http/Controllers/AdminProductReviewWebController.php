<?php

namespace App\Http\Controllers;

use App\Actions\CheckProductReviewEligibilityAction;
use App\Actions\CreateAdminReviewReplyAction;
use App\Actions\ModerateProductReviewAction;
use App\Actions\ModerateReviewCommentAction;
use App\Data\AdminProductReviewFilterData;
use App\Data\AdminReviewReplyData;
use App\Enums\ProductReviewStatus;
use App\Enums\ReviewCommentStatus;
use App\Http\Requests\Admin\AdminModerateReviewCommentRequest;
use App\Http\Requests\Admin\AdminModerateReviewRequest;
use App\Http\Requests\Admin\AdminProductReviewFilterRequest;
use App\Http\Requests\Admin\AdminReviewReplyRequest;
use App\Models\ProductReview;
use App\Models\ReviewComment;
use App\Queries\AdminProductReviewsQuery;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

final class AdminProductReviewWebController extends Controller
{
    public function index(AdminProductReviewFilterRequest $request, AdminProductReviewsQuery $query): View
    {
        $filters = AdminProductReviewFilterData::fromValidated($request->validated());
        $reviews = $query->paginate($filters);
        $metrics = $query->getMetrics();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'metrics' => $metrics,
            'filters' => $request->validated(),
        ]);
    }

    public function show(ProductReview $review, CheckProductReviewEligibilityAction $eligibilityAction): View
    {
        $review->load([
            'customer:id,name,phone,normalized_phone,is_active',
            'product:id,name_ar,name_en,slug,status,brand_id,category_id',
            'product.brand:id,name_ar',
            'product.category:id,name_ar',
            'product.media',
            'moderator:id,name,email',
            'comments' => fn ($q) => $q
                ->with(['customer:id,name,phone', 'adminUser:id,name,email'])
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        $isVerifiedPurchase = $eligibilityAction->execute($review->customer, $review->product);

        return view('admin.reviews.show', [
            'review' => $review,
            'isVerifiedPurchase' => $isVerifiedPurchase,
        ]);
    }

    public function moderate(
        AdminModerateReviewRequest $request,
        ProductReview $review,
        ModerateProductReviewAction $action,
    ): RedirectResponse {
        $rawAction = $request->input('action') ?? $request->input('status');
        $targetStatus = match ($rawAction) {
            'approve', 'approved' => ProductReviewStatus::Approved,
            'reject', 'rejected' => ProductReviewStatus::Rejected,
            'hide', 'hidden' => ProductReviewStatus::Hidden,
            'restore', 'pending' => ProductReviewStatus::Pending,
            default => throw new DomainException('إجراء إشراف غير مدعوم.'),
        };

        try {
            $action->execute(
                review: $review,
                targetStatus: $targetStatus,
                moderator: $request->user(),
                reason: $request->input('reason'),
            );

            return redirect()->back()->with('success', 'تم تحديث حالة التقييم بنجاح.');
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reply(
        AdminReviewReplyRequest $request,
        ProductReview $review,
        CreateAdminReviewReplyAction $action,
    ): RedirectResponse {
        try {
            $action->execute(
                review: $review,
                admin: $request->user(),
                data: AdminReviewReplyData::fromValidated($request->validated()),
            );

            return redirect()->back()->with('success', 'تم إرسال رد الإدارة بنجاح.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function moderateComment(
        AdminModerateReviewCommentRequest $request,
        ProductReview $review,
        ReviewComment $comment,
        ModerateReviewCommentAction $action,
    ): RedirectResponse {
        if ((int) $comment->review_id !== (int) $review->id) {
            abort(404);
        }

        $rawAction = (string) $request->input('action');
        $targetStatus = match ($rawAction) {
            'hide', 'hidden' => ReviewCommentStatus::Hidden,
            'restore', 'approved' => ReviewCommentStatus::Approved,
            default => throw new DomainException('إجراء إشراف تعليق غير مدعوم.'),
        };

        try {
            $action->execute(
                comment: $comment,
                targetStatus: $targetStatus,
                moderator: $request->user(),
            );

            $message = $targetStatus === ReviewCommentStatus::Hidden
                ? 'تم إخفاء التعليق بنجاح.'
                : 'تم إظهار التعليق بنجاح.';

            return redirect()->back()->with('success', $message);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
