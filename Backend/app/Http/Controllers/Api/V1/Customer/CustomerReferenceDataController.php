<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\CustomerReferenceCountryResource;
use App\Http\Resources\Api\V1\Customer\CustomerReferenceCurrencyResource;
use App\Http\Resources\Api\V1\Customer\CustomerReferenceGovernorateResource;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Governorate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerReferenceDataController extends Controller
{
    /**
     * List all active countries.
     */
    public function countries(Request $request): JsonResponse
    {
        $query = Country::query()->where('is_active', true);
        if ($request->boolean('delivery_only') || $request->boolean('is_delivery_active')) {
            $query->where('is_delivery_active', true);
        }

        $countries = $query
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            CustomerReferenceCountryResource::collection($countries),
            'تم جلب قائمة الدول بنجاح.'
        );
    }

    /**
     * List active governorates for a country.
     */
    public function governoratesByCountry(Request $request, int $countryId): JsonResponse
    {
        $country = Country::query()->where('id', $countryId)->where('is_active', true)->first();

        if (! $country) {
            return ApiResponse::error(
                message: 'الدولة المحددة غير موجودة أو غير نشطة.',
                code: ApiErrorCode::ResourceNotFound,
                status: 404
            );
        }

        $query = Governorate::query()
            ->where('country_id', $countryId)
            ->where('is_active', true);

        if ($request->boolean('delivery_only') || $request->boolean('is_delivery_active')) {
            $query->where('is_delivery_active', true);
        }

        $governorates = $query
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            CustomerReferenceGovernorateResource::collection($governorates),
            'تم جلب قائمة المحافظات بنجاح.'
        );
    }

    /**
     * List all active governorates with fixed delivery fee metadata.
     *
     * @deprecated Prefer GET /countries/{id}/governorates for new clients.
     */
    public function governorates(Request $request): JsonResponse
    {
        $governorates = Governorate::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            CustomerReferenceGovernorateResource::collection($governorates),
            'تم جلب قائمة المحافظات بنجاح.'
        );
    }

    /**
     * @deprecated City hierarchy is no longer used in customer address flows.
     */
    public function cities(Request $request, int $governorateId): JsonResponse
    {
        return ApiResponse::success(
            [],
            'قائمة المدن لم تعد مستخدمة في تطبيق العميل. يرجى الاعتماد على المحافظة والعنوان التفصيلي.'
        );
    }

    /**
     * @deprecated District hierarchy is no longer used in customer address flows.
     */
    public function districts(Request $request, int $cityId): JsonResponse
    {
        return ApiResponse::success(
            [],
            'قائمة المديريات لم تعد مستخدمة في تطبيق العميل. يرجى الاعتماد على المحافظة والعنوان التفصيلي.'
        );
    }

    /**
     * List active store currencies from backend configuration.
     */
    public function currencies(Request $request): JsonResponse
    {
        $currencies = Currency::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            CustomerReferenceCurrencyResource::collection($currencies),
            'تم جلب قائمة العملات المعتمدة بنجاح.'
        );
    }
}
