<?php

namespace App\Actions;

use App\Models\Supplier;
use App\Services\AuditLogger;

final class ToggleSupplierStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Supplier $supplier): Supplier
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        $action = $supplier->is_active ? 'admin.supplier.activated' : 'admin.supplier.deactivated';
        $this->auditLogger->record($action, $supplier);

        return $supplier;
    }
}
