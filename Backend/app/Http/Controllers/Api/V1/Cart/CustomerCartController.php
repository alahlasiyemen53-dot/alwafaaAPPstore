<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cart;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cart\AddCartItemRequest;
use App\Http\Requests\Api\V1\Cart\ApplyCouponRequest;
use App\Http\Requests\Api\V1\Cart\UpdateCartItemRequest;
use App\Http\Resources\Api\V1\Cart\CustomerCartResource;
use App\Models\Customer;
use App\Services\CustomerCartService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerCartController extends Controller
{
    public function __construct(
        private readonly CustomerCartService $cartService,
    ) {}

    /**
     * Get the current customer's cart with server-calculated prices and discounts.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $cartData = $this->cartService->getCartDetails($customer);

        return ApiResponse::success(
            new CustomerCartResource($cartData),
            'تم جلب بيانات السلة بنجاح.'
        );
    }

    /**
     * Add a variant item to the cart.
     */
    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $result = $this->cartService->addItem(
            customer: $customer,
            variantId: (int) $request->validated('product_variant_id'),
            quantity: (int) $request->validated('quantity', 1),
        );

        if (! $result['success']) {
            $status = match ($result['error_code']) {
                ApiErrorCode::ProductVariantNotFound => 404,
                default => 422,
            };

            return ApiResponse::error(
                message: $result['message'] ?? 'فشل إضافة العنصر إلى السلة.',
                code: $result['error_code'] ?? ApiErrorCode::ValidationFailed,
                status: $status
            );
        }

        $cartData = $this->cartService->getCartDetails($customer);

        return ApiResponse::success(
            new CustomerCartResource($cartData),
            'تمت إضافة العنصر إلى السلة بنجاح.',
            201
        );
    }

    /**
     * Update quantity of an item in the cart.
     */
    public function updateItem(UpdateCartItemRequest $request, int $item): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $result = $this->cartService->updateItem(
            customer: $customer,
            itemId: $item,
            quantity: (int) $request->validated('quantity'),
        );

        if (! $result['success']) {
            $status = match ($result['error_code']) {
                ApiErrorCode::CartItemNotFound => 404,
                default => 422,
            };

            return ApiResponse::error(
                message: $result['message'] ?? 'فشل تحديث عنصر السلة.',
                code: $result['error_code'] ?? ApiErrorCode::CartItemNotFound,
                status: $status
            );
        }

        $cartData = $this->cartService->getCartDetails($customer);

        return ApiResponse::success(
            new CustomerCartResource($cartData),
            'تم تحديث كمية العنصر في السلة بنجاح.'
        );
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(Request $request, int $item): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $result = $this->cartService->removeItem(
            customer: $customer,
            itemId: $item,
        );

        if (! $result['success']) {
            return ApiResponse::error(
                message: $result['message'] ?? 'عنصر السلة غير موجود.',
                code: $result['error_code'] ?? ApiErrorCode::CartItemNotFound,
                status: 404
            );
        }

        $cartData = $this->cartService->getCartDetails($customer);

        return ApiResponse::success(
            new CustomerCartResource($cartData),
            'تم حذف العنصر من السلة بنجاح.'
        );
    }

    /**
     * Clear all items and coupon from the cart.
     */
    public function clear(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $this->cartService->clearCart($customer);
        $cartData = $this->cartService->getCartDetails($customer);

        return ApiResponse::success(
            new CustomerCartResource($cartData),
            'تم إفراغ السلة بنجاح.'
        );
    }

    /**
     * Apply a coupon code to the cart.
     */
    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $result = $this->cartService->applyCoupon(
            customer: $customer,
            code: (string) $request->validated('code')
        );

        if (! $result['success']) {
            return ApiResponse::error(
                message: $result['message'] ?? 'الكوبون غير صالح.',
                code: $result['error_code'] ?? ApiErrorCode::CouponInvalid,
                status: 422
            );
        }

        $cartData = $this->cartService->getCartDetails($customer);

        return ApiResponse::success(
            new CustomerCartResource($cartData),
            $result['message'] ?? 'تم تطبيق الكوبون بنجاح.'
        );
    }

    /**
     * Remove the applied coupon from the cart.
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $this->cartService->removeCoupon($customer);
        $cartData = $this->cartService->getCartDetails($customer);

        return ApiResponse::success(
            new CustomerCartResource($cartData),
            'تمت إزالة الكوبون من السلة بنجاح.'
        );
    }
}
