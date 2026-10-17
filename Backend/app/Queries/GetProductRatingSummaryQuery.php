<?php

namespace App\Queries;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;

final class GetProductRatingSummaryQuery
{
    /**
     * @return array{
     *     average_rating: float,
     *     reviews_count: int,
     *     distribution: array<int, int>
     * }
     */
    public function execute(Product|int $product): array
    {
        return $this->getForProduct($product);
    }

    /**
     * @return array{
     *     average_rating: float,
     *     reviews_count: int,
     *     distribution: array<int, int>
     * }
     */
    public function getForProduct(Product|int $product): array
    {
        $productId = $product instanceof Product ? $product->id : $product;

        $approvedQuery = ProductReview::query()
            ->where('product_id', $productId)
            ->where('status', ProductReviewStatus::Approved);

        $reviewsCount = (int) $approvedQuery->count();

        if ($reviewsCount === 0) {
            return [
                'average_rating' => 0.0,
                'reviews_count' => 0,
                'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ];
        }

        $avg = (float) $approvedQuery->avg('rating');

        $counts = ProductReview::query()
            ->where('product_id', $productId)
            ->where('status', ProductReviewStatus::Approved)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->all();

        $distribution = [
            5 => (int) ($counts[5] ?? 0),
            4 => (int) ($counts[4] ?? 0),
            3 => (int) ($counts[3] ?? 0),
            2 => (int) ($counts[2] ?? 0),
            1 => (int) ($counts[1] ?? 0),
        ];

        return [
            'average_rating' => round($avg, 1),
            'reviews_count' => $reviewsCount,
            'distribution' => $distribution,
        ];
    }
}
