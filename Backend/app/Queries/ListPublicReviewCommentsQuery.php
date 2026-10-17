<?php

namespace App\Queries;

use App\Enums\ProductReviewStatus;
use App\Enums\ReviewCommentStatus;
use App\Models\ProductReview;
use App\Models\ReviewComment;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListPublicReviewCommentsQuery
{
    /**
     * @return LengthAwarePaginator<int, ReviewComment>
     */
    public function paginate(ProductReview $review, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        if ($review->status !== ProductReviewStatus::Approved) {
            throw new DomainException('REVIEW_NOT_PUBLIC');
        }

        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        return ReviewComment::query()
            ->where('review_id', $review->id)
            ->where('status', ReviewCommentStatus::Approved)
            ->with(['customer:id,name', 'adminUser:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
