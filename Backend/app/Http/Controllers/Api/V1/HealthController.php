<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HealthRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __invoke(HealthRequest $request): JsonResponse
    {
        $data = ['status' => 'ok', 'app' => config('app.name'), 'version' => 'v1'];

        if ($request->boolean('verbose')) {
            $data['environment'] = app()->environment();
        }

        return ApiResponse::success($data);
    }
}
