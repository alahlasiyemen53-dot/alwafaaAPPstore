<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Catalog\CustomerBrandResource;
use App\Models\Brand;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CustomerBrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en', 'slug', 'description', 'sort_order']);

        return ApiResponse::success(
            CustomerBrandResource::collection($brands),
            'تم جلب العلامات التجارية بنجاح.'
        );
    }

    public function show(Brand $brand): JsonResponse
    {
        abort_unless($brand->is_active, 404);

        $brand->load('media');

        return ApiResponse::success(
            new CustomerBrandResource($brand),
            'تم جلب العلامة التجارية بنجاح.'
        );
    }
}
