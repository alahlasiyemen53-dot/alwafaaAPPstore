<?php

namespace App\Queries\Notifications;

use App\Models\BusinessNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class NotificationLogQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, BusinessNotification>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = BusinessNotification::query()
            ->with(['recipient', 'deliveries.pushDevice', 'deliveries.attempts'])
            ->latest('id');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('business_reference', 'like', "%{$search}%")
                    ->orWhere('idempotency_key', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
