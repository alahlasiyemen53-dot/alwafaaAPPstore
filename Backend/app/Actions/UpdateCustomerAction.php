<?php

namespace App\Actions;

use App\Data\CustomerData;
use App\Models\Customer;
use App\Services\AuditLogger;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateCustomerAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Customer $customer, CustomerData $data): Customer
    {
        $newNormalizedPhone = PhoneNormalizer::normalize($data->phone);
        if ($newNormalizedPhone === null || ! PhoneNormalizer::isValid($data->phone)) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف غير صالح.',
            ]);
        }

        if (Customer::query()
            ->where('normalized_phone', $newNormalizedPhone)
            ->where('id', '!=', $customer->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف مستخدم بالفعل لعميل آخر.',
            ]);
        }

        return DB::transaction(function () use ($customer, $data, $newNormalizedPhone): Customer {
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            $oldNormalized = $lockedCustomer->normalized_phone;
            $wasVerified = $lockedCustomer->isPhoneVerified();
            $canonicalPhoneChanged = ($oldNormalized !== $newNormalizedPhone);

            $updateData = [
                'name' => $data->name,
                'phone' => $data->phone,
                'normalized_phone' => $newNormalizedPhone,
                'email' => $data->email,
                'notes' => $data->notes,
                'is_active' => $data->isActive,
            ];

            if ($canonicalPhoneChanged) {
                $updateData['phone_verified_at'] = null;
                $updateData['phone_verification_method'] = null;
                $updateData['phone_verified_by'] = null;

                if ($wasVerified) {
                    $this->auditLogger->record('admin.customer.phone_verification_reset', $lockedCustomer, metadata: [
                        'old_phone' => $lockedCustomer->phone,
                        'old_normalized_phone' => $oldNormalized,
                        'new_phone' => $data->phone,
                        'new_normalized_phone' => $newNormalizedPhone,
                        'reason' => 'canonical_phone_changed',
                    ]);
                }
            }

            $lockedCustomer->update($updateData);

            $this->auditLogger->record('admin.customer.updated', $lockedCustomer, metadata: [
                'canonical_phone_changed' => $canonicalPhoneChanged,
            ]);

            return $lockedCustomer;
        });
    }
}
