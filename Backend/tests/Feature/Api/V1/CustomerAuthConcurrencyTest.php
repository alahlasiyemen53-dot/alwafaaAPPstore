<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Otp\RequestCustomerOtpAction;
use App\Enums\ApiErrorCode;
use App\Enums\OtpPurpose;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Governorate;
use App\Services\AuditLogger;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerAuthConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private FakeOtpSender $sender;

    private RequestCustomerOtpAction $requestAction;

    private Governorate $governorate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = new FakeOtpSender;
        $this->requestAction = new RequestCustomerOtpAction(
            otpSender: $this->sender,
            auditLogger: app(AuditLogger::class),
        );

        $yemen = Country::query()->where('code', 'YE')->firstOrFail();
        $this->governorate = Governorate::create([
            'country_id' => $yemen->id,
            'name_ar' => 'صنعاء',
            'name_en' => 'Sanaa',
            'slug' => 'sanaa-concurrency',
            'is_active' => true,
        ]);
    }

    public function test_concurrent_replay_verify_request_only_first_succeeds(): void
    {
        $requestAction = $this->requestAction;
        $challenge = $requestAction->execute('770000111', OtpPurpose::Login);
        $code = $this->sender->getLastCodeFor('+967770000111');

        // First verification request
        $response1 = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '770000111',
            'challenge_id' => $challenge->challenge_id,
            'code' => $code,
            'name' => 'Concurrent User',
            'governorate_id' => $this->governorate->id,
        ]);

        $response1->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in', 'customer']]);

        // Second verification request replay
        $response2 = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '770000111',
            'challenge_id' => $challenge->challenge_id,
            'code' => $code,
            'name' => 'Concurrent User',
            'governorate_id' => $this->governorate->id,
        ]);

        $response2->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::OtpInvalid->value);

        // Assert only one customer exists in the database
        $this->assertSame(1, Customer::where('normalized_phone', '+967770000111')->count());
    }

    public function test_concurrent_first_login_resolution_preserves_single_customer_record(): void
    {
        $requestAction = $this->requestAction;

        // Challenge 1
        $challenge1 = $requestAction->execute('770000222', OtpPurpose::Login);
        $code1 = $this->sender->getLastCodeFor('+967770000222');

        // Fast-forward past cooldown to generate second challenge for same phone
        $this->travel(65)->seconds();
        $challenge2 = $requestAction->execute('770000222', OtpPurpose::Login);
        $code2 = $this->sender->getLastCodeFor('+967770000222');

        // Verify challenge 2
        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '770000222',
            'challenge_id' => $challenge2->challenge_id,
            'code' => $code2,
            'name' => 'Single Record User',
            'governorate_id' => $this->governorate->id,
        ]);

        $response->assertStatus(200);

        // Verify superseded challenge 1 fails
        $responseSuperseded = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '770000222',
            'challenge_id' => $challenge1->challenge_id,
            'code' => $code1,
            'name' => 'Single Record User',
            'governorate_id' => $this->governorate->id,
        ]);

        $responseSuperseded->assertStatus(400)
            ->assertJsonPath('data.code', ApiErrorCode::OtpInvalid->value);

        $this->assertSame(1, Customer::where('normalized_phone', '+967770000222')->count());
    }

    public function test_rapid_otp_requests_trigger_429_cooldown_envelope(): void
    {
        $phone = '770000333';

        // 1st request
        $resp1 = $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone]);
        $resp1->assertStatus(200);

        // 2nd immediate request within cooldown
        $resp2 = $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone]);
        $resp2->assertStatus(429)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::OtpRateLimited->value);
    }
}
