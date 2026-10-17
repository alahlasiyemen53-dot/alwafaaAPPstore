<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AssignRolePermissionsRequest;
use App\Http\Requests\Admin\CreateRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AdminAuthorizationService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AdminRoleWebController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', ['roles' => Role::query()->withCount(['permissions', 'users'])->orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        return view('admin.roles.form', ['role' => null, 'permissionGroups' => $this->permissionGroups()]);
    }

    public function store(CreateRoleRequest $request, AuditLogger $audit): RedirectResponse
    {
        $role = Role::query()->create($request->validated());
        $audit->record('admin.role.created', $role, $request);

        return redirect()->route('admin.roles.web.index')->with('success', 'تم إنشاء الدور بنجاح.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', ['role' => $role->load('permissions'), 'permissionGroups' => $this->permissionGroups()]);
    }

    public function update(UpdateRoleRequest $request, Role $role, AdminAuthorizationService $authorization, AuditLogger $audit): RedirectResponse
    {
        $authorization->assertCanModifyRole($request->user(), $role);
        $role->update($request->validated());
        $audit->record('admin.role.updated', $role, $request);

        return back()->with('success', 'تم تحديث بيانات الدور.');
    }

    public function permissions(AssignRolePermissionsRequest $request, Role $role, AdminAuthorizationService $authorization, AuditLogger $audit): RedirectResponse
    {
        $authorization->assertCanModifyRole($request->user(), $role);
        $names = array_values($request->validated('permissions') ?? []);

        DB::transaction(function () use ($role, $names): void {
            // Must use syncPermissions (not permissions()->sync) so Laratrust
            // flushes role permission cache — otherwise checks keep stale data.
            $role->syncPermissions($names);
            $this->flushUsersCacheForRole($role);
        });

        $audit->record('admin.role.permissions.updated', $role, $request, ['permissions' => $names]);

        return back()->with('success', 'تم تحديث صلاحيات الدور.');
    }

    private function flushUsersCacheForRole(Role $role): void
    {
        $role->users()->each(function ($user): void {
            if (method_exists($user, 'flushCache')) {
                $user->flushCache();
            }
        });
    }

    public function permissionIndex(Request $request): View
    {
        $permissions = Permission::query()->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.permissions.index', ['permissions' => $permissions]);
    }

    private function permissionGroups(): array
    {
        return Permission::query()->orderBy('name')->get()->groupBy(
            fn (Permission $permission) => explode('.', $permission->name)[1] ?? 'general'
        )->all();
    }
}
