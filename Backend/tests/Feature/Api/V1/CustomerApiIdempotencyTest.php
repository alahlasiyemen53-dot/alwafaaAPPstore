<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Services\Auth\CustomerJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerApiIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(CustomerJwtService::class);
        Cache::flush();
    }

    public function test_post_echo_executes_successfully_with_idempotency_key(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $response = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/proof/echo', [
            'message' => 'Hello World',
            'counter' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.echo.message', 'Hello World')
            ->assertJsonPath('data.echo.counter', 1)
            ->assertHeaderMissing('X-Idempotent-Replay');
    }

    public function test_replayed_request_returns_cached_response_with_replay_header(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $response1 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/proof/echo', [
            'message' => 'Idempotent Message',
            'counter' => 42,
        ]);

        $response1->assertOk();
        $timestamp1 = $response1->json('data.processed_at');

        // Second request with exact same Idempotency-Key
        $response2 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/proof/echo', [
            'message' => 'Different payload will be ignored because key matches',
            'counter' => 99,
        ]);

        $response2->assertOk()
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.echo.message', 'Idempotent Message')
            ->assertJsonPath('data.echo.counter', 42);

        $this->assertSame($timestamp1, $response2->json('data.processed_at'));
    }

    public function test_invalid_idempotency_key_format_returns_bad_request(): void
    {
        $response = $this->withHeaders([
            'Idempotency-Key' => 'short', // less than 16 chars
        ])->postJson('/api/v1/proof/echo', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'MALFORMED_REQUEST');
    }

    public function test_public_endpoint_rate_limiter_triggers_429(): void
    {
        RateLimiter::clear('127.0.0.1');

        // Limit is 60 per minute for api.customer.public
        for ($i = 0; $i < 60; $i++) {
            $res = $this->getJson('/api/v1/ping');
            $res->assertOk();
        }

        // 61st request should be throttled
        $throttled = $this->getJson('/api/v1/ping');
        $throttled->assertStatus(429)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'RATE_LIMITED');
    }

    public function test_authenticated_endpoint_rate_limiter_triggers_429(): void
    {
        $customer = Customer::create([
            'name' => 'Rate Limit Customer',
            'phone' => '779999999',
            'normalized_phone' => '+967779999999',
            'is_active' => true,
        ]);
        $token = $this->jwtService->generateAccessToken($customer);

        // Limit is 120 per minute for api.customer.auth
        for ($i = 0; $i < 120; $i++) {
            $res = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                ->getJson('/api/v1/customer/me');
            $res->assertOk();
        }

        // 121st request should be throttled
        $throttled = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/customer/me');
        $throttled->assertStatus(429)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'RATE_LIMITED');
    }
}
