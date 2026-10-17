<?php

namespace App\Actions;

use App\Models\Cashbox;
use App\Services\AuditLogger;

final class ToggleCashboxStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Cashbox $cashbox): Cashbox
    {
        $cashbox->update([
            'is_active' => ! $cashbox->is_active,
            'updated_by' => auth()->id() > 0 ? auth()->id() : null,
        ]);

        $statusLabel = $cashbox->is_active ? 'تفعيل' : 'تعطيل';

        $this->auditLogger->record('admin.cashbox.status_toggled', $cashbox, metadata: [
            'code' => $cashbox->code,
            'is_active' => $cashbox->is_active,
        ]);

        return $cashbox;
    }
}
