<?php

namespace App\Actions\Auth;

use App\Actions\VerifyCustomerPhoneAction;
use App\Enums\CustomerTierAssignmentSource;
use App\Enums\PhoneVerificationMethod;
use App\Exceptions\Auth\CustomerAccessRestrictedException;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\CustomerTierMembership;
use App\Services\AuditLogger;
use App\Support\CountryCallingCode;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ResolveOrCreateCustomerFromOtpAction
{
    public function __construct(
        private readonly VerifyCustomerPhoneAction $verifyCustomerPhoneAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Resolve existing customer or atomically create a new one.
     *
     * @throws CustomerAccessRestrictedException
     * @throws InvalidArgumentException
     */
    public function execute(
        string $phone,
        ?string $name = null,
        ?int $countryId = null,
        ?int $governorateId = null,
    ): Customer {
        $callingCode = CountryCallingCode::forCountryId($countryId);
        $normalizedPhone = PhoneNormalizer::normalize($phone, $callingCode);

        if ($normalizedPhone === null || ! PhoneNormalizer::isValid($phone, $countryId)) {
            throw new InvalidArgumentException('رقم الهاتف المدخل غير صالح.');
        }

        return DB::transaction(function () use ($phone, $normalizedPhone, $name, $countryId, $governorateId): Customer {
            /** @var Customer|null $existingCustomer */
            $existingCustomer = Customer::query()
                ->where('normalized_phone', $normalizedPhone)
                ->lockForUpdate()
                ->first();

            if ($existingCustomer !== null) {
                if (! $existingCustomer->is_active) {
                    throw new CustomerAccessRestrictedException;
                }

                if (! $existingCustomer->isPhoneVerified()) {
                    $existingCustomer = $this->verifyCustomerPhoneAction->execute(
                        customer: $existingCustomer,
                        method: PhoneVerificationMethod::Otp,
                        verifiedBy: null
                    );
                }

                if (! empty(trim((string) $name)) && ($existingCustomer->name === 'عميل جديد' || empty($existingCustomer->name))) {
                    $existingCustomer->name = trim((string) $name);
                    $existingCustomer->save();
                }

                return $existingCustomer;
            }

            // Create new customer
            $customerName = ! empty(trim((string) $name)) ? trim((string) $name) : 'عميل جديد';

            $customer = Customer::query()->create([
                'name' => $customerName,
                'phone' => $phone,
                'normalized_phone' => $normalizedPhone,
                'email' => null,
                'country_id' => $countryId,
                'governorate_id' => $governorateId,
                'notes' => 'تم التسجيل آلياً عبر رمز التحقق OTP',
                'is_active' => true,
                'phone_verified_at' => now(),
                'phone_verification_method' => PhoneVerificationMethod::Otp,
                'phone_verified_by' => null,
            ]);

            // Assign default tier if available
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
                    'change_reason' => 'عضوية افتراضية أولية عند تسجيل العميل عبر OTP',
                ]);
            }

            $this->auditLogger->record('customer.registered_via_otp', $customer, metadata: [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'normalized_phone' => $customer->normalized_phone,
                'tier_id' => $defaultTier?->id,
            ]);

            return $customer;
        });
    }
}
