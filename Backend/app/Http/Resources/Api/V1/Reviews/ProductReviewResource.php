<?php

namespace App\Http\Resources\Api\V1\Reviews;

use App\Models\ProductReview;
use App\Support\CustomerDisplayName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductReview
 */
final class ProductReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductReview $review */
        $review = $this->resource;

        $preview = $review->relationLoaded('visibleComments')
            ? $review->visibleComments
            : collect();

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'is_verified_purchase' => (bool) $review->is_verified_purchase,
            'author' => [
                'display_name' => CustomerDisplayName::from($review->customer),
            ],
            'comment_count' => (int) ($review->comment_count ?? 0),
            'comments_preview' => ReviewCommentResource::collection($preview),
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }
}
