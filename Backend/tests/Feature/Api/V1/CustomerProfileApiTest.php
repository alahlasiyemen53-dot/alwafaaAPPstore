<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Services\Auth\CustomerJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(CustomerJwtService::class);
    }

    public function test_unauthenticated_request_to_me_returns_401(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_authenticated_customer_can_retrieve_profile_via_me_and_customer_me(): void
    {
        $customer = Customer::create([
            'name' => 'Sara Profile Test',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'email' => 'sara@example.com',
            'is_active' => true,
            'phone_verified_at' => now(),
            'phone_verification_method' => 'otp',
        ]);

        $token = $this->jwtService->generateAccessToken($customer);

        // Test GET /api/v1/me
        $responseMe = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me');

        $responseMe->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', 'Sara Profile Test')
            ->assertJsonPath('data.phone', '771234567')
            ->assertJsonPath('data.normalized_phone', '+967771234567')
            ->assertJsonPath('data.email', 'sara@example.com')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.phone_verified', true);

        // Test GET /api/v1/customer/me
        $responseCustomerMe = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/customer/me');

        $responseCustomerMe->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $customer->id);
    }

    public function test_inactive_customer_cannot_access_profile_with_jwt(): void
    {
        $customer = Customer::create([
            'name' => 'Disabled Customer',
            'phone' => '770000000',
            'normalized_phone' => '+967770000000',
            'is_active' => false,
        ]);

        $token = $this->jwtService->generateAccessToken($customer);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me');

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }
}
