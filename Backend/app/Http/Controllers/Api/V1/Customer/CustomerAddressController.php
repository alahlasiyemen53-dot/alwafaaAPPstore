<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCustomerAddressRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerAddressRequest;
use App\Http\Resources\Api\V1\Customer\CustomerAddressResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CustomerAddressController extends Controller
{
    /**
     * List authenticated customer addresses.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $addresses = CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->with(['country', 'governorate'])
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(
            CustomerAddressResource::collection($addresses),
            'تم جلب عناوين التوصيل بنجاح.'
        );
    }

    /**
     * Store a new address for the authenticated customer.
     */
    public function store(StoreCustomerAddressRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $address = DB::transaction(function () use ($request, $customer) {
            $hasExisting = CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->exists();

            $isDefault = ! $hasExisting || $request->boolean('is_default');

            if ($isDefault) {
                CustomerAddress::query()
                    ->where('customer_id', $customer->id)
                    ->update(['is_default' => false]);
            }

            $data = $this->normalizeAddressPayload($request->validated());
            $data['customer_id'] = $customer->id;
            $data['is_default'] = $isDefault;

            if (empty($data['contact_name'])) {
                $data['contact_name'] = $customer->name;
            }
            if (empty($data['contact_phone'])) {
                $data['contact_phone'] = $customer->phone;
            }

            /** @var CustomerAddress $newAddress */
            $newAddress = CustomerAddress::query()->create($data);

            return $newAddress->load(['country', 'governorate']);
        });

        return ApiResponse::success(
            new CustomerAddressResource($address),
            'تمت إضافة العنوان بنجاح.',
            201
        );
    }

    /**
     * View a single customer address with BOLA isolation.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        /** @var CustomerAddress|null $address */
        $address = CustomerAddress::query()
            ->where('id', $id)
            ->where('customer_id', $customer->id)
            ->with(['country', 'governorate'])
            ->first();

        if (! $address) {
            return ApiResponse::error(
                message: 'العنوان المطلوب غير موجود.',
                code: ApiErrorCode::ResourceNotFound,
                status: 404
            );
        }

        return ApiResponse::success(
            new CustomerAddressResource($address),
            'تم جلب تفاصيل العنوان بنجاح.'
        );
    }

    /**
     * Update an address with BOLA isolation.
     */
    public function update(UpdateCustomerAddressRequest $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        /** @var CustomerAddress|null $address */
        $address = CustomerAddress::query()
            ->where('id', $id)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $address) {
            return ApiResponse::error(
                message: 'العنوان المطلوب غير موجود.',
                code: ApiErrorCode::ResourceNotFound,
                status: 404
            );
        }

        $updatedAddress = DB::transaction(function () use ($request, $customer, $address) {
            $data = $this->normalizeAddressPayload($request->validated(), $address);

            if ($request->has('is_default') && $request->boolean('is_default')) {
                CustomerAddress::query()
                    ->where('customer_id', $customer->id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($data);

            return $address->fresh(['country', 'governorate']);
        });

        return ApiResponse::success(
            new CustomerAddressResource($updatedAddress),
            'تم تحديث العنوان بنجاح.'
        );
    }

    /**
     * Delete an address with BOLA isolation.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        /** @var CustomerAddress|null $address */
        $address = CustomerAddress::query()
            ->where('id', $id)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $address) {
            return ApiResponse::error(
                message: 'العنوان المطلوب غير موجود.',
                code: ApiErrorCode::ResourceNotFound,
                status: 404
            );
        }

        DB::transaction(function () use ($customer, $address) {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                /** @var CustomerAddress|null $nextDefault */
                $nextDefault = CustomerAddress::query()
                    ->where('customer_id', $customer->id)
                    ->latest('id')
                    ->first();

                $nextDefault?->update(['is_default' => true]);
            }
        });

        return ApiResponse::success(null, 'تم حذف العنوان بنجاح.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAddressPayload(array $data, ?CustomerAddress $existing = null): array
    {
        $clearAddress = trim((string) ($data['formatted_address'] ?? $data['street'] ?? ''));
        if ($clearAddress === '' && $existing !== null) {
            $clearAddress = trim((string) ($existing->formatted_address ?: $existing->street ?: ''));
        }

        if ($clearAddress !== '') {
            if (empty($data['formatted_address'])) {
                $data['formatted_address'] = $clearAddress;
            }
            if (empty($data['street'])) {
                $data['street'] = $clearAddress;
            }
        }

        if (empty($data['country_id']) && $existing?->country_id !== null) {
            $data['country_id'] = $existing->country_id;
        }

        unset($data['city_id'], $data['district_id']);

        return $data;
    }
}
