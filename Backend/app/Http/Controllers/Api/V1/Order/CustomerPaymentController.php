<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Order;

use App\Actions\Payments\ConfirmOnlinePaymentAction;
use App\Actions\Payments\InitiateOnlinePaymentAction;
use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\ConfirmCustomerPaymentRequest;
use App\Http\Requests\Api\V1\Order\InitiateCustomerPaymentRequest;
use App\Http\Resources\Api\V1\Order\CustomerOrderResource;
use App\Http\Resources\Api\V1\Order\PaymentAttemptResource;
use App\Models\Customer;
use App\Models\PaymentAttempt;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

final class CustomerPaymentController extends Controller
{
    public function __construct(
        private readonly InitiateOnlinePaymentAction $initiatePayment,
        private readonly ConfirmOnlinePaymentAction $confirmPayment,
    ) {}

    public function initiate(InitiateCustomerPaymentRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        try {
            $attempt = $this->initiatePayment->execute($customer, $request->validated());
            $attempt->load('paymentMethod');

            return ApiResponse::success(
                new PaymentAttemptResource($attempt),
                'تم بدء عملية الدفع بنجاح. أدخل رمز التحقق لإتمام الطلب.'
            );
        } catch (DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                code: $this->mapDomainExceptionToErrorCode($e->getMessage()),
                status: 422
            );
        }
    }

    public function confirm(ConfirmCustomerPaymentRequest $request, PaymentAttempt $paymentAttempt): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        try {
            $order = $this->confirmPayment->execute(
                $customer,
                $paymentAttempt,
                (string) $request->validated('otp')
            );

            return ApiResponse::created(
                new CustomerOrderResource($order),
                'تم تأكيد الدفع وإنشاء الطلب بنجاح.'
            );
        } catch (DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                code: $this->mapDomainExceptionToErrorCode($e->getMessage()),
                status: 422
            );
        }
    }

    private function mapDomainExceptionToErrorCode(string $message): ApiErrorCode
    {
        if (str_contains($message, 'فشل الدفع')
            || str_contains($message, 'رمز التحقق')
            || str_contains($message, 'جيب')
            || str_contains($message, 'فلوسك')) {
            return ApiErrorCode::PaymentFailed;
        }

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

        if (str_contains($message, 'الكوبون')) {
            return ApiErrorCode::CouponNotEligible;
        }

        return ApiErrorCode::ValidationFailed;
    }
}
