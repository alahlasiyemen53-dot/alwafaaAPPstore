<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reviews;

use App\Actions\CreateCustomerReviewCommentAction;
use App\Data\CreateReviewCommentData;
use App\Enums\ApiErrorCode;
use App\Enums\ProductReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reviews\CreateReviewCommentRequest;
use App\Http\Resources\Api\V1\Reviews\ReviewCommentResource;
use App\Models\Customer;
use App\Models\ProductReview;
use App\Queries\ListPublicReviewCommentsQuery;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class CustomerReviewCommentController extends Controller
{
    public function __construct(
        private readonly ListPublicReviewCommentsQuery $commentsQuery,
        private readonly CreateCustomerReviewCommentAction $createAction,
    ) {}

    public function index(Request $request, int $review): JsonResponse
    {
        $productReview = $this->findPublicReview($review);
        if ($productReview === null) {
            return $this->reviewNotFoundResponse();
        }

        try {
            $paginator = $this->commentsQuery->paginate(
                $productReview,
                (int) $request->input('per_page', 15),
                (int) $request->input('page', 1),
            );
        } catch (DomainException) {
            return $this->reviewNotFoundResponse();
        }

        return ApiResponse::success([
            'items' => ReviewCommentResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 'تم استرجاع التعليقات بنجاح.');
    }

    public function store(CreateReviewCommentRequest $request, int $review): JsonResponse
    {
        $productReview = $this->findPublicReview($review);
        if ($productReview === null) {
            return $this->reviewNotFoundResponse();
        }

        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        try {
            $comment = $this->createAction->execute(
                review: $productReview,
                customer: $customer,
                data: CreateReviewCommentData::fromValidated($request->validated()),
            );
        } catch (DomainException $e) {
            if ($e->getMessage() === 'REVIEW_NOT_PUBLIC') {
                return $this->reviewNotFoundResponse();
            }

            return ApiResponse::error(
                ApiErrorCode::CommentNotAllowed->defaultMessage(),
                ApiErrorCode::CommentNotAllowed,
                403
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::validationError(
                ['body' => [$e->getMessage()]],
                'Please correct the highlighted information.'
            );
        }

        $comment->load(['customer:id,name', 'adminUser:id,name']);

        return ApiResponse::created(
            new ReviewCommentResource($comment),
            'تم إضافة التعليق بنجاح.'
        );
    }

    private function findPublicReview(int $reviewId): ?ProductReview
    {
        return ProductReview::query()
            ->where('id', $reviewId)
            ->where('status', ProductReviewStatus::Approved)
            ->first();
    }

    private function reviewNotFoundResponse(): JsonResponse
    {
        return ApiResponse::error(
            ApiErrorCode::ReviewNotFound->defaultMessage(),
            ApiErrorCode::ReviewNotFound,
            404
        );
    }
}
