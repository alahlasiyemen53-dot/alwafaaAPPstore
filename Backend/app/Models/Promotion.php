<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property PromotionType $type
 * @property float $value
 * @property PromotionScope $scope
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property int $priority
 * @property string $stacking_policy
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'scope',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
        'stacking_policy',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => PromotionType::class,
        'scope' => PromotionScope::class,
        'value' => 'decimal:4',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /** @return HasMany<PromotionTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isCurrentlyValid(?Carbon $time = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $checkTime = $time ?? now();

        if ($this->starts_at && $checkTime->isBefore($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $checkTime->isAfter($this->ends_at)) {
            return false;
        }

        return true;
    }
}
