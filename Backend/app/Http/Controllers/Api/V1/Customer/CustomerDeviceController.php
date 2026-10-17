<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Data\Notifications\PushDeviceRegistrationData;
use App\Enums\ApiErrorCode;
use App\Enums\PushDevicePlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Devices\RegisterPushDeviceRequest;
use App\Http\Resources\Api\V1\Devices\PushDeviceResource;
use App\Models\Customer;
use App\Models\PushDevice;
use App\Services\Notifications\PushDeviceLifecycleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerDeviceController extends Controller
{
    public function register(RegisterPushDeviceRequest $request, PushDeviceLifecycleService $lifecycleService): JsonResponse
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

        $platform = PushDevicePlatform::from((string) $request->input('platform'));

        $registrationData = new PushDeviceRegistrationData(
            token: (string) $request->input('fcm_token'),
            platform: $platform,
            deviceIdentifier: $request->input('device_identifier'),
            locale: $request->input('locale'),
            appVersion: $request->input('app_version'),
        );

        $device = $lifecycleService->register($customer, $registrationData);

        return ApiResponse::success(
            new PushDeviceResource($device),
            'تم تسجيل جهاز الإشعارات بنجاح.'
        );
    }

    public function destroy(int|string $device, Request $request, PushDeviceLifecycleService $lifecycleService): JsonResponse
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

        /** @var PushDevice|null $pushDevice */
        $pushDevice = PushDevice::query()->find($device);

        // BOLA Defense: device must exist and belong to the authenticated customer
        if (
            $pushDevice === null
            || $pushDevice->owner_type !== Customer::class
            || (int) $pushDevice->owner_id !== (int) $customer->id
        ) {
            return ApiResponse::error(
                message: ApiErrorCode::DeviceNotFound->defaultMessage(),
                code: ApiErrorCode::DeviceNotFound,
                status: 404
            );
        }

        $lifecycleService->detach($pushDevice);

        return ApiResponse::success(
            null,
            'تم إلغاء تفعيل جهاز الإشعارات بنجاح.'
        );
    }
}
