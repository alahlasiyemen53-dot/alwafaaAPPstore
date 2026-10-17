<?php

namespace App\Actions;

use App\Models\Account;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class ToggleAccountStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Account $account): Account
    {
        return DB::transaction(function () use ($account): Account {
            /** @var Account $lockedAccount */
            $lockedAccount = Account::query()->lockForUpdate()->findOrFail($account->id);

            $newStatus = ! $lockedAccount->is_active;
            $userId = auth()->id() > 0 ? auth()->id() : null;

            $lockedAccount->update([
                'is_active' => $newStatus,
                'updated_by' => $userId,
            ]);

            $this->auditLogger->record($newStatus ? 'admin.account.activated' : 'admin.account.deactivated', $lockedAccount, metadata: [
                'code' => $lockedAccount->code,
                'is_active' => $newStatus,
            ]);

            return $lockedAccount;
        });
    }
}
