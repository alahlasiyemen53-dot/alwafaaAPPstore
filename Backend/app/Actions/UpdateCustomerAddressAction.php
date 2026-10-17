<?php

namespace App\Actions;

use App\Data\CustomerAddressData;
use App\Models\CustomerAddress;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerAddressAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CustomerAddress $address, CustomerAddressData $data): CustomerAddress
    {
        return DB::transaction(function () use ($address, $data): CustomerAddress {
            $customerId = $address->customer_id;
            $isDefault = $data->isDefault;

            // Enforce default address invariant
            if ($isDefault) {
                // If setting as default, unset others
                CustomerAddress::query()
                    ->where('customer_id', $customerId)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            } else {
                // If unsetting default when it was default:
                if ($address->is_default) {
                    $otherAddress = CustomerAddress::query()
                        ->where('customer_id', $customerId)
                        ->where('id', '!=', $address->id)
                        ->orderBy('id', 'asc')
                        ->first();

                    if ($otherAddress) {
                        $otherAddress->update(['is_default' => true]);
                    } else {
                        // It is the only address, it must remain default
                        $isDefault = true;
                    }
                }
            }

            $address->update([
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

            $this->auditLogger->record('admin.customer_address.updated', $address, metadata: [
                'customer_id' => $customerId,
                'is_default' => $isDefault,
            ]);

            return $address;
        });
    }
}
