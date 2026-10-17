<?php

namespace App\Actions;

use App\Data\CustomerData;
use App\Enums\CustomerTierAssignmentSource;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\CustomerTierMembership;
use App\Services\AuditLogger;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateCustomerAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CustomerData $data): Customer
    {
        $normalizedPhone = PhoneNormalizer::normalize($data->phone);
        if ($normalizedPhone === null || ! PhoneNormalizer::isValid($data->phone)) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف غير صالح.',
            ]);
        }

        if (Customer::query()->where('normalized_phone', $normalizedPhone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف مستخدم بالفعل لعميل آخر.',
            ]);
        }

        return DB::transaction(function () use ($data, $normalizedPhone): Customer {
            $customer = Customer::query()->create([
                'name' => $data->name,
                'phone' => $data->phone,
                'normalized_phone' => $normalizedPhone,
                'email' => $data->email,
                'notes' => $data->notes,
                'is_active' => $data->isActive,
                'phone_verified_at' => null,
                'phone_verification_method' => null,
                'phone_verified_by' => null,
            ]);

            $defaultTier = CustomerTier::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($defaultTier !== null) {
                $customer->update(['current_tier_id' => $defaultTier->id]);

                CustomerTierMembership::query()->create([
                    'customer_id' => $customer->id,
                    'customer_tier_id' => $defaultTier->id,
                    'starts_at' => now(),
                    'ends_at' => null,
                    'assignment_source' => CustomerTierAssignmentSource::SYSTEM_DEFAULT,
                    'assigned_by' => null,
                    'change_reason' => 'عضوية افتراضية أولية عند تسجيل العميل',
                ]);
            }

            $this->auditLogger->record('admin.customer.created', $customer, metadata: [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'normalized_phone' => $customer->normalized_phone,
                'tier_id' => $defaultTier?->id,
            ]);

            return $customer;
        });
    }
}
