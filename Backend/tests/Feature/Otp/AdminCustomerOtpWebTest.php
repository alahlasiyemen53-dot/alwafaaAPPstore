<?php

namespace Tests\Feature\Otp;

use App\Actions\Otp\RequestCustomerOtpAction;
use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Models\CustomerOtpChallenge;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Otp\FakeOtpSender;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCustomerOtpWebTest extends TestCase
{
    use RefreshDatabase;

    private FakeOtpSender $sender;
    private RequestCustomerOtpAction $requestAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->sender = new FakeOtpSender();
        $this->requestAction = new RequestCustomerOtpAction(
            otpSender: $this->sender,
            auditLogger: app(AuditLogger::class),
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/customers/otp-challenges')
            ->assertRedirect('/admin/login');
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $user = $this->userWithRole('purchasing');
        $this->actingAs($user)
            ->get('/admin/customers/otp-challenges')
            ->assertForbidden();
    }

    public function test_authorized_admin_can_view_otp_challenges_list(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        // Generate a challenge
        $challenge = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);

        $response = $this->get('/admin/customers/otp-challenges');
        $response->assertOk();
        $response->assertSee('سجلات وتحديات رموز التحقق (OTP)');
        $response->assertSee('إجمالي طلبات OTP');
        $response->assertSee($challenge->masked_phone);
    }

    public function test_otp_view_masks_phone_and_never_exposes_code_hash_or_plaintext(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $challenge = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);
        $code = $this->sender->getLastCodeFor('+967770123456');

        $response = $this->get('/admin/customers/otp-challenges');
        $response->assertOk();

        // Must display masked phone (+967 770 *** 456)
        $response->assertSee($challenge->masked_phone);

        // MUST NOT display the plaintext OTP code anywhere in the HTML
        $response->assertDontSee($code);

        // MUST NOT display the raw code_hash anywhere in the HTML
        $response->assertDontSee($challenge->code_hash);
    }

    public function test_admin_can_filter_otp_challenges_by_phone_and_status(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $challenge1 = $this->requestAction->execute('770111111', OtpPurpose::PhoneVerification);

        $this->travel(65)->seconds();
        $challenge2 = $this->requestAction->execute('770222222', OtpPurpose::PhoneVerification);

        // Filter by phone search
        $responseSearch = $this->get('/admin/customers/otp-challenges?search=770111111');
        $responseSearch->assertOk();
        $responseSearch->assertSee($challenge1->masked_phone);
        $responseSearch->assertDontSee($challenge2->masked_phone);

        // Filter by status
        $responseStatus = $this->get('/admin/customers/otp-challenges?status=' . OtpChallengeStatus::Pending->value);
        $responseStatus->assertOk();
        $responseStatus->assertSee($challenge1->masked_phone);
        $responseStatus->assertSee($challenge2->masked_phone);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $roleModel = Role::where('name', $role)->firstOrFail();
        $user->syncRoles([$roleModel]);

        return $user;
    }
}
