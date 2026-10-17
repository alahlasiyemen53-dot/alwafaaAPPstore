<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reviews;

use App\Enums\ApiErrorCode;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Reviews\ProductReviewResource;
use App\Models\Product;
use App\Queries\ListPublicProductReviewsQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerProductReviewController extends Controller
{
    public function __construct(
        private readonly ListPublicProductReviewsQuery $reviewsQuery,
    ) {}

    public function index(Request $request, int|string $product): JsonResponse
    {
        $resolved = $this->resolveActiveProduct($product);
        if ($resolved === null) {
            return ApiResponse::error(
                ApiErrorCode::ProductNotFound->defaultMessage(),
                ApiErrorCode::ProductNotFound,
                404
            );
        }

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginator = $this->reviewsQuery->paginate($resolved, $perPage, $page);

        return ApiResponse::success([
            'items' => ProductReviewResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 'تم استرجاع تقييمات المنتج بنجاح.');
    }

    private function resolveActiveProduct(int|string $product): ?Product
    {
        $query = Product::query()->where('status', ProductStatus::Active);

        if (is_numeric($product)) {
            return $query->where('id', (int) $product)->first();
        }

        return $query->where('slug', (string) $product)->first();
    }
}
