<?php

namespace App\Actions;

use App\Data\ChangePasswordData;
use App\Models\User;
use App\Services\Admin\AdminSessionManager;
use App\Services\AuditLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

final class ChangeOwnPasswordAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AdminSessionManager $adminSessions,
    ) {}

    public function execute(User $user, ChangePasswordData $data, ?string $currentSessionId = null): void
    {
        if (! Hash::check($data->currentPassword, $user->password)) {
            throw new AuthenticationException('Current password is invalid.');
        }

        $user->update(['password' => $data->password]);
        $user->tokens()->delete();

        if ($currentSessionId !== null && $currentSessionId !== '') {
            $this->adminSessions->revokeOtherSessions($user, $currentSessionId, $user);
        } else {
            $this->adminSessions->revokeAllSessionsForUser($user, $user);
        }

        $this->auditLogger->record('admin.password.changed', $user);
    }
}
