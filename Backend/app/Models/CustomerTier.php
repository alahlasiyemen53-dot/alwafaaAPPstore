<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'description',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<CustomerTierMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(CustomerTierMembership::class, 'customer_tier_id');
    }

    /** @return HasMany<CustomerTierMembership, $this> */
    public function activeMemberships(): HasMany
    {
        return $this->hasMany(CustomerTierMembership::class, 'customer_tier_id')->whereNull('ends_at');
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'current_tier_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function hasMembershipHistory(): bool
    {
        return $this->memberships()->exists();
    }

    public function canBeDeleted(): bool
    {
        return ! $this->is_default && ! $this->hasMembershipHistory();
    }
}
