<?php

namespace App\Actions;

use App\Data\SupplierData;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class UpdateSupplierAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Supplier $supplier, SupplierData $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data): Supplier {
            $supplier->update([
                'code' => $data->code ?? $supplier->code,
                'name' => $data->name,
                'contact_person' => $data->contactPerson,
                'phone' => $data->phone,
                'secondary_phone' => $data->secondaryPhone,
                'email' => $data->email,
                'address' => $data->address,
                'tax_number' => $data->taxNumber,
                'notes' => $data->notes,
                'is_active' => $data->isActive,
            ]);

            $this->auditLogger->record('admin.supplier.updated', $supplier);

            return $supplier;
        });
    }
}
