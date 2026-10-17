<?php

namespace App\Support;

use App\Enums\CustomerTierAssignmentSource;
use App\Enums\PhoneVerificationMethod;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\CustomerTierMembership;

/**
 * Fixed demo customer credentials for QA / local testing.
 * Phone and OTP are configurable; set OTP_DEMO_ENABLED=false to disable.
 */
final class DemoCustomerOtp
{
    public static function enabled(): bool
    {
        // Missing/stale config (e.g. old config:cache) must not silently disable QA login.
        $raw = config('otp.demo.enabled');
        if ($raw === null) {
            return true;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    public static function phone(): string
    {
        return (string) config('otp.demo.phone', '771771771');
    }

    public static function code(): string
    {
        return (string) config('otp.demo.code', '123456');
    }

    public static function name(): string
    {
        return (string) config('otp.demo.name', 'مستخدم تجريبي');
    }

    public static function normalizedPhone(): ?string
    {
        return PhoneNormalizer::normalize(self::phone());
    }

    public static function matchesNormalizedPhone(?string $normalizedPhone): bool
    {
        if (! self::enabled() || $normalizedPhone === null || $normalizedPhone === '') {
            return false;
        }

        $demo = self::normalizedPhone();

        return $demo !== null && $demo === $normalizedPhone;
    }

    public static function matchesCode(string $code): bool
    {
        return self::enabled() && hash_equals(self::code(), $code);
    }

    /**
     * Ensure the demo customer exists as an active, verified account.
     */
    public static function ensureCustomer(): ?Customer
    {
        $phone = self::phone();
        $normalizedPhone = self::normalizedPhone();
        if ($normalizedPhone === null) {
            return null;
        }

        $customer = Customer::query()->updateOrCreate(
            ['normalized_phone' => $normalizedPhone],
            [
                'name' => self::name(),
                'phone' => $phone,
                'is_active' => true,
                'phone_verified_at' => now(),
                'phone_verification_method' => PhoneVerificationMethod::Admin,
                'notes' => 'حساب تجريبي للاختبار — OTP ثابت '.self::code(),
            ]
        );

        $defaultTier = CustomerTier::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($defaultTier !== null && $customer->current_tier_id === null) {
            $customer->update(['current_tier_id' => $defaultTier->id]);

            $hasMembership = CustomerTierMembership::query()
                ->where('customer_id', $customer->id)
                ->where('customer_tier_id', $defaultTier->id)
                ->whereNull('ends_at')
                ->exists();

            if (! $hasMembership) {
                CustomerTierMembership::query()->create([
                    'customer_id' => $customer->id,
                    'customer_tier_id' => $defaultTier->id,
                    'starts_at' => now(),
                    'ends_at' => null,
                    'assignment_source' => CustomerTierAssignmentSource::SYSTEM_DEFAULT,
                    'assigned_by' => null,
                    'change_reason' => 'عضوية افتراضية لحساب الاختبار التجريبي',
                ]);
            }
        }

        return $customer->fresh();
    }
}
