<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(['items' => PermissionResource::collection(Permission::query()->orderBy('name')->get())->resolve()]);
    }
}
