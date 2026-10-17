<?php

namespace Tests\Feature;

use App\Actions\VerifyCustomerPhoneAction;
use App\Enums\PhoneVerificationMethod;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_authorized_admin_can_manually_verify_customer_phone(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل غير موثق',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'phone_verified_at' => null,
            'phone_verification_method' => null,
            'phone_verified_by' => null,
        ]);

        $response = $this->actingAs($admin)->post("/admin/customers/{$customer->id}/verify-phone");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertTrue($customer->isPhoneVerified());
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertSame(PhoneVerificationMethod::Admin, $customer->phone_verification_method);
        $this->assertSame($admin->id, $customer->phone_verified_by);

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.customer.phone_verified',
            'actor_id' => $admin->id,
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
        ]);
    }

    public function test_unauthorized_user_cannot_verify_phone(): void
    {
        $cashier = $this->userWithRole('cashier');

        $customer = Customer::create([
            'name' => 'عميل غير موثق',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $response = $this->actingAs($cashier)->post("/admin/customers/{$customer->id}/verify-phone");
        $response->assertForbidden();

        $this->assertFalse($customer->fresh()->isPhoneVerified());
    }

    public function test_repeated_manual_verification_is_idempotent(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل موثق مسبقاً',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        // First verification
        $this->actingAs($admin)->post("/admin/customers/{$customer->id}/verify-phone");
        $firstVerifiedAt = $customer->fresh()->phone_verified_at;

        // Second click / repeat
        $this->actingAs($admin)->post("/admin/customers/{$customer->id}/verify-phone");
        $secondVerifiedAt = $customer->fresh()->phone_verified_at;

        $this->assertEquals($firstVerifiedAt, $secondVerifiedAt);
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.customer.phone_verified')->count());
    }

    public function test_changing_display_format_preserves_phone_verification(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل موثق',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'phone_verified_at' => now()->subDay(),
            'phone_verification_method' => PhoneVerificationMethod::Admin,
            'phone_verified_by' => $admin->id,
        ]);

        $originalVerifiedAt = $customer->phone_verified_at;

        // Update phone format only: e.g. +967 77 123 4567 instead of 771234567
        $response = $this->actingAs($admin)->put("/admin/customers/{$customer->id}", [
            'name' => $customer->name,
            'phone' => '+967 77 123 4567',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $customer->refresh();

        // Verification MUST be preserved
        $this->assertTrue($customer->isPhoneVerified());
        $this->assertEquals($originalVerifiedAt->toDateTimeString(), $customer->phone_verified_at->toDateTimeString());
        $this->assertSame(PhoneVerificationMethod::Admin, $customer->phone_verification_method);
        $this->assertSame($admin->id, $customer->phone_verified_by);
    }

    public function test_changing_canonical_phone_resets_verification_atomically(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل موثق',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'phone_verified_at' => now()->subDay(),
            'phone_verification_method' => PhoneVerificationMethod::Admin,
            'phone_verified_by' => $admin->id,
        ]);

        // Change to a new canonical phone number
        $response = $this->actingAs($admin)->put("/admin/customers/{$customer->id}", [
            'name' => $customer->name,
            'phone' => '779999999',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $customer->refresh();

        // Verification MUST be reset
        $this->assertFalse($customer->isPhoneVerified());
        $this->assertNull($customer->phone_verified_at);
        $this->assertNull($customer->phone_verification_method);
        $this->assertNull($customer->phone_verified_by);
        $this->assertSame('+967779999999', $customer->normalized_phone);

        // Verification reset audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.customer.phone_verification_reset',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_future_otp_verification_compatibility(): void
    {
        $customer = Customer::create([
            'name' => 'عميل توثيق OTP',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $action = app(VerifyCustomerPhoneAction::class);
        $verifiedCustomer = $action->execute($customer, PhoneVerificationMethod::Otp, null);

        $this->assertTrue($verifiedCustomer->isPhoneVerified());
        $this->assertSame(PhoneVerificationMethod::Otp, $verifiedCustomer->phone_verification_method);
        $this->assertNull($verifiedCustomer->phone_verified_by);
        $this->assertNotNull($verifiedCustomer->phone_verified_at);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
