<?php

namespace Tests\Unit\Notifications;

use App\Data\Notifications\PushDeviceRegistrationData;
use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use App\Models\Customer;
use App\Models\PushDevice;
use App\Services\Notifications\PushDeviceEligibilityService;
use App\Services\Notifications\PushDeviceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PushDeviceLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_a_token_hashes_it_and_reactivates_the_existing_registration(): void
    {
        $customer = $this->customer(true);
        $service = app(PushDeviceLifecycleService::class);
        $registration = new PushDeviceRegistrationData('fcm-token-one', PushDevicePlatform::Android, 'android-1');
        $device = $service->register($customer, $registration);
        $service->invalidate($device, 'provider_unregistered');
        $reactivated = $service->register($customer, $registration);

        self::assertSame($device->id, $reactivated->id);
        self::assertSame(PushDeviceStatus::Active, $reactivated->status);
        self::assertSame(hash('sha256', 'fcm-token-one'), $reactivated->token_hash);
        self::assertNull($reactivated->invalidated_at);
        self::assertNull($reactivated->invalidation_reason);
        self::assertSame('fcm-token-one', $reactivated->fresh()->token);
    }

    public function test_registering_a_token_for_another_customer_moves_the_single_registration(): void
    {
        $firstCustomer = $this->customer(true);
        $secondCustomer = $this->customer(true);
        $service = app(PushDeviceLifecycleService::class);
        $firstDevice = $service->register($firstCustomer, new PushDeviceRegistrationData('shared-token', PushDevicePlatform::Android));
        $movedDevice = $service->register($secondCustomer, new PushDeviceRegistrationData('shared-token', PushDevicePlatform::Ios));

        self::assertSame($firstDevice->id, $movedDevice->id);
        self::assertSame($secondCustomer->id, $movedDevice->owner_id);
        self::assertSame(Customer::class, $movedDevice->owner_type);
        self::assertSame(PushDevicePlatform::Ios, $movedDevice->platform);
        self::assertSame(0, $firstCustomer->pushDevices()->count());
    }

    public function test_inactive_devices_and_inactive_customers_are_not_eligible(): void
    {
        $activeCustomer = $this->customer(true);
        $inactiveCustomer = $this->customer(false);
        $lifecycle = app(PushDeviceLifecycleService::class);
        $activeDevice = $lifecycle->register($activeCustomer, new PushDeviceRegistrationData('active-token', PushDevicePlatform::Android));
        $inactiveDevice = $lifecycle->register($inactiveCustomer, new PushDeviceRegistrationData('inactive-token', PushDevicePlatform::Ios));
        $lifecycle->detach($activeDevice);
        $eligibility = app(PushDeviceEligibilityService::class);

        self::assertFalse($eligibility->isEligible($activeDevice->fresh()));
        self::assertFalse($eligibility->isEligible($inactiveDevice->fresh()));
        self::assertCount(0, $eligibility->forCustomer($activeCustomer));
        self::assertCount(0, $eligibility->forCustomer($inactiveCustomer));
    }

    public function test_hash_only_devices_are_not_eligible(): void
    {
        $customer = $this->customer(true);
        $device = new PushDevice([
            'platform' => PushDevicePlatform::Android,
        ]);
        $device->owner()->associate($customer);
        $device->provider = 'fcm';
        $device->status = PushDeviceStatus::Active;
        $device->token_hash = hash('sha256', 'missing-encrypted-token');
        $device->save();

        self::assertFalse(app(PushDeviceEligibilityService::class)->isEligible($device->fresh()));
        self::assertCount(0, app(PushDeviceEligibilityService::class)->forCustomer($customer));
    }

    private function customer(bool $isActive): Customer
    {
        return Customer::query()->create([
            'name' => 'عميل إشعارات',
            'phone' => fake()->unique()->numerify('77#######'),
            'normalized_phone' => fake()->unique()->numerify('77#######'),
            'is_active' => $isActive,
        ]);
    }
}
