<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppReleaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppVersionController extends Controller
{
    public function show(AppReleaseService $appReleaseService): JsonResponse
    {
        return $this->respond($appReleaseService->getReleaseInfo());
    }

    /**
     * Client reports its installed version (auto-bumps latest when newer),
     * then receives store URLs + resolved latest for the update gate.
     */
    public function check(Request $request, AppReleaseService $appReleaseService): JsonResponse
    {
        $clientVersion = $request->input('client_version', $request->query('client_version'));
        $appReleaseService->recordClientVersion(
            is_string($clientVersion) ? $clientVersion : null
        );

        return $this->respond($appReleaseService->getReleaseInfo());
    }

    /**
     * @param  array{
     *     latest_version: ?string,
     *     min_version?: ?string,
     *     is_force?: bool,
     *     android_store_url: ?string,
     *     ios_store_url: ?string
     * }  $info
     */
    private function respond(array $info): JsonResponse
    {
        return ApiResponse::success([
            'latest_version' => $info['latest_version'],
            'min_version' => $info['min_version'] ?? null,
            'is_force' => (bool) ($info['is_force'] ?? false),
            'android_store_url' => $info['android_store_url'],
            'ios_store_url' => $info['ios_store_url'],
        ]);
    }
}
