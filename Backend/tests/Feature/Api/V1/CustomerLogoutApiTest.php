<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Services\Auth\CustomerJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomerLogoutApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(CustomerJwtService::class);
        Cache::flush();
    }

    public function test_unauthenticated_logout_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_logout_revokes_jwt_immediately(): void
    {
        $customer = Customer::create([
            'name' => 'Logout Test Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $token = $this->jwtService->generateAccessToken($customer);

        // Verify token is initially valid
        $resBefore = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me');
        $resBefore->assertOk();

        // Perform logout
        $resLogout = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/logout');
        $resLogout->assertOk()
            ->assertJsonPath('status', true);

        // Immediate subsequent request with same token must be rejected (401)
        $resAfter = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me');
        $resAfter->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_revoking_token_does_not_affect_another_customer_token(): void
    {
        $customerA = Customer::create([
            'name' => 'Customer A',
            'phone' => '771111111',
            'normalized_phone' => '+967771111111',
            'is_active' => true,
        ]);

        $customerB = Customer::create([
            'name' => 'Customer B',
            'phone' => '772222222',
            'normalized_phone' => '+967772222222',
            'is_active' => true,
        ]);

        $tokenA = $this->jwtService->generateAccessToken($customerA);
        $tokenB = $this->jwtService->generateAccessToken($customerB);

        // Customer A logs out
        $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Customer A token is revoked
        $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
            ->getJson('/api/v1/me')
            ->assertStatus(401);

        // Customer B token remains completely valid
        $this->withHeaders(['Authorization' => "Bearer {$tokenB}"])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Customer B');
    }
}
