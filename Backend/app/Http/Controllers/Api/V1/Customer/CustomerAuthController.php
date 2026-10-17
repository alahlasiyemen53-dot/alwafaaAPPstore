<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\Auth\ResolveOrCreateCustomerFromOtpAction;
use App\Actions\Otp\RequestCustomerOtpAction;
use App\Actions\Otp\VerifyCustomerOtpAction;
use App\Enums\ApiErrorCode;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RequestCustomerOtpRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerProfileRequest;
use App\Http\Requests\Api\V1\Customer\VerifyCustomerOtpRequest;
use App\Http\Resources\Api\V1\Customer\CustomerAuthTokenResource;
use App\Http\Resources\Api\V1\Customer\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerOtpChallenge;
use App\Services\Auth\CustomerJwtService;
use App\Support\ApiResponse;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    /**
     * Request an OTP for customer login/registration.
     */
    public function requestOtp(RequestCustomerOtpRequest $request, RequestCustomerOtpAction $action): JsonResponse
    {
        $phone = (string) $request->input('phone');
        $countryId = $request->filled('country_id') ? $request->integer('country_id') : null;

        $challenge = $action->execute(
            phone: $phone,
            purpose: OtpPurpose::Login,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            countryId: $countryId,
        );

        $normalizedPhone = PhoneNormalizer::normalize(
            $phone,
            \App\Support\CountryCallingCode::forCountryId($countryId)
        );
        $isNewCustomer = ! Customer::query()->where('normalized_phone', $normalizedPhone)->exists();

        return ApiResponse::success([
            'challenge_id' => $challenge->challenge_id,
            'expires_in' => max(0, (int) now()->diffInSeconds($challenge->expires_at)),
            'resend_after' => (int) config('otp.resend_cooldown_seconds', 60),
            'is_new_customer' => $isNewCustomer,
        ], 'تم إرسال رمز التحقق بنجاح.');
    }

    /**
     * Verify OTP and authenticate/register customer, returning a JWT token.
     */
    public function verifyOtp(
        VerifyCustomerOtpRequest $request,
        VerifyCustomerOtpAction $verifyAction,
        ResolveOrCreateCustomerFromOtpAction $resolveCustomerAction,
        CustomerJwtService $jwtService
    ): JsonResponse {
        $countryId = $request->filled('country_id') ? $request->integer('country_id') : null;
        $normalizedPhone = PhoneNormalizer::normalize(
            (string) $request->input('phone'),
            \App\Support\CountryCallingCode::forCountryId($countryId)
        );
        $challengeId = (string) $request->input('challenge_id');
        $code = (string) $request->input('code');
        $name = $request->input('name');

        // BOLA / Challenge ownership verification
        $challenge = CustomerOtpChallenge::query()
            ->where('challenge_id', $challengeId)
            ->first();

        if ($challenge === null || $challenge->normalized_phone !== $normalizedPhone) {
            return ApiResponse::error(
                message: 'طلب التحقق غير صالح أو لا يتطابق مع رقم الهاتف المدخل.',
                code: ApiErrorCode::OtpChallengeInvalid,
                status: 400
            );
        }

        // Verify the OTP challenge (increments attempts or marks consumed)
        $verifyAction->execute(
            challengeId: $challengeId,
            code: $code
        );

        // Resolve existing customer or create new one
        $customer = $resolveCustomerAction->execute(
            phone: (string) $request->input('phone'),
            name: $name ? (string) $name : null,
            countryId: $countryId,
            governorateId: $request->filled('governorate_id') ? $request->integer('governorate_id') : null,
        );
        $customer->loadMissing(['country', 'governorate']);

        // Issue JWT access token and refresh token
        $token = $jwtService->generateAccessToken($customer);
        $refreshToken = $jwtService->generateRefreshToken($customer);
        $ttlSeconds = (int) config('jwt.ttl', 86400) * 60;

        return ApiResponse::success(
            new CustomerAuthTokenResource([
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => $ttlSeconds,
                'customer' => $customer,
            ]),
            'تم تسجيل الدخول بنجاح.'
        );
    }

    /**
     * Get authenticated customer profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = $request->user('customer_jwt');

        if (! $customer instanceof Customer) {
            return ApiResponse::error(
                message: ApiErrorCode::AuthRequired->defaultMessage(),
                code: ApiErrorCode::AuthRequired,
                status: 401
            );
        }

        return ApiResponse::success(
            new CustomerResource($customer),
            'تم استرجاع الملف الشخصي بنجاح.'
        );
    }

    /**
     * Update authenticated customer profile (name, email).
     */
    public function updateProfile(UpdateCustomerProfileRequest $request): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = $request->user('customer_jwt');

        if (! $customer instanceof Customer) {
            return ApiResponse::error(
                message: ApiErrorCode::AuthRequired->defaultMessage(),
                code: ApiErrorCode::AuthRequired,
                status: 401
            );
        }

        $validated = $request->validated();
        $customer->fill(array_intersect_key($validated, array_flip(['name', 'email'])));
        $customer->save();

        return ApiResponse::success(
            new CustomerResource($customer->fresh()),
            'تم تحديث الملف الشخصي بنجاح.'
        );
    }

    /**
     * Invalidate/revoke current JWT session.
     */
    public function logout(Request $request, CustomerJwtService $jwtService): JsonResponse
    {
        $token = $jwtService->extractToken($request);

        if ($token !== null) {
            $jwtService->revokeToken($token);
        }

        return ApiResponse::success(null, 'تم تسجيل الخروج بنجاح.');
    }

    /**
     * Refresh access token using a valid refresh token.
     */
    public function refreshToken(Request $request, CustomerJwtService $jwtService): JsonResponse
    {
        $refreshToken = (string) $request->input('refresh_token');
        if (empty($refreshToken)) {
            return ApiResponse::error(
                message: 'رمز التجديد مطلوب.',
                code: ApiErrorCode::AuthRequired,
                status: 400
            );
        }

        try {
            $payload = $jwtService->decode($refreshToken, 'refresh');
            $customer = Customer::query()->find($payload->sub);

            if (! $customer instanceof Customer || ! $customer->is_active) {
                return ApiResponse::error(
                    message: 'المستخدم غير متاح أو الحساب معطل.',
                    code: ApiErrorCode::CustomerAccessRestricted,
                    status: 403
                );
            }

            $newToken = $jwtService->generateAccessToken($customer);
            $newRefreshToken = $jwtService->generateRefreshToken($customer);
            $ttlSeconds = (int) config('jwt.ttl', 86400) * 60;

            return ApiResponse::success(
                new CustomerAuthTokenResource([
                    'access_token' => $newToken,
                    'refresh_token' => $newRefreshToken,
                    'expires_in' => $ttlSeconds,
                    'customer' => $customer,
                ]),
                'تم تجديد الجلسة بنجاح.'
            );
        } catch (\Throwable $e) {
            return ApiResponse::error(
                message: 'رمز التجديد منتهي أو غير صالح.',
                code: ApiErrorCode::AuthRequired,
                status: 401
            );
        }
    }
}
