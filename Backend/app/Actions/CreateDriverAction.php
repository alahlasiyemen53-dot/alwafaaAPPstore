<?php

namespace App\Actions;

use App\DTOs\CreateDriverData;
use App\Models\Driver;
use App\Services\AuditLogger;

final class CreateDriverAction
{
    public function __construct(
        private readonly GenerateDriverCodeAction $generateCode,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreateDriverData $data): Driver
    {
        $code = $this->generateCode->execute();

        $driver = Driver::create([
            'code' => $code,
            'name' => $data->name,
            'phone' => $data->phone,
            'normalized_phone' => $data->phone,
            'secondary_phone' => $data->secondaryPhone,
            'vehicle_type' => $data->vehicleType,
            'vehicle_plate_number' => $data->vehiclePlateNumber,
            'is_active' => $data->isActive,
            'notes' => $data->notes,
        ]);

        $this->auditLogger->record('admin.driver.created', $driver, metadata: [
            'code' => $driver->code,
            'name' => $driver->name,
            'phone' => $driver->phone,
        ]);

        return $driver;
    }
}
