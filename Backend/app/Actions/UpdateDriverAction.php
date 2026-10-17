<?php

namespace App\Actions;

use App\DTOs\UpdateDriverData;
use App\Models\Driver;
use App\Services\AuditLogger;

final class UpdateDriverAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Driver $driver, UpdateDriverData $data): Driver
    {
        $driver->update([
            'name' => $data->name,
            'phone' => $data->phone,
            'normalized_phone' => $data->phone,
            'secondary_phone' => $data->secondaryPhone,
            'vehicle_type' => $data->vehicleType,
            'vehicle_plate_number' => $data->vehiclePlateNumber,
            'is_active' => $data->isActive,
            'notes' => $data->notes,
        ]);

        $this->auditLogger->record('admin.driver.updated', $driver, metadata: [
            'code' => $driver->code,
            'name' => $driver->name,
        ]);

        return $driver;
    }
}
