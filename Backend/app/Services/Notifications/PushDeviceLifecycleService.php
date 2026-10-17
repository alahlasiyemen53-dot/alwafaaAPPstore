<?php

namespace App\Services\Notifications;

use App\Data\Notifications\PushDeviceRegistrationData;
use App\Enums\PushDeviceStatus;
use App\Models\Customer;
use App\Models\PushDevice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PushDeviceLifecycleService
{
    public function __construct(private readonly TokenMasker $tokenMasker) {}

    public function register(Customer $customer, PushDeviceRegistrationData $registration): PushDevice
    {
        $token = trim($registration->token);

        if ($token === '') {
            throw new InvalidArgumentException('Device token is required.');
        }

        return DB::transaction(function () use ($customer, $registration, $token): PushDevice {
            $tokenHash = $this->tokenMasker->hash($token);
            $device = $this->lockedDeviceForToken($tokenHash) ?? $this->lockedDeviceForIdentifier($customer, $registration);
            $device ??= new PushDevice(['provider' => 'fcm']);
            $device->owner()->associate($customer);
            $this->applyRegistration($device, $registration, $token, $tokenHash);
            $device->save();

            return $device;
        });
    }

    public function invalidate(PushDevice $device, string $reason): void
    {
        DB::transaction(function () use ($device, $reason): void {
            $lockedDevice = PushDevice::query()->lockForUpdate()->findOrFail($device->id);
            $lockedDevice->status = PushDeviceStatus::Invalidated;
            $lockedDevice->invalidated_at = now();
            $lockedDevice->invalidation_reason = mb_substr(trim($reason), 0, 100);
            $lockedDevice->save();
        });
    }

    public function detach(PushDevice $device): void
    {
        DB::transaction(function () use ($device): void {
            $lockedDevice = PushDevice::query()->lockForUpdate()->findOrFail($device->id);
            $lockedDevice->status = PushDeviceStatus::Inactive;
            $lockedDevice->save();
        });
    }

    private function lockedDeviceForToken(string $tokenHash): ?PushDevice
    {
        return PushDevice::query()->where('provider', 'fcm')->where('token_hash', $tokenHash)->lockForUpdate()->first();
    }

    private function lockedDeviceForIdentifier(Customer $customer, PushDeviceRegistrationData $registration): ?PushDevice
    {
        if ($registration->deviceIdentifier === null) {
            return null;
        }

        return $customer->pushDevices()->where('provider', 'fcm')->where('device_identifier', $registration->deviceIdentifier)->lockForUpdate()->first();
    }

    private function applyRegistration(PushDevice $device, PushDeviceRegistrationData $registration, string $token, string $tokenHash): void
    {
        $device->provider = 'fcm';
        $device->token = $token;
        $device->token_hash = $tokenHash;
        $device->platform = $registration->platform;
        $device->device_identifier = $registration->deviceIdentifier;
        $device->locale = $registration->locale;
        $device->app_version = $registration->appVersion;
        $device->status = PushDeviceStatus::Active;
        $device->last_registered_at = now();
        $device->invalidated_at = null;
        $device->invalidation_reason = null;
    }
}
