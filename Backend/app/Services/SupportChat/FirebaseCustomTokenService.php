<?php

declare(strict_types=1);

namespace App\Services\SupportChat;

use App\Models\Customer;
use App\Models\User;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Factory;
use RuntimeException;
use Throwable;

final class FirebaseCustomTokenService
{
    public function __construct(
        private readonly ?FirebaseAuth $auth,
        private readonly ?string $disabledReason = null,
    ) {}

    /** @param array<string, mixed> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        if (! ($configuration['enabled'] ?? false)) {
            return new self(null, 'Firebase is disabled.');
        }

        $path = $configuration['credentials_path'] ?? null;
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return new self(null, 'Firebase credentials missing.');
        }

        try {
            $auth = (new Factory)->withServiceAccount($path)->createAuth();

            return new self($auth);
        } catch (Throwable $e) {
            report($e);

            return new self(null, 'Firebase Auth could not be initialized.');
        }
    }

    public function isReady(): bool
    {
        return $this->auth !== null;
    }

    public function configurationIssue(): ?string
    {
        return $this->auth === null ? ($this->disabledReason ?? 'Firebase Auth unavailable.') : null;
    }

    /** @return array{token: string, uid: string, expires_in: int} */
    public function issueForCustomer(Customer $customer): array
    {
        $this->assertReady();
        $uid = 'customer:'.$customer->id;
        $token = $this->auth->createCustomToken($uid, [
            'role' => 'customer',
            'customer_id' => $customer->id,
        ]);

        return [
            'token' => $token->toString(),
            'uid' => $uid,
            'expires_in' => 3600,
        ];
    }

    /** @return array{token: string, uid: string, expires_in: int} */
    public function issueForAdmin(User $admin): array
    {
        $this->assertReady();

        if (! $admin->is_active) {
            throw new RuntimeException('Admin account is disabled.');
        }

        if (
            ! $admin->hasPermission('admin.support_chat.view')
            && ! $admin->hasPermission('admin.support_chat.view_all')
            && ! $admin->hasRole('super_admin')
        ) {
            throw new RuntimeException('Admin lacks support chat permission.');
        }

        $uid = 'admin:'.$admin->id;
        $token = $this->auth->createCustomToken($uid, [
            'role' => 'admin',
            'admin_id' => $admin->id,
            'can_view_all' => $admin->hasPermission('admin.support_chat.view_all')
                || $admin->hasRole('super_admin'),
        ]);

        return [
            'token' => $token->toString(),
            'uid' => $uid,
            'expires_in' => 3600,
        ];
    }

    private function assertReady(): void
    {
        if ($this->auth === null) {
            throw new RuntimeException($this->disabledReason ?? 'Firebase Auth unavailable.');
        }
    }
}
