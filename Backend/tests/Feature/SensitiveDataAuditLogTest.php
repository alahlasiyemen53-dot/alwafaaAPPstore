<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 19: failed-login attempts are now audited (AdminWebController::authenticate,
 * AuthenticateAdminAction) so operators can see brute-force patterns. This test locks
 * in that the audited metadata never contains the submitted password.
 */
final class SensitiveDataAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_web_login_audit_entry_never_contains_the_password(): void
    {
        User::factory()->create(['is_active' => true, 'email' => 'audit-target@example.com']);

        $this->post('/admin/login', [
            'identifier' => 'audit-target@example.com',
            'password' => 'super-secret-password-value',
        ]);

        $entry = AuditLog::where('action', 'admin.web_login.failed')->latest('id')->firstOrFail();

        $this->assertArrayNotHasKey('password', $entry->metadata);
        $this->assertStringNotContainsString('super-secret-password-value', json_encode($entry->metadata));
    }

    public function test_failed_api_login_audit_entry_never_contains_the_password(): void
    {
        User::factory()->create(['is_active' => true, 'email' => 'audit-target-api@example.com']);

        $this->postJson('/api/v1/admin/login', [
            'identifier' => 'audit-target-api@example.com',
            'password' => 'super-secret-password-value',
        ]);

        $entry = AuditLog::where('action', 'admin.login.failed')->latest('id')->firstOrFail();

        $this->assertArrayNotHasKey('password', $entry->metadata);
        $this->assertStringNotContainsString('super-secret-password-value', json_encode($entry->metadata));
    }
}
