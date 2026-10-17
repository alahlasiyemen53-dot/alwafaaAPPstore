<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DeliveryMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'price',
        'estimated_delivery_ar',
        'estimated_delivery_en',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getDisplayNameAttribute(): string
    {
        return $this->name_ar !== '' ? (string) $this->name_ar : (string) ($this->name_en ?? $this->code);
    }

    /** @return HasMany<GovernorateDeliveryMethod, $this> */
    public function governorateAssignments(): HasMany
    {
        return $this->hasMany(GovernorateDeliveryMethod::class);
    }
}
