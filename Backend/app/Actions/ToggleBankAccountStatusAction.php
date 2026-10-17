<?php

namespace App\Actions;

use App\Models\BankAccount;
use App\Services\AuditLogger;

final class ToggleBankAccountStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(BankAccount $bankAccount): BankAccount
    {
        $bankAccount->update([
            'is_active' => ! $bankAccount->is_active,
            'updated_by' => auth()->id() > 0 ? auth()->id() : null,
        ]);

        $statusLabel = $bankAccount->is_active ? 'تفعيل' : 'تعطيل';

        $this->auditLogger->record('admin.bank_account.status_toggled', $bankAccount, metadata: [
            'code' => $bankAccount->code,
            'is_active' => $bankAccount->is_active,
        ]);

        return $bankAccount;
    }
}
