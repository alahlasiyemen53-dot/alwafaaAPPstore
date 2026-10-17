<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $promotion_id
 * @property string $target_type
 * @property int $target_id
 */
final class PromotionTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_id',
        'target_type',
        'target_id',
    ];

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
