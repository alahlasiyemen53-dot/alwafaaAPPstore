<?php

namespace App\Actions;

use App\Models\CustomerAddress;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class DeleteCustomerAddressAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            $customerId = $address->customer_id;
            $wasDefault = $address->is_default;

            $address->delete();

            // Invariant: If default address is deleted and other addresses exist, deterministic replacement becomes default
            if ($wasDefault) {
                $replacement = CustomerAddress::query()
                    ->where('customer_id', $customerId)
                    ->orderBy('id', 'asc')
                    ->first();

                if ($replacement) {
                    $replacement->update(['is_default' => true]);

                    $this->auditLogger->record('admin.customer_address.default_reassigned', $replacement, metadata: [
                        'customer_id' => $customerId,
                        'reason' => 'previous_default_deleted',
                    ]);
                }
            }

            $this->auditLogger->record('admin.customer_address.deleted', null, metadata: [
                'customer_id' => $customerId,
                'address_id' => $address->id,
                'was_default' => $wasDefault,
            ]);
        });
    }
}
