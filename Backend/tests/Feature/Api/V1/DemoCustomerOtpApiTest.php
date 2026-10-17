<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Models\Customer;
use App\Models\CustomerOtpChallenge;
use App\Support\DemoCustomerOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoCustomerOtpApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'otp.demo.enabled' => true,
            'otp.demo.phone' => '771771771',
            'otp.demo.code' => '123456',
            'otp.demo.name' => 'مستخدم تجريبي',
        ]);
    }

    public function test_demo_phone_request_uses_fixed_otp_and_skips_cooldown(): void
    {
        $first = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '771771771',
        ]);

        $first->assertOk()->assertJsonPath('status', true);

        $second = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '771771771',
        ]);

        $second->assertOk()->assertJsonPath('status', true);

        $challenge = CustomerOtpChallenge::query()
            ->where('challenge_id', $second->json('data.challenge_id'))
            ->firstOrFail();

        $this->assertTrue(Hash::check(DemoCustomerOtp::code(), $challenge->code_hash));
    }

    public function test_demo_customer_can_login_with_fixed_otp(): void
    {
        $this->seed(\Database\Seeders\DemoCustomerSeeder::class);

        $customer = Customer::query()
            ->where('normalized_phone', '+967771771771')
            ->firstOrFail();

        $this->assertSame('مستخدم تجريبي', $customer->name);
        $this->assertTrue($customer->is_active);
        $this->assertTrue($customer->isPhoneVerified());

        $request = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '771771771',
        ]);

        $request->assertOk();

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '771771771',
            'challenge_id' => $request->json('data.challenge_id'),
            'code' => '123456',
        ]);

        $verify->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.customer.name', 'مستخدم تجريبي')
            ->assertJsonPath('data.customer.is_active', true)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'customer'],
            ]);
    }

    public function test_demo_otp_bypass_accepts_fixed_code_even_when_hash_differs(): void
    {
        DemoCustomerOtp::ensureCustomer();

        $challenge = CustomerOtpChallenge::query()->create([
            'challenge_id' => (string) Str::uuid(),
            'phone' => '771771771',
            'normalized_phone' => '+967771771771',
            'purpose' => OtpPurpose::Login,
            'code_hash' => Hash::make('999999'),
            'status' => OtpChallengeStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
        ]);

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '771771771',
            'challenge_id' => $challenge->challenge_id,
            'code' => '123456',
        ]);

        $verify->assertOk()->assertJsonPath('status', true);
    }
}
