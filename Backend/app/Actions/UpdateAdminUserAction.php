<?php

namespace App\Actions;

use App\Data\UpdateAdminUserData;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuthorizationService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class UpdateAdminUserAction
{
    public function __construct(
        private readonly AdminAuthorizationService $authorization,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $actor, User $target, UpdateAdminUserData $data): User
    {
        $this->authorization->assertCanModifyUser($actor, $target);
        $this->authorization->assertCanRemoveSuperAdminRole($actor, $target, $data->roles);

        return DB::transaction(function () use ($target, $data): User {
            $roles = Role::query()->whereIn('name', $data->roles)->get();
            $target->update([
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'is_active' => $data->isActive,
            ]);
            $target->syncRoles($roles->all());
            $this->auditLogger->record('admin.user.updated', $target, metadata: ['roles' => $data->roles]);

            return $target->load('roles');
        });
    }
}
