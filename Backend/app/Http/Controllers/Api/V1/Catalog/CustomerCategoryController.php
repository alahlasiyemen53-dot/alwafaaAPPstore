<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Catalog\CustomerCategoryResource;
use App\Queries\Catalog\ListCustomerCategoriesQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CustomerCategoryController extends Controller
{
    public function index(ListCustomerCategoriesQuery $query): JsonResponse
    {
        $categories = $query->getTree();

        return ApiResponse::success(
            CustomerCategoryResource::collection($categories),
            'تم جلب تصنيفات المتجر بنجاح.'
        );
    }

    public function show(int|string $category, ListCustomerCategoriesQuery $query): JsonResponse
    {
        $categoryModel = $query->findActive($category);

        if ($categoryModel === null) {
            return ApiResponse::error(
                ApiErrorCode::CategoryNotFound->defaultMessage(),
                ApiErrorCode::CategoryNotFound,
                404
            );
        }

        return ApiResponse::success(
            new CustomerCategoryResource($categoryModel),
            'تم جلب تفاصيل القسم بنجاح.'
        );
    }
}
