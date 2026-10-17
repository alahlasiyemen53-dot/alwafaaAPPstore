<?php

namespace App\Actions;

use App\Enums\PhoneVerificationMethod;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class VerifyCustomerPhoneAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Customer $customer, PhoneVerificationMethod $method, ?User $verifiedBy = null): Customer
    {
        if ($method === PhoneVerificationMethod::Admin && $verifiedBy === null) {
            throw new InvalidArgumentException('Admin verification requires an admin user.');
        }

        if ($method === PhoneVerificationMethod::Otp && $verifiedBy !== null) {
            throw new InvalidArgumentException('OTP verification must not have a verifying admin user.');
        }

        return DB::transaction(function () use ($customer, $method, $verifiedBy): Customer {
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            // Double-click / Idempotency protection: If already verified with current phone, return safely
            if ($lockedCustomer->isPhoneVerified()) {
                return $lockedCustomer;
            }

            $lockedCustomer->update([
                'phone_verified_at' => now(),
                'phone_verification_method' => $method,
                'phone_verified_by' => $verifiedBy?->id,
            ]);

            $this->auditLogger->record(
                action: 'admin.customer.phone_verified',
                subject: $lockedCustomer,
                metadata: [
                    'method' => $method->value,
                    'phone' => $lockedCustomer->phone,
                    'normalized_phone' => $lockedCustomer->normalized_phone,
                    'verified_by' => $verifiedBy?->id,
                ],
                actor: $verifiedBy
            );

            return $lockedCustomer;
        });
    }
}
