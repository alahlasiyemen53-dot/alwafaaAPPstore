<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin-login');
    }

    public function test_web_login_is_rate_limited_after_five_failed_attempts(): void
    {
        User::factory()->create(['is_active' => true, 'email' => 'target@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/admin/login', [
                'identifier' => 'target@example.com',
                'password' => 'wrong-password',
            ]);
            $response->assertStatus(302);
            $response->assertSessionHasErrors('identifier');
        }

        $response = $this->post('/admin/login', [
            'identifier' => 'target@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_api_login_is_rate_limited_after_five_failed_attempts(): void
    {
        User::factory()->create(['is_active' => true, 'email' => 'target-api@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/admin/login', [
                'identifier' => 'target-api@example.com',
                'password' => 'wrong-password',
            ]);
            $response->assertStatus(401);
        }

        $response = $this->postJson('/api/v1/admin/login', [
            'identifier' => 'target-api@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }
}
