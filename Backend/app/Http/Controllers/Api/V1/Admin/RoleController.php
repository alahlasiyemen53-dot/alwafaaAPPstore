<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRolePermissionsRequest;
use App\Http\Requests\Admin\CreateRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\AdminAuthorizationService;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()->withCount('permissions')->orderBy('name')->paginate(50);

        return ApiResponse::success(['items' => RoleResource::collection($roles->items())->resolve(), 'meta' => ['current_page' => $roles->currentPage(), 'last_page' => $roles->lastPage(), 'total' => $roles->total()]]);
    }

    public function store(CreateRoleRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        $role = Role::create($request->validated());
        $auditLogger->record('admin.role.created', $role, $request);

        return ApiResponse::success(RoleResource::make($role)->resolve(), 'Role created.', 201);
    }

    public function update(UpdateRoleRequest $request, Role $role, AdminAuthorizationService $authorization, AuditLogger $auditLogger): JsonResponse
    {
        $authorization->assertCanModifyRole($request->user(), $role);
        $role->update($request->validated());
        $auditLogger->record('admin.role.updated', $role, $request);

        return ApiResponse::success(RoleResource::make($role)->resolve(), 'Role updated.');
    }

    public function permissions(AssignRolePermissionsRequest $request, Role $role, AdminAuthorizationService $authorization, AuditLogger $auditLogger): JsonResponse
    {
        $authorization->assertCanModifyRole($request->user(), $role);
        $names = array_values($request->validated('permissions') ?? []);

        DB::transaction(function () use ($role, $names): void {
            $role->syncPermissions($names);
            $role->users()->each(function ($user): void {
                if (method_exists($user, 'flushCache')) {
                    $user->flushCache();
                }
            });
        });

        $auditLogger->record('admin.role.permissions.updated', $role, $request, ['permissions' => $names]);

        return ApiResponse::success(RoleResource::make($role->fresh('permissions'))->resolve(), 'Permissions updated.');
    }
}
