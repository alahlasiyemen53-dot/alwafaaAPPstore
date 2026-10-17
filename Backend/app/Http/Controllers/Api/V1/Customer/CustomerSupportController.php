<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Support\CustomerSupportResource;
use App\Services\CustomerSupportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CustomerSupportController extends Controller
{
    public function index(CustomerSupportService $supportService): JsonResponse
    {
        $settings = $supportService->getSettings();

        return ApiResponse::success(
            new CustomerSupportResource($settings),
            'تم استرجاع معلومات الدعم الفني بنجاح.'
        );
    }
}
