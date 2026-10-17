<?php

namespace App\Actions;

use App\Enums\PhoneVerificationMethod;
use App\Models\Customer;
use App\Models\User;

final class VerifyCustomerPhoneByAdminAction
{
    public function __construct(
        private readonly VerifyCustomerPhoneAction $verifyCustomerPhoneAction,
    ) {}

    public function execute(Customer $customer, User $adminUser): Customer
    {
        return $this->verifyCustomerPhoneAction->execute(
            customer: $customer,
            method: PhoneVerificationMethod::Admin,
            verifiedBy: $adminUser
        );
    }
}
