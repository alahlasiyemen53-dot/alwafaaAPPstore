<?php

namespace App\Actions;

use App\Models\Customer;
use App\Services\AuditLogger;

final class ActivateCustomerAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Customer $customer): Customer
    {
        $customer->update(['is_active' => true]);

        $this->auditLogger->record('admin.customer.activated', $customer);

        return $customer;
    }
}
