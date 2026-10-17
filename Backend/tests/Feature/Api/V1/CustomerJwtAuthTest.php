<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\User;
use App\Services\Auth\CustomerJwtService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerJwtAuthTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(CustomerJwtService::class);
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Sara Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ], $attributes));
    }

    public function test_unauthenticated_request_returns_canonical_auth_required_error(): void
    {
        $response = $this->getJson('/api/v1/customer/me');

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_tampered_or_invalid_jwt_returns_auth_required_error(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid.token.payload',
        ])->getJson('/api/v1/customer/me');

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_expired_jwt_returns_auth_required_error(): void
    {
        $customer = $this->createCustomer(['is_active' => true]);

        // Generate expired token
        $secret = (string) config('jwt.secret', config('app.key'));
        $algo = (string) config('jwt.algo', 'HS256');
        $expiredPayload = [
            'iss' => config('jwt.issuer'),
            'aud' => config('jwt.audience'),
            'sub' => (string) $customer->id,
            'iat' => time() - 7200,
            'nbf' => time() - 7200,
            'exp' => time() - 3600, // expired 1 hour ago
            'token_type' => 'access',
        ];
        $token = JWT::encode($expiredPayload, $secret, $algo);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/customer/me');

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_inactive_customer_cannot_authenticate_with_valid_jwt(): void
    {
        $customer = $this->createCustomer(['is_active' => false]);
        $token = $this->jwtService->generateAccessToken($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/customer/me');

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_refresh_token_cannot_be_used_as_access_token(): void
    {
        $customer = $this->createCustomer(['is_active' => true]);
        $refreshToken = $this->jwtService->generateRefreshToken($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$refreshToken}",
        ])->getJson('/api/v1/customer/me');

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_active_customer_with_valid_jwt_authenticates_successfully(): void
    {
        $customer = $this->createCustomer([
            'name' => 'Sara Customer',
            'phone' => '+967771234567',
            'is_active' => true,
        ]);

        $token = $this->jwtService->generateAccessToken($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/customer/me');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', 'Sara Customer')
            ->assertJsonPath('data.phone', '+967771234567')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_customer_jwt_cannot_access_admin_sanctum_routes(): void
    {
        $customer = $this->createCustomer(['is_active' => true]);
        $token = $this->jwtService->generateAccessToken($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/admin/profile');

        $response->assertStatus(401);
    }

    public function test_admin_sanctum_token_cannot_access_customer_jwt_routes(): void
    {
        $user = User::factory()->create();
        $adminToken = $user->createToken('admin-test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->getJson('/api/v1/customer/me');

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }
}
