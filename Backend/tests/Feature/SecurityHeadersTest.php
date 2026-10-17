<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_response_includes_baseline_security_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_authenticated_admin_pages_include_baseline_security_headers(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_api_responses_include_baseline_security_headers_without_page_csp(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }
}
