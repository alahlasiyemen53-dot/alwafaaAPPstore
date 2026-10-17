<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Home\CustomerHomeResource;
use App\Models\Customer;
use App\Queries\Catalog\CustomerHomeQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerHomeController extends Controller
{
    public function index(Request $request, CustomerHomeQuery $query): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = $request->user('customer_jwt');
        $homeData = $query->execute($customer);

        return ApiResponse::success(
            new CustomerHomeResource($homeData),
            'تم جلب محتوى الصفحة الرئيسية بنجاح.'
        );
    }
}
