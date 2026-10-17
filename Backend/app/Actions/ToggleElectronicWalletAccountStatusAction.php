<?php

namespace App\Actions;

use App\Models\ElectronicWalletAccount;
use App\Services\AuditLogger;

final class ToggleElectronicWalletAccountStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ElectronicWalletAccount $wallet): ElectronicWalletAccount
    {
        $wallet->update([
            'is_active' => ! $wallet->is_active,
            'updated_by' => auth()->id() > 0 ? auth()->id() : null,
        ]);

        $statusLabel = $wallet->is_active ? 'تفعيل' : 'تعطيل';

        $this->auditLogger->record('admin.electronic_wallet.status_toggled', $wallet, metadata: [
            'code' => $wallet->code,
            'is_active' => $wallet->is_active,
        ]);

        return $wallet;
    }
}
