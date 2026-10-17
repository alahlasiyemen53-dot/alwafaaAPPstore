<?php

namespace App\Console\Commands;

use App\Enums\OtpChallengeStatus;
use App\Models\CustomerOtpChallenge;
use Illuminate\Console\Command;

final class PruneExpiredOtpChallengesCommand extends Command
{
    protected $signature = 'otp:prune {--days= : Override default retention days}';

    protected $description = 'Prune old expired, consumed, or superseded customer OTP challenges';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('otp.retention_days', 7));
        $cutoff = now()->subDays($days);

        $this->info("Pruning customer OTP challenges older than {$cutoff->toDateTimeString()} ({$days} days)...");

        $deleted = CustomerOtpChallenge::query()
            ->where('created_at', '<', $cutoff)
            ->where(function ($query) {
                $query->whereIn('status', [
                    OtpChallengeStatus::Verified->value,
                    OtpChallengeStatus::Expired->value,
                    OtpChallengeStatus::Exhausted->value,
                    OtpChallengeStatus::Superseded->value,
                ])
                ->orWhere('expires_at', '<', now());
            })
            ->delete();

        $this->info("Successfully pruned {$deleted} OTP challenge records.");

        return self::SUCCESS;
    }
}
