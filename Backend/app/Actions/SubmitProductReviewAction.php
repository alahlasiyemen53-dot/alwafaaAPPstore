<?php

namespace App\Actions;

use App\Enums\ProductReviewStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use DomainException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

final class SubmitProductReviewAction
{
    public function __construct(
        private readonly CheckProductReviewEligibilityAction $eligibilityAction,
    ) {}

    public function execute(
        Customer $customer,
        Product $product,
        int $rating,
        ?string $title = null,
        ?string $comment = null,
        ?string $body = null,
    ): ProductReview {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('التقييم يجب أن يكون بين 1 و 5 نجوم.');
        }

        if (! $this->eligibilityAction->execute($customer, $product)) {
            throw new DomainException('لا يمكن تقييم المنتج إلا بعد استلام طلب مؤكد يحتوي عليه.');
        }

        $text = $comment ?? $body;

        try {
            return ProductReview::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'product_id' => $product->id,
                ],
                [
                    'rating' => $rating,
                    'title' => $title ? trim($title) : null,
                    'body' => $text ? trim($text) : null,
                    'status' => ProductReviewStatus::Pending,
                    'is_verified_purchase' => true,
                    'moderated_by' => null,
                    'moderated_at' => null,
                    'moderation_reason' => null,
                ]
            );
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                return ProductReview::query()
                    ->where('customer_id', $customer->id)
                    ->where('product_id', $product->id)
                    ->firstOrFail();
            }

            throw $e;
        }
    }
}
