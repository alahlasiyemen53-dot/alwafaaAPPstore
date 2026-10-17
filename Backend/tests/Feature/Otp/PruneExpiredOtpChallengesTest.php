<?php

namespace Tests\Feature\Otp;

use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Models\CustomerOtpChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PruneExpiredOtpChallengesTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_challenges_older_than_retention_days(): void
    {
        // Travel back 10 days
        $this->travel(-10)->days();

        // 1. Old verified challenge (10 days old) -> should be pruned
        CustomerOtpChallenge::query()->create([
            'challenge_id' => (string) Str::uuid(),
            'phone' => '770000001',
            'normalized_phone' => '+967770000001',
            'purpose' => OtpPurpose::PhoneVerification,
            'code_hash' => 'dummy-hash',
            'status' => OtpChallengeStatus::Verified,
            'attempts' => 1,
            'max_attempts' => 5,
            'expires_at' => now()->subMinutes(10),
            'consumed_at' => now(),
        ]);

        // Travel to 8 days ago
        $this->travelBack();
        $this->travel(-8)->days();

        // 2. Old expired challenge (8 days old) -> should be pruned
        CustomerOtpChallenge::query()->create([
            'challenge_id' => (string) Str::uuid(),
            'phone' => '770000002',
            'normalized_phone' => '+967770000002',
            'purpose' => OtpPurpose::PhoneVerification,
            'code_hash' => 'dummy-hash',
            'status' => OtpChallengeStatus::Expired,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->subMinutes(10),
        ]);

        // Travel to 2 days ago
        $this->travelBack();
        $this->travel(-2)->days();

        // 3. Recent verified challenge (2 days old) -> should be KEPT
        $recentVerified = CustomerOtpChallenge::query()->create([
            'challenge_id' => (string) Str::uuid(),
            'phone' => '770000003',
            'normalized_phone' => '+967770000003',
            'purpose' => OtpPurpose::PhoneVerification,
            'code_hash' => 'dummy-hash',
            'status' => OtpChallengeStatus::Verified,
            'attempts' => 1,
            'max_attempts' => 5,
            'expires_at' => now()->subMinutes(10),
            'consumed_at' => now(),
        ]);

        // Return to present
        $this->travelBack();

        // 4. Active pending challenge -> should be KEPT
        $activePending = CustomerOtpChallenge::query()->create([
            'challenge_id' => (string) Str::uuid(),
            'phone' => '770000004',
            'normalized_phone' => '+967770000004',
            'purpose' => OtpPurpose::PhoneVerification,
            'code_hash' => 'dummy-hash',
            'status' => OtpChallengeStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertSame(4, CustomerOtpChallenge::query()->count());

        // Run the prune command with 7 days retention
        $this->artisan('otp:prune', ['--days' => 7])
            ->expectsOutputToContain('Successfully pruned 2 OTP challenge records.')
            ->assertSuccessful();

        $this->assertSame(2, CustomerOtpChallenge::query()->count());
        $this->assertTrue(CustomerOtpChallenge::query()->where('id', $recentVerified->id)->exists());
        $this->assertTrue(CustomerOtpChallenge::query()->where('id', $activePending->id)->exists());
    }
}
