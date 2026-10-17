<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminAuthorizationService
{
    public function assertCanAssignRoles(User $actor, array $roles): void
    {
        if (in_array('super_admin', $roles, true) && ! $actor->hasRole('super_admin')) {
            throw new AuthorizationException('You cannot assign the super_admin role.');
        }
    }

    public function assertCanModifyUser(User $actor, User $target): void
    {
        if ($target->hasRole('super_admin') && ! $actor->hasRole('super_admin')) {
            throw new AuthorizationException('This user is protected.');
        }
    }

    public function assertCanModifyRole(User $actor, Role $role): void
    {
        if ($role->name === 'super_admin' && ! $actor->hasRole('super_admin')) {
            throw new AuthorizationException('This role is protected.');
        }
    }

    public function assertCanDeactivate(User $actor, User $target): void
    {
        $this->assertCanModifyUser($actor, $target);

        if ($actor->is($target)) {
            throw new AuthorizationException('You cannot deactivate your own account.');
        }

        if ($target->hasRole('super_admin') && User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->count() <= 1) {
            throw new ConflictHttpException('At least one active super_admin must remain.');
        }
    }

    public function assertCanRemoveSuperAdminRole(User $actor, User $target, array $roles): void
    {
        $this->assertCanAssignRoles($actor, $roles);

        if ($target->hasRole('super_admin') && ! in_array('super_admin', $roles, true)) {
            if (User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))
                ->count() <= 1) {
                throw new ConflictHttpException('At least one active super_admin must remain.');
            }

            if ($actor->is($target)) {
                throw new AuthorizationException('You cannot remove your own super_admin role.');
            }
        }
    }
}
