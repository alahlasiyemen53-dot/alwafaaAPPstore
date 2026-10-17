<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contracts\Search\ProductSearchInterface;
use App\DTOs\Search\ProductSearchQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\SearchCustomerProductsRequest;
use App\Http\Resources\Api\V1\Catalog\CustomerProductListResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CustomerSearchController extends Controller
{
    public function index(
        SearchCustomerProductsRequest $request,
        ProductSearchInterface $searchService
    ): JsonResponse {
        $validated = $request->validated();

        $categoryIds = null;
        if (! empty($validated['category_id'])) {
            $categoryIds = array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                (array) $validated['category_id'],
            )));
        }

        $brandIds = null;
        if (! empty($validated['brand_id'])) {
            $brandIds = array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                (array) $validated['brand_id'],
            )));
        }

        $inStockOnly = false;
        if (isset($validated['availability'])) {
            $inStockOnly = in_array($validated['availability'], ['in_stock', 'stocked'], true);
        }

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $sortBy = (string) ($validated['sort'] ?? 'relevance');

        $searchQuery = new ProductSearchQuery(
            query: (string) $validated['q'],
            categoryIds: $categoryIds,
            brandIds: $brandIds,
            inStockOnly: $inStockOnly,
            isActiveOnly: true,
            sortBy: $sortBy,
            page: $page,
            perPage: $perPage,
        );

        $result = $searchService->search($searchQuery);

        return ApiResponse::success([
            'items' => CustomerProductListResource::collection($result->items),
            'pagination' => [
                'current_page' => $result->page,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'last_page' => $result->lastPage,
                'has_more' => $result->page < $result->lastPage,
            ],
            'meta' => [
                'query' => $validated['q'],
                'processing_time_ms' => $result->processingTimeMs,
            ],
        ], 'تم البحث عن المنتجات بنجاح.');
    }
}
