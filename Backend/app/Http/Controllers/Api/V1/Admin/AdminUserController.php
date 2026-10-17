<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\CreateAdminUserAction;
use App\Actions\UpdateAdminUserAction;
use App\Data\CreateAdminUserData;
use App\Data\UpdateAdminUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignUserRolesRequest;
use App\Http\Requests\Admin\CreateAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminAuthorizationService;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requestedSort = (string) $request->query('sort', 'created_at');
        $sort = in_array($requestedSort, ['name', 'created_at', 'last_login_at'], true) ? $requestedSort : 'created_at';
        $direction = $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $users = User::query()->with('roles')->when($request->filled('search'), function ($query) use ($request): void {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
        })->when($request->filled('role'), fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role')->toString())))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($sort, $direction)
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ApiResponse::success(['items' => AdminUserResource::collection($users->items())->resolve(), 'meta' => ['current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'per_page' => $users->perPage(), 'total' => $users->total()]]);
    }

    public function store(CreateAdminUserRequest $request, CreateAdminUserAction $action): JsonResponse
    {
        $data = $request->validated();
        $user = $action->execute($request->user(), new CreateAdminUserData($data['name'], $data['email'] ?? null, $data['phone'] ?? null, $data['password'], $data['is_active'] ?? true, $data['roles']));

        return ApiResponse::success(AdminUserResource::make($user)->resolve(), 'User created.', 201);
    }

    public function update(UpdateAdminUserRequest $request, User $user, UpdateAdminUserAction $action): JsonResponse
    {
        $data = $request->validated();
        $updated = $action->execute($request->user(), $user, new UpdateAdminUserData($data['name'], $data['email'] ?? null, $data['phone'] ?? null, $data['is_active'], $data['roles']));

        return ApiResponse::success(AdminUserResource::make($updated)->resolve(), 'User updated.');
    }

    public function deactivate(Request $request, User $user, AdminAuthorizationService $authorization, AuditLogger $auditLogger): JsonResponse
    {
        $authorization->assertCanDeactivate($request->user(), $user);
        $user->update(['is_active' => false]);
        $auditLogger->record('admin.user.deactivated', $user, $request);

        return ApiResponse::success(AdminUserResource::make($user->fresh('roles'))->resolve(), 'User deactivated.');
    }

    public function activate(Request $request, User $user, AdminAuthorizationService $authorization, AuditLogger $auditLogger): JsonResponse
    {
        $authorization->assertCanModifyUser($request->user(), $user);
        $user->update(['is_active' => true]);
        $auditLogger->record('admin.user.activated', $user, $request);

        return ApiResponse::success(AdminUserResource::make($user->fresh('roles'))->resolve(), 'User activated.');
    }

    public function roles(AssignUserRolesRequest $request, User $user, AdminAuthorizationService $authorization, AuditLogger $auditLogger): JsonResponse
    {
        $actor = $request->user();
        $roles = $request->validated('roles');
        $authorization->assertCanModifyUser($actor, $user);
        $authorization->assertCanRemoveSuperAdminRole($actor, $user, $roles);

        DB::transaction(fn () => $user->syncRoles($roles));
        $auditLogger->record('admin.user.roles.updated', $user, $request, ['roles' => $roles]);

        return ApiResponse::success(AdminUserResource::make($user->fresh('roles'))->resolve(), 'User roles updated.');
    }
}
