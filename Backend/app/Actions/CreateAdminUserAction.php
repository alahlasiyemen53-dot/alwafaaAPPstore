<?php

namespace App\Actions;

use App\Data\CreateAdminUserData;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuthorizationService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CreateAdminUserAction
{
    public function __construct(
        private readonly AdminAuthorizationService $authorization,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $actor, CreateAdminUserData $data): User
    {
        $this->authorization->assertCanAssignRoles($actor, $data->roles);

        return DB::transaction(function () use ($data): User {
            $roles = Role::query()->whereIn('name', $data->roles)->get();
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'password' => $data->password,
                'is_active' => $data->isActive,
            ]);
            $user->syncRoles($roles->all());
            $this->auditLogger->record('admin.user.created', $user, metadata: ['roles' => $data->roles]);

            return $user->load('roles');
        });
    }
}
