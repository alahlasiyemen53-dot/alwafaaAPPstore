<?php

namespace App\Actions;

use App\Models\Customer;
use App\Services\AuditLogger;

final class DeactivateCustomerAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Customer $customer): Customer
    {
        $customer->update(['is_active' => false]);

        $this->auditLogger->record('admin.customer.deactivated', $customer);

        return $customer;
    }
}
