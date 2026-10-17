<?php

namespace App\Http\Controllers;

use App\Actions\CreateAdminUserAction;
use App\Actions\UpdateAdminUserAction;
use App\Data\CreateAdminUserData;
use App\Data\UpdateAdminUserData;
use App\Http\Requests\Admin\CreateAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AdminSessionManager;
use App\Services\AdminAuthorizationService;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminUserWebController extends Controller
{
    public function index(Request $request): View
    {
        $requestedSort = (string) $request->query('sort', 'created_at');
        $sort = in_array($requestedSort, ['name', 'created_at', 'last_login_at'], true) ? $requestedSort : 'created_at';
        $direction = $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $users = User::query()->with('roles')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('role'), fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role')->toString())))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->orderBy($sort, $direction)->paginate(20)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('display_name')->get(['name', 'display_name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['adminUser' => null, 'roles' => $this->assignableRoles()]);
    }

    public function store(CreateAdminUserRequest $request, CreateAdminUserAction $action): RedirectResponse
    {
        $data = $request->validated();
        $action->execute($request->user(), new CreateAdminUserData(
            $data['name'], $data['email'] ?? null, $data['phone'] ?? null, $data['password'],
            $request->boolean('is_active'), $data['roles'],
        ));

        return redirect()->route('admin.users.web.index')->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['adminUser' => $user->load('roles'), 'roles' => $this->assignableRoles()]);
    }

    public function update(UpdateAdminUserRequest $request, User $user, UpdateAdminUserAction $action): RedirectResponse
    {
        $data = $request->validated();
        $action->execute($request->user(), $user, new UpdateAdminUserData(
            $data['name'], $data['email'] ?? null, $data['phone'] ?? null, $request->boolean('is_active'), $data['roles'],
        ));

        return redirect()->route('admin.users.web.index')->with('success', 'تم تحديث المستخدم بنجاح.');
    }

    public function activate(Request $request, User $user, AdminAuthorizationService $authorization, AuditLogger $audit): RedirectResponse
    {
        try {
            $authorization->assertCanModifyUser($request->user(), $user);
            $user->update(['is_active' => true]);
            $audit->record('admin.user.activated', $user, $request);
        } catch (AuthorizationException|ConflictHttpException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تفعيل المستخدم.');
    }

    public function deactivate(
        Request $request,
        User $user,
        AdminAuthorizationService $authorization,
        AdminSessionManager $adminSessions,
        AuditLogger $audit,
    ): RedirectResponse {
        try {
            $authorization->assertCanDeactivate($request->user(), $user);
            $user->update(['is_active' => false]);
            $user->tokens()->delete();
            $adminSessions->revokeAllSessionsForUser($user, $request->user());
            $audit->record('admin.user.deactivated', $user, $request);
        } catch (AuthorizationException|ConflictHttpException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تعطيل المستخدم وإنهاء جلساته.');
    }

    private function assignableRoles(): Collection
    {
        return Role::query()
            ->when(! request()->user()->hasRole('super_admin'), fn ($query) => $query->where('name', '!=', 'super_admin'))
            ->orderBy('display_name')->get(['name', 'display_name']);
    }
}
