<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\ListCustomerProductsRequest;
use App\Http\Resources\Api\V1\Catalog\CustomerProductDetailResource;
use App\Http\Resources\Api\V1\Catalog\CustomerProductListResource;
use App\Queries\Catalog\GetCustomerProductDetailQuery;
use App\Queries\Catalog\GetRelatedProductsQuery;
use App\Queries\Catalog\ListCustomerProductsQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerProductController extends Controller
{
    public function index(ListCustomerProductsRequest $request, ListCustomerProductsQuery $query): JsonResponse
    {
        $paginator = $query->paginate($request->validated());

        return ApiResponse::success([
            'items' => CustomerProductListResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 'تم جلب قائمة المنتجات بنجاح.');
    }

    public function show(int|string $product, GetCustomerProductDetailQuery $query): JsonResponse
    {
        $result = $query->find($product);

        if ($result === null) {
            return ApiResponse::error(
                ApiErrorCode::ProductNotFound->defaultMessage(),
                ApiErrorCode::ProductNotFound,
                404
            );
        }

        $resource = (new CustomerProductDetailResource($result['product']))
            ->withRatingSummary($result['rating_summary']);

        return ApiResponse::success($resource, 'تم جلب تفاصيل المنتج بنجاح.');
    }

    public function related(Request $request, int|string $product, GetRelatedProductsQuery $query): JsonResponse
    {
        $limit = $request->query('limit') !== null ? (int) $request->query('limit') : 8;

        $items = $query->execute($product, $limit);

        if ($items === null) {
            return ApiResponse::error(
                ApiErrorCode::ProductNotFound->defaultMessage(),
                ApiErrorCode::ProductNotFound,
                404
            );
        }

        return ApiResponse::success([
            'items' => CustomerProductListResource::collection($items),
        ], 'تم جلب المنتجات ذات الصلة بنجاح.');
    }
}

