<?php

namespace App\Actions;

use App\Enums\ProductReviewStatus;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ModerateProductReviewAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        ProductReview $review,
        ?ProductReviewStatus $targetStatus = null,
        ?User $moderator = null,
        ?string $reason = null,
        ?ProductReviewStatus $status = null,
    ): ProductReview {
        $effectiveStatus = $targetStatus ?? $status;
        if (! $effectiveStatus) {
            throw new InvalidArgumentException('حالة الإشراف المستهدفة مطلوبة.');
        }

        if (! $moderator) {
            throw new InvalidArgumentException('المشرف مطلوب لتسجيل العملية.');
        }

        if (in_array($effectiveStatus, [ProductReviewStatus::Rejected, ProductReviewStatus::Hidden], true) && empty(trim((string) $reason))) {
            throw new InvalidArgumentException('سبب الإشراف إلزامي عند رفض أو إخفاء التقييم.');
        }

        return DB::transaction(function () use ($review, $effectiveStatus, $moderator, $reason): ProductReview {
            /** @var ProductReview $lockedReview */
            $lockedReview = ProductReview::query()
                ->where('id', $review->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedReview->status->canTransitionTo($effectiveStatus)) {
                throw new DomainException(sprintf(
                    'لا يمكن تغيير حالة التقييم من [%s] إلى [%s].',
                    $lockedReview->status->label(),
                    $effectiveStatus->label()
                ));
            }

            $oldStatus = $lockedReview->status;

            $lockedReview->update([
                'status' => $effectiveStatus,
                'moderated_by' => $moderator->id,
                'moderated_at' => now(),
                'moderation_reason' => $reason ? trim($reason) : null,
            ]);

            $this->auditLogger->log(
                action: 'admin.product_review.'.$effectiveStatus->value,
                actor: $moderator,
                target: $lockedReview,
                payload: [
                    'previous_status' => $oldStatus->value,
                    'new_status' => $effectiveStatus->value,
                    'moderation_reason' => $reason,
                ]
            );

            return $lockedReview;
        });
    }
}
