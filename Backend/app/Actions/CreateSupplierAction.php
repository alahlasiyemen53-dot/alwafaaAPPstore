<?php

namespace App\Actions;

use App\Data\SupplierData;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CreateSupplierAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(SupplierData $data): Supplier
    {
        return DB::transaction(function () use ($data): Supplier {
            $code = $data->code;
            if ($code === null || trim($code) === '') {
                $lastId = Supplier::query()->max('id') ?? 0;
                $code = 'SUP-'.str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT);
            }

            $supplier = Supplier::query()->create([
                'code' => $code,
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

            $this->auditLogger->record('admin.supplier.created', $supplier);

            return $supplier;
        });
    }
}
