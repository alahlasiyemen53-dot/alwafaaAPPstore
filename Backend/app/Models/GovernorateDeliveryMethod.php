<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GovernorateDeliveryMethod extends Model
{
    protected $fillable = [
        'governorate_id',
        'delivery_method_id',
        'delivery_fee',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:4',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Governorate, $this> */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /** @return BelongsTo<DeliveryMethod, $this> */
    public function deliveryMethod(): BelongsTo
    {
        return $this->belongsTo(DeliveryMethod::class);
    }
}
