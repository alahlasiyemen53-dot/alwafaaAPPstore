<?php

namespace App\Actions;

use App\Models\CustomerAddress;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class SetDefaultCustomerAddressAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CustomerAddress $address): CustomerAddress
    {
        return DB::transaction(function () use ($address): CustomerAddress {
            $customerId = $address->customer_id;

            CustomerAddress::query()
                ->where('customer_id', $customerId)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);

            $this->auditLogger->record('admin.customer_address.set_default', $address, metadata: [
                'customer_id' => $customerId,
            ]);

            return $address;
        });
    }
}
