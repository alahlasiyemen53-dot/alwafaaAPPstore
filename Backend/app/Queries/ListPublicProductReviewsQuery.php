<?php

namespace App\Queries;

use App\Enums\ProductReviewStatus;
use App\Enums\ReviewCommentStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ReviewComment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListPublicProductReviewsQuery
{
    public const PREVIEW_LIMIT = 2;

    /**
     * @return LengthAwarePaginator<int, ProductReview>
     */
    public function paginate(Product $product, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        $paginator = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', ProductReviewStatus::Approved)
            ->with(['customer:id,name'])
            ->withCount([
                'comments as comment_count' => fn ($q) => $q->where('status', ReviewCommentStatus::Approved),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->attachBoundedCommentPreviews($paginator->getCollection());

        return $paginator;
    }

    /**
     * @param  Collection<int, ProductReview>  $reviews
     */
    private function attachBoundedCommentPreviews(Collection $reviews): void
    {
        if ($reviews->isEmpty()) {
            return;
        }

        $reviewIds = $reviews->pluck('id')->map(fn ($id) => (int) $id)->all();
        $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
        $limit = self::PREVIEW_LIMIT;
        $approved = ReviewCommentStatus::Approved->value;

        $rows = DB::select(
            "SELECT id FROM (
                SELECT id,
                       ROW_NUMBER() OVER (PARTITION BY review_id ORDER BY created_at ASC, id ASC) AS rn
                FROM review_comments
                WHERE review_id IN ({$placeholders})
                  AND status = ?
            ) ranked
            WHERE rn <= ?",
            [...$reviewIds, $approved, $limit]
        );

        $previewIds = array_map(static fn ($row) => (int) $row->id, $rows);

        $commentsByReview = empty($previewIds)
            ? collect()
            : ReviewComment::query()
                ->whereIn('id', $previewIds)
                ->with(['customer:id,name', 'adminUser:id,name'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->groupBy('review_id');

        foreach ($reviews as $review) {
            $preview = ($commentsByReview->get($review->id) ?? collect())->values();
            $review->setRelation('visibleComments', $preview);
        }
    }
}
