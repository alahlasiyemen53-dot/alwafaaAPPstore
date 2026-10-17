<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerOtpChallenge;
use App\Models\CustomerTier;
use App\Models\Governorate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerOtpVerifyApiTest extends TestCase
{
    use RefreshDatabase;

    private Governorate $governorate;

    protected function setUp(): void
    {
        parent::setUp();

        $yemen = Country::query()->where('code', 'YE')->firstOrFail();

        $this->governorate = Governorate::create([
            'country_id' => $yemen->id,
            'name_ar' => 'صنعاء',
            'name_en' => 'Sanaa',
            'slug' => 'sanaa-otp-test',
            'is_active' => true,
        ]);
    }

    private function createChallenge(string $phone = '771234567', string $code = '123456', array $attributes = []): CustomerOtpChallenge
    {
        return CustomerOtpChallenge::create(array_merge([
            'challenge_id' => (string) Str::uuid(),
            'phone' => $phone,
            'normalized_phone' => '+967771234567',
            'purpose' => OtpPurpose::Login,
            'code_hash' => Hash::make($code),
            'status' => OtpChallengeStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
        ], $attributes));
    }

    public function test_valid_otp_authenticates_existing_customer_and_returns_jwt(): void
    {
        $customer = Customer::create([
            'name' => 'Existing Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $challenge = $this->createChallenge(phone: '771234567', code: '123456');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '771234567',
            'challenge_id' => $challenge->challenge_id,
            'code' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.customer.name', 'Existing Customer')
            ->assertJsonPath('data.customer.phone_verified', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token_type',
                    'access_token',
                    'expires_in',
                    'customer' => ['id', 'name', 'phone', 'is_active', 'phone_verified'],
                ],
            ]);

        $this->assertSame(OtpChallengeStatus::Verified, $challenge->fresh()->status);
        $this->assertNotNull($challenge->fresh()->consumed_at);
    }

    public function test_valid_otp_registers_new_customer_with_default_tier(): void
    {
        $tier = CustomerTier::create([
            'code' => 'BRONZE',
            'name_ar' => 'البرونزي',
            'name_en' => 'Bronze',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $challenge = $this->createChallenge(
            phone: '779999999',
            code: '654321',
            attributes: ['normalized_phone' => '+967779999999']
        );

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '779999999',
            'challenge_id' => $challenge->challenge_id,
            'code' => '654321',
            'name' => 'New Registered Customer',
            'governorate_id' => $this->governorate->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.customer.name', 'New Registered Customer')
            ->assertJsonPath('data.customer.phone_verified', true);

        $this->assertNotNull($response->json('data.customer.tier'));

        $created = Customer::where('normalized_phone', '+967779999999')->first();
        $this->assertNotNull($created);
        $this->assertNotNull($created->current_tier_id);
        $this->assertSame($this->governorate->id, $created->governorate_id);
        $this->assertSame($this->governorate->country_id, $created->country_id);
    }

    public function test_inactive_customer_returns_403_customer_access_restricted(): void
    {
        Customer::create([
            'name' => 'Suspended Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => false,
        ]);

        $challenge = $this->createChallenge(phone: '771234567', code: '123456');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '771234567',
            'challenge_id' => $challenge->challenge_id,
            'code' => '123456',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'CUSTOMER_ACCESS_RESTRICTED');
    }

    public function test_invalid_otp_decrements_attempts_and_returns_400(): void
    {
        Customer::create([
            'name' => 'Test User',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $challenge = $this->createChallenge(phone: '771234567', code: '123456');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '771234567',
            'challenge_id' => $challenge->challenge_id,
            'code' => '999999', // wrong code
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'OTP_INVALID')
            ->assertJsonPath('data.errors.attempts_remaining', 4);

        $this->assertSame(1, $challenge->fresh()->attempts);
    }

    public function test_exhausted_attempts_locks_challenge_and_returns_429(): void
    {
        Customer::create([
            'name' => 'Test User',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $challenge = $this->createChallenge(
            phone: '771234567',
            code: '123456',
            attributes: ['attempts' => 4]
        );

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '771234567',
            'challenge_id' => $challenge->challenge_id,
            'code' => '000000', // 5th failed attempt
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'OTP_ATTEMPTS_EXHAUSTED');

        $this->assertSame(OtpChallengeStatus::Exhausted, $challenge->fresh()->status);
    }

    public function test_expired_otp_returns_400_otp_expired(): void
    {
        Customer::create([
            'name' => 'Test User',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $challenge = $this->createChallenge(
            phone: '771234567',
            code: '123456',
            attributes: ['expires_at' => now()->subMinute()]
        );

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '771234567',
            'challenge_id' => $challenge->challenge_id,
            'code' => '123456',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'OTP_EXPIRED');
    }

    public function test_registration_requires_name_for_new_customer(): void
    {
        $challenge = $this->createChallenge(
            phone: '778888888',
            code: '123456',
            attributes: ['normalized_phone' => '+967778888888']
        );

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '778888888',
            'challenge_id' => $challenge->challenge_id,
            'code' => '123456',
            'governorate_id' => $this->governorate->id,
            // name omitted for new customer
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_registration_requires_governorate_id_for_new_customer(): void
    {
        $challenge = $this->createChallenge(
            phone: '777777777',
            code: '123456',
            attributes: ['normalized_phone' => '+967777777777']
        );

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '777777777',
            'challenge_id' => $challenge->challenge_id,
            'code' => '123456',
            'name' => 'New Without Governorate',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['governorate_id']);
    }

    public function test_existing_customer_can_verify_otp_without_name(): void
    {
        Customer::create([
            'name' => 'Existing Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $challenge = $this->createChallenge(phone: '771234567', code: '123456');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '771234567',
            'challenge_id' => $challenge->challenge_id,
            'code' => '123456',
            // name omitted for existing customer
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.customer.name', 'Existing Customer');
        $this->assertNotNull($response->json('data.refresh_token'));
    }

    public function test_token_refresh_endpoint_issues_new_token(): void
    {
        $customer = Customer::create([
            'name' => 'Refresh User',
            'phone' => '772222222',
            'normalized_phone' => '+967772222222',
            'is_active' => true,
        ]);

        $jwtService = app(\App\Services\Auth\CustomerJwtService::class);
        $refreshToken = $jwtService->generateRefreshToken($customer);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true);
        $this->assertNotNull($response->json('data.access_token'));
        $this->assertNotNull($response->json('data.refresh_token'));
    }
}
