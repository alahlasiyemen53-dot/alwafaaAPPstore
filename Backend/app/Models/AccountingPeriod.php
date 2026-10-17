<?php

namespace App\Models;

use App\Enums\AccountingPeriodStatus;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AccountingPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'starts_at',
        'ends_at',
        'status',
        'closed_at',
        'closed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => AccountingPeriodStatus::class,
        'starts_at' => 'date',
        'ends_at' => 'date',
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<JournalEntry, $this> */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'accounting_period_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AccountingPeriodStatus::Open->value);
    }

    public function isOpen(): bool
    {
        return $this->status === AccountingPeriodStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === AccountingPeriodStatus::Closed;
    }

    public function containsDate(string|DateTimeInterface $date): bool
    {
        $target = $date instanceof DateTimeInterface ? Carbon::instance($date)->toDateString() : Carbon::parse($date)->toDateString();
        $start = $this->starts_at->toDateString();
        $end = $this->ends_at->toDateString();

        return $target >= $start && $target <= $end;
    }
}
