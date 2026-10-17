<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\AuthenticateAdminAction;
use App\Actions\ChangeOwnPasswordAction;
use App\Data\ChangePasswordData;
use App\Data\LoginData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangePasswordRequest;
use App\Http\Requests\Admin\LoginRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Http\Resources\AdminUserResource;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthenticateAdminAction $action): JsonResponse
    {
        [$user, $token] = $action->execute(new LoginData($request->string('identifier')->toString(), $request->string('password')->toString()));

        return ApiResponse::success(['user' => AdminUserResource::make($user->load('roles'))->resolve(), 'token' => $token]);
    }

    public function logout(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $user = $request->user();
        $auditLogger->record('admin.logout', $user, $request);
        PersonalAccessToken::findToken($request->bearerToken())?->delete();

        return ApiResponse::success(null, 'Logged out.');
    }

    public function profile(Request $request): JsonResponse
    {
        return ApiResponse::success(AdminUserResource::make($request->user()->load('roles'))->resolve());
    }

    public function updateProfile(Request $request, UpdateProfileRequest $profileRequest, AuditLogger $auditLogger): JsonResponse
    {
        $user = $request->user();
        $user->update($profileRequest->validated());
        $auditLogger->record('admin.profile.updated', $user, $request);

        return ApiResponse::success(AdminUserResource::make($user->fresh('roles'))->resolve());
    }

    public function changePassword(Request $request, ChangePasswordRequest $passwordRequest, ChangeOwnPasswordAction $action): JsonResponse
    {
        $action->execute($request->user(), new ChangePasswordData($passwordRequest->string('current_password')->toString(), $passwordRequest->string('password')->toString()));

        return ApiResponse::success(null, 'Password changed. Please sign in again.');
    }
}
