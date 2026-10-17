<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Models\GovernorateDeliveryMethod;
use Illuminate\Database\Eloquent\Collection;

final class GovernorateDeliveryAvailability
{
    /**
     * @return Collection<int, GovernorateDeliveryMethod>
     */
    public function listAvailableForGovernorate(int $governorateId): Collection
    {
        return GovernorateDeliveryMethod::query()
            ->with('deliveryMethod')
            ->join('delivery_methods', 'delivery_methods.id', '=', 'governorate_delivery_methods.delivery_method_id')
            ->where('governorate_delivery_methods.governorate_id', $governorateId)
            ->where('governorate_delivery_methods.is_active', true)
            ->where('delivery_methods.is_active', true)
            ->orderBy('governorate_delivery_methods.sort_order')
            ->orderBy('delivery_methods.sort_order')
            ->orderBy('governorate_delivery_methods.id')
            ->select('governorate_delivery_methods.*')
            ->get();
    }

    public function resolve(int $governorateId, string $methodCode): ?GovernorateDeliveryMethod
    {
        $normalized = trim($methodCode);
        if ($normalized === '') {
            $normalized = 'standard';
        }

        return GovernorateDeliveryMethod::query()
            ->with('deliveryMethod')
            ->where('governorate_id', $governorateId)
            ->where('is_active', true)
            ->whereHas('deliveryMethod', fn ($query) => $query
                ->where('is_active', true)
                ->where('code', $normalized))
            ->first();
    }
}
