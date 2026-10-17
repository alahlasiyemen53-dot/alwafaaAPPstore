<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Actions\MergeGuestRecentlyViewedAction;
use App\Actions\RecordProductViewAction;
use App\Enums\ApiErrorCode;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\MergeGuestRecentlyViewedRequest;
use App\Http\Resources\Api\V1\Catalog\CustomerProductListResource;
use App\Models\Customer;
use App\Models\Product;
use App\Queries\Catalog\GetCustomerRecentlyViewedQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerRecentlyViewedController extends Controller
{
    public function __construct(
        private readonly GetCustomerRecentlyViewedQuery $query,
        private readonly RecordProductViewAction $recordView,
        private readonly MergeGuestRecentlyViewedAction $mergeGuest,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $products = $this->query->execute($customer);

        return ApiResponse::success([
            'items' => CustomerProductListResource::collection($products),
        ], 'تم استرجاع المنتجات التي شاهدتها مؤخراً.');
    }

    public function storeView(Request $request, int|string $product): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $resolved = $this->resolveProduct($product);
        if ($resolved === null || $resolved->status !== ProductStatus::Active) {
            return ApiResponse::error(
                ApiErrorCode::ProductNotFound->defaultMessage(),
                ApiErrorCode::ProductNotFound,
                404
            );
        }

        $this->recordView->execute($customer, $resolved);

        return ApiResponse::success(
            ['product_id' => $resolved->id],
            'تم تسجيل مشاهدة المنتج.'
        );
    }

    public function merge(MergeGuestRecentlyViewedRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('product_ids'));

        $this->mergeGuest->execute($customer, $ids);

        return ApiResponse::success(
            ['items' => CustomerProductListResource::collection($this->query->execute($customer))],
            'تمت مزامنة المنتجات التي شاهدتها مؤخراً.'
        );
    }

    private function resolveProduct(int|string $product): ?Product
    {
        if (is_numeric($product)) {
            return Product::query()->find((int) $product);
        }

        return Product::query()->where('slug', (string) $product)->first();
    }
}
