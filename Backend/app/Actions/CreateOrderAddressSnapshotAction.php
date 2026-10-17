<?php

namespace App\Actions;

use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderAddress;
use DomainException;

final class CreateOrderAddressSnapshotAction
{
    public function execute(Order $order, int $customerAddressId): OrderAddress
    {
        $address = CustomerAddress::query()
            ->with(['country', 'governorate', 'city', 'district'])
            ->findOrFail($customerAddressId);

        if ($address->customer_id !== $order->customer_id) {
            throw new DomainException('عنوان التوصيل المحدد لا ينتمي للعميل صاحب الطلب.');
        }

        // Delete any previous draft delivery address for this order if updating
        OrderAddress::query()->where('order_id', $order->id)->delete();

        return OrderAddress::query()->create([
            'order_id' => $order->id,
            'address_type' => 'delivery',
            'contact_name' => $address->contact_name,
            'contact_phone' => $address->contact_phone,
            'country_id' => $address->country_id,
            'governorate_id' => $address->governorate_id,
            'city_id' => null,
            'district_id' => null,
            'governorate_name_snapshot' => $address->governorate?->name_ar,
            'city_name_snapshot' => null,
            'district_name_snapshot' => null,
            'country_name_snapshot' => $address->country?->name_ar,
            'country_code_snapshot' => $address->country?->code,
            'neighborhood' => $address->neighborhood,
            'street' => $address->street,
            'building' => $address->building,
            'floor' => $address->floor,
            'apartment' => $address->apartment,
            'landmark' => $address->landmark,
            'notes' => $address->notes,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'formatted_address' => $address->formatted_address,
        ]);
    }
}
