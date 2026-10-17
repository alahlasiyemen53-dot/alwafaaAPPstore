<?php

namespace App\Actions;

use App\Models\Driver;
use App\Services\AuditLogger;

final class ToggleDriverStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Driver $driver): Driver
    {
        $driver->update([
            'is_active' => ! $driver->is_active,
        ]);

        $this->auditLogger->record('admin.driver.status_toggled', $driver, metadata: [
            'code' => $driver->code,
            'is_active' => $driver->is_active,
        ]);

        return $driver;
    }
}
