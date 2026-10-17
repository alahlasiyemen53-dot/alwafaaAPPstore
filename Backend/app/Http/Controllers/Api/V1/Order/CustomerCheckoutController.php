<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Order;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\CheckoutPreviewRequest;
use App\Http\Requests\Api\V1\Order\CreateCustomerOrderRequest;
use App\Http\Resources\Api\V1\Order\CheckoutPreviewResource;
use App\Http\Resources\Api\V1\Order\CustomerOrderResource;
use App\Models\Customer;
use App\Services\CustomerCheckoutService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

final class CustomerCheckoutController extends Controller
{
    public function __construct(
        private readonly CustomerCheckoutService $checkoutService,
    ) {}

    /**
     * Compute authoritative informational preview of checkout.
     * Informational only: preview ≠ reservation. Does NOT lock inventory or alter cart.
     */
    public function preview(CheckoutPreviewRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        try {
            $preview = $this->checkoutService->preview($customer, $request->validated());

            return ApiResponse::success(
                new CheckoutPreviewResource($preview),
                'تم جلب تفاصيل معاينة الطلب بنجاح.'
            );
        } catch (DomainException $e) {
            $errorCode = $this->mapDomainExceptionToErrorCode($e->getMessage());

            return ApiResponse::error(
                message: $e->getMessage(),
                code: $errorCode,
                status: 422
            );
        }
    }

    /**
     * Create and confirm order with idempotency protection.
     * Re-reads authoritative server truth, reserves STOCKED items,
     * creates ON_DEMAND sourcing, snapshots address, clears cart, and schedules async notification.
     */
    public function createOrder(CreateCustomerOrderRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        try {
            $order = $this->checkoutService->createOrder($customer, $request->validated());

            return ApiResponse::created(
                new CustomerOrderResource($order),
                'تم إنشاء وتأكيد الطلب بنجاح.'
            );
        } catch (DomainException $e) {
            $errorCode = $this->mapDomainExceptionToErrorCode($e->getMessage());

            return ApiResponse::error(
                message: $e->getMessage(),
                code: $errorCode,
                status: 422
            );
        }
    }

    /**
     * Map Arabic domain messages to structured API error codes.
     */
    private function mapDomainExceptionToErrorCode(string $message): ApiErrorCode
    {
        if (str_contains($message, 'الرصيد') || str_contains($message, 'غير كافٍ') || str_contains($message, 'المخزني')) {
            return ApiErrorCode::InsufficientStock;
        }

        if (str_contains($message, 'سلة المشتريات فارغة')) {
            return ApiErrorCode::CartEmpty;
        }

        if (str_contains($message, 'عنوان التوصيل')) {
            return ApiErrorCode::AddressNotFound;
        }

        if (str_contains($message, 'المحافظة')) {
            return ApiErrorCode::GovernorateInactive;
        }

        if (str_contains($message, 'طريقة الدفع')) {
            return ApiErrorCode::PaymentMethodUnsupported;
        }

        if (str_contains($message, 'فشل الدفع')
            || str_contains($message, 'رمز التحقق')
            || str_contains($message, 'جيب')
            || str_contains($message, 'فلوسك')) {
            return ApiErrorCode::PaymentFailed;
        }

        if (str_contains($message, 'الكوبون')) {
            return ApiErrorCode::CouponNotEligible;
        }

        return ApiErrorCode::ValidationFailed;
    }
}
