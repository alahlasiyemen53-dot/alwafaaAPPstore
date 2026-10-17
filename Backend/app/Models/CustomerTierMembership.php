<?php

namespace App\Models;

use App\Enums\CustomerTierAssignmentSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerTierMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'customer_tier_id',
        'starts_at',
        'ends_at',
        'assignment_source',
        'assigned_by',
        'change_reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'assignment_source' => CustomerTierAssignmentSource::class,
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CustomerTier, $this> */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(CustomerTier::class, 'customer_tier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @param Builder<self> $query */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ends_at');
    }

    public function isCurrent(): bool
    {
        return $this->ends_at === null;
    }
}
