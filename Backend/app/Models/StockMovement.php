<?php

namespace App\Models;

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class StockMovement extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'movement_type',
        'direction',
        'quantity',
        'reference_type',
        'reference_id',
        'reason_code',
        'notes',
        'occurred_at',
        'created_by',
    ];

    protected $casts = [
        'movement_type' => StockMovementType::class,
        'direction' => StockMovementDirection::class,
        'quantity' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
