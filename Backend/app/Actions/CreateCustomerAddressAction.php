<?php

namespace App\Actions;

use App\Data\CustomerAddressData;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CreateCustomerAddressAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Customer $customer, CustomerAddressData $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data): CustomerAddress {
            $hasExistingAddresses = CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->exists();

            // First Address invariant: automatically default if first address
            $isDefault = (! $hasExistingAddresses) || $data->isDefault;

            if ($isDefault) {
                CustomerAddress::query()
                    ->where('customer_id', $customer->id)
                    ->update(['is_default' => false]);
            }

            $address = CustomerAddress::query()->create([
                'customer_id' => $customer->id,
                'label' => $data->label,
                'contact_name' => $data->contactName,
                'contact_phone' => $data->contactPhone,
                'country_id' => $data->countryId,
                'governorate_id' => $data->governorateId,
                'city_id' => $data->cityId,
                'district_id' => $data->districtId,
                'neighborhood' => $data->neighborhood,
                'street' => $data->street,
                'building' => $data->building,
                'floor' => $data->floor,
                'apartment' => $data->apartment,
                'landmark' => $data->landmark,
                'notes' => $data->notes,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'google_place_id' => $data->googlePlaceId,
                'formatted_address' => $data->formattedAddress,
                'is_default' => $isDefault,
            ]);

            $this->auditLogger->record('admin.customer_address.created', $address, metadata: [
                'customer_id' => $customer->id,
                'is_default' => $isDefault,
            ]);

            return $address;
        });
    }
}
