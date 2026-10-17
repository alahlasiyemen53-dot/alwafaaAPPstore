<?php

namespace App\Models;

use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class CustomerOtpChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'phone',
        'normalized_phone',
        'purpose',
        'code_hash',
        'status',
        'attempts',
        'max_attempts',
        'expires_at',
        'consumed_at',
        'last_sent_at',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected $casts = [
        'purpose' => OtpPurpose::class,
        'status' => OtpChallengeStatus::class,
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->isAfter($this->expires_at);
    }

    public function isExhausted(): bool
    {
        return $this->attempts >= $this->max_attempts || $this->status === OtpChallengeStatus::Exhausted;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null || $this->status === OtpChallengeStatus::Verified;
    }

    public function canAttempt(): bool
    {
        return $this->status === OtpChallengeStatus::Pending
            && ! $this->isExpired()
            && ! $this->isExhausted()
            && ! $this->isConsumed();
    }

    public function getMaskedPhoneAttribute(): string
    {
        $phone = $this->normalized_phone ?? $this->phone ?? '';
        if (strlen($phone) < 7) {
            return $phone;
        }

        $prefix = substr($phone, 0, 6);
        $suffix = substr($phone, -3);

        return $prefix.'****'.$suffix;
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OtpChallengeStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->whereNull('consumed_at');
    }
}
