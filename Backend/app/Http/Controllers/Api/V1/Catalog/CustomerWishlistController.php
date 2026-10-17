<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Actions\AddProductToCustomerWishlistAction;
use App\Actions\RemoveProductFromCustomerWishlistAction;
use App\Enums\ApiErrorCode;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\AddProductToWishlistRequest;
use App\Http\Resources\Api\V1\Catalog\CustomerProductListResource;
use App\Models\Customer;
use App\Models\Product;
use App\Queries\Catalog\GetCustomerWishlistApiQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerWishlistController extends Controller
{
    public function __construct(
        private readonly AddProductToCustomerWishlistAction $addAction,
        private readonly RemoveProductFromCustomerWishlistAction $removeAction,
        private readonly GetCustomerWishlistApiQuery $wishlistQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginator = $this->wishlistQuery->paginate($customer, $perPage, $page);

        return ApiResponse::success([
            'items' => CustomerProductListResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 'تم استرجاع قائمة المفضلة بنجاح.');
    }

    public function store(AddProductToWishlistRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $productId = (int) $request->input('product_id');
        $product = Product::find($productId);

        if ($product === null || $product->status !== ProductStatus::Active) {
            return ApiResponse::error(
                ApiErrorCode::ProductNotFound->defaultMessage(),
                ApiErrorCode::ProductNotFound,
                404
            );
        }

        $this->addAction->execute($customer, $product);

        return ApiResponse::success(
            ['product_id' => $product->id],
            'تمت إضافة المنتج إلى المفضلة بنجاح.'
        );
    }

    public function destroy(Request $request, int|string $product): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $productId = is_numeric($product) ? (int) $product : null;
        if ($productId === null) {
            $resolvedProduct = Product::where('slug', (string) $product)->first();
            $productId = $resolvedProduct?->id;
        }

        if ($productId === null) {
            return ApiResponse::error(
                ApiErrorCode::ProductNotFound->defaultMessage(),
                ApiErrorCode::ProductNotFound,
                404
            );
        }

        $this->removeAction->execute($customer, $productId);

        return ApiResponse::success(
            ['product_id' => $productId],
            'تمت إزالة المنتج من المفضلة بنجاح.'
        );
    }
}
