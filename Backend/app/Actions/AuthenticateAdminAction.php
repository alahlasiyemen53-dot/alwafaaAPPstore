<?php

namespace App\Actions;

use App\Data\LoginData;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

final class AuthenticateAdminAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(LoginData $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $user = User::query()
            ->where(fn ($query) => $query->where('email', $data->identifier)->orWhere('phone', $data->identifier))
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($data->password, $user->password)) {
            $this->auditLogger->record('admin.login.failed', null, metadata: ['identifier' => $data->identifier]);

            throw new AuthenticationException('Invalid credentials.');
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->auditLogger->record('admin.login.succeeded', $user, actor: $user);

        return [$user, $user->createToken('admin')->plainTextToken];
    }
}
