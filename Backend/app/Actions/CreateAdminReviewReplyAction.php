<?php

namespace App\Actions;

use App\Data\AdminReviewReplyData;
use App\Enums\ReviewCommentStatus;
use App\Models\ProductReview;
use App\Models\ReviewComment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateAdminReviewReplyAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        ProductReview $review,
        User $admin,
        AdminReviewReplyData $data,
    ): ReviewComment {
        $body = trim($data->body);
        if ($body === '') {
            throw new InvalidArgumentException('نص رد الإدارة مطلوب.');
        }

        if (mb_strlen($body) > 1000) {
            throw new InvalidArgumentException('نص رد الإدارة يجب ألا يتجاوز 1000 حرف.');
        }

        return DB::transaction(function () use ($review, $admin, $body): ReviewComment {
            /** @var ProductReview $lockedReview */
            $lockedReview = ProductReview::query()
                ->where('id', $review->id)
                ->lockForUpdate()
                ->firstOrFail();

            $comment = ReviewComment::query()->create([
                'review_id' => $lockedReview->id,
                'customer_id' => null,
                'admin_user_id' => $admin->id,
                'body' => $body,
                'status' => ReviewCommentStatus::Approved,
            ]);

            $this->auditLogger->log(
                action: 'admin.review_comment.reply',
                actor: $admin,
                target: $comment,
                payload: [
                    'review_id' => $lockedReview->id,
                    'comment_id' => $comment->id,
                ]
            );

            return $comment;
        });
    }
}
