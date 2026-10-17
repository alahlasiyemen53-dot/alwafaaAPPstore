<?php

namespace App\Actions;

use App\Enums\ReviewCommentStatus;
use App\Models\ReviewComment;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ModerateReviewCommentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        ReviewComment $comment,
        ReviewCommentStatus $targetStatus,
        User $moderator,
    ): ReviewComment {
        return DB::transaction(function () use ($comment, $targetStatus, $moderator): ReviewComment {
            /** @var ReviewComment $locked */
            $locked = ReviewComment::query()
                ->where('id', $comment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->canTransitionTo($targetStatus)) {
                throw new DomainException(sprintf(
                    'لا يمكن تغيير حالة التعليق من [%s] إلى [%s].',
                    $locked->status->label(),
                    $targetStatus->label()
                ));
            }

            $previous = $locked->status;

            $locked->update([
                'status' => $targetStatus,
            ]);

            $this->auditLogger->log(
                action: 'admin.review_comment.'.$targetStatus->value,
                actor: $moderator,
                target: $locked,
                payload: [
                    'previous_status' => $previous->value,
                    'new_status' => $targetStatus->value,
                ]
            );

            return $locked->fresh() ?? $locked;
        });
    }

    public function hide(ReviewComment $comment, User $moderator): ReviewComment
    {
        return $this->execute($comment, ReviewCommentStatus::Hidden, $moderator);
    }

    public function restore(ReviewComment $comment, User $moderator): ReviewComment
    {
        return $this->execute($comment, ReviewCommentStatus::Approved, $moderator);
    }
}
