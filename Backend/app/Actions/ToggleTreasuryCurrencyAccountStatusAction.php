<?php

namespace App\Actions;

use App\Models\TreasuryCurrencyAccount;
use App\Services\AuditLogger;

final class ToggleTreasuryCurrencyAccountStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(TreasuryCurrencyAccount $currencyAccount, int $userId = 0): TreasuryCurrencyAccount
    {
        $oldStatus = $currencyAccount->is_active;
        $newStatus = ! $oldStatus;

        $currencyAccount->is_active = $newStatus;
        $currencyAccount->updated_by = $userId > 0 ? $userId : null;
        $currencyAccount->save();

        $this->auditLogger->record(
            action: 'treasury_currency_account.status_toggled',
            subject: $currencyAccount,
            metadata: [
                'old_values' => ['is_active' => $oldStatus],
                'new_values' => ['is_active' => $newStatus],
            ],
            oldValues: ['is_active' => $oldStatus],
        );

        return $currencyAccount;
    }
}
