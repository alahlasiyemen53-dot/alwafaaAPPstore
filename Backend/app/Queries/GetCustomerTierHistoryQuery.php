<?php

namespace App\Queries;

use App\Models\Customer;
use App\Models\CustomerTierMembership;
use Illuminate\Database\Eloquent\Collection;

final class GetCustomerTierHistoryQuery
{
    /** @return Collection<int, CustomerTierMembership> */
    public function getBounded(Customer $customer, int $limit = 20): Collection
    {
        return CustomerTierMembership::query()
            ->where('customer_id', $customer->id)
            ->with([
                'tier:id,code,name_ar,name_en',
                'assignedBy:id,name,email',
            ])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function count(Customer $customer): int
    {
        return CustomerTierMembership::query()
            ->where('customer_id', $customer->id)
            ->count();
    }
}
