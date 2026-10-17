<?php

namespace App\Actions;

use App\Data\CreateReviewCommentData;
use App\Enums\ProductReviewStatus;
use App\Enums\ReviewCommentStatus;
use App\Models\Customer;
use App\Models\ProductReview;
use App\Models\ReviewComment;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateCustomerReviewCommentAction
{
    public function execute(
        ProductReview $review,
        Customer $customer,
        CreateReviewCommentData $data,
    ): ReviewComment {
        if ($review->status !== ProductReviewStatus::Approved) {
            throw new DomainException('REVIEW_NOT_PUBLIC');
        }

        $body = trim($data->body);
        if ($body === '') {
            throw new InvalidArgumentException('نص التعليق مطلوب.');
        }

        if (mb_strlen($body) > 1000) {
            throw new InvalidArgumentException('نص التعليق يجب ألا يتجاوز 1000 حرف.');
        }

        return DB::transaction(function () use ($review, $customer, $body): ReviewComment {
            /** @var ProductReview $lockedReview */
            $lockedReview = ProductReview::query()
                ->where('id', $review->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReview->status !== ProductReviewStatus::Approved) {
                throw new DomainException('REVIEW_NOT_PUBLIC');
            }

            return ReviewComment::query()->create([
                'review_id' => $lockedReview->id,
                'customer_id' => $customer->id,
                'admin_user_id' => null,
                'body' => $body,
                'status' => ReviewCommentStatus::Approved,
            ]);
        });
    }
}
