<?php

namespace App\Services\Notifications;

use App\Enums\PushDeviceStatus;
use App\Models\Customer;
use App\Models\PushDevice;
use Illuminate\Database\Eloquent\Collection;

final class PushDeviceEligibilityService
{
    /** @return Collection<int, PushDevice> */
    public function forCustomer(Customer $customer): Collection
    {
        if (! $customer->is_active) {
            return new Collection;
        }

        return $customer->pushDevices()
            ->where('provider', 'fcm')
            ->where('status', PushDeviceStatus::Active)
            ->whereNotNull('token')
            ->whereNotNull('token_hash')
            ->get();
    }

    public function isEligible(PushDevice $device): bool
    {
        $owner = $device->owner;

        return $device->provider === 'fcm'
            && $device->status === PushDeviceStatus::Active
            && $device->token !== null
            && $device->token_hash !== null
            && $owner instanceof Customer
            && $owner->is_active;
    }
}
