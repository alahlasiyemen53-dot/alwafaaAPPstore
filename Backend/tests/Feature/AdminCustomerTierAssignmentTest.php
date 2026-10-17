<?php

namespace Tests\Feature;

use App\Enums\CustomerTierAssignmentSource;
use App\Models\Customer;
use App\Models\CustomerLoyaltyEntry;
use App\Models\CustomerTier;
use App\Models\CustomerTierMembership;
use App\Models\CustomerWalletEntry;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCustomerTierAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::create([
            'name' => "User {$role}",
            'email' => "{$role}_".uniqid().'@lamma.test',
            'password' => bcrypt('password'),
        ]);

        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->attach($roleModel);
        }

        return $user;
    }

    public function test_authorized_admin_can_change_customer_tier_with_reason(): void
    {
        $admin = $this->userWithRole('admin');
        $standardTier = CustomerTier::where('code', 'STANDARD')->firstOrFail();
        $vipTier = CustomerTier::where('code', 'VIP')->firstOrFail();

        $customer = Customer::create([
            'name' => 'محمد سعيد',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'current_tier_id' => $standardTier->id,
        ]);

        $initialMembership = CustomerTierMembership::create([
            'customer_id' => $customer->id,
            'customer_tier_id' => $standardTier->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
            'assignment_source' => CustomerTierAssignmentSource::SYSTEM_DEFAULT,
        ]);

        $response = $this->actingAs($admin)->post("/admin/customers/{$customer->id}/tier", [
            'tier_id' => $vipTier->id,
            'reason' => 'تم الترقية إلى VIP تقديراً لولاء العميل المستمر مع الشركة',
        ]);

        $response->assertRedirect("/admin/customers/{$customer->id}");
        $response->assertSessionHas('success');

        // Verify customer current tier
        $this->assertSame($vipTier->id, $customer->fresh()->current_tier_id);

        // Verify old membership closed
        $this->assertNotNull($initialMembership->fresh()->ends_at);

        // Verify new membership created
        $currentMembership = CustomerTierMembership::where('customer_id', $customer->id)
            ->whereNull('ends_at')
            ->first();

        $this->assertNotNull($currentMembership);
        $this->assertSame($vipTier->id, $currentMembership->customer_tier_id);
        $this->assertSame($admin->id, $currentMembership->assigned_by);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer_tier_membership.changed',
            'actor_id' => $admin->id,
            'subject_id' => $customer->id,
        ]);
    }

    public function test_unauthorized_user_cannot_assign_customer_tier(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = Customer::create([
            'name' => 'سالم عبده',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);
        $vipTier = CustomerTier::where('code', 'VIP')->firstOrFail();

        $response = $this->actingAs($cashier)->post("/admin/customers/{$customer->id}/tier", [
            'tier_id' => $vipTier->id,
            'reason' => 'محاولة تعيين بدون صلاحية',
        ]);

        $response->assertForbidden();
    }

    public function test_assignment_fails_if_reason_is_missing_or_short(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = Customer::create([
            'name' => 'سالم عبده',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);
        $vipTier = CustomerTier::where('code', 'VIP')->firstOrFail();

        $response = $this->actingAs($admin)->post("/admin/customers/{$customer->id}/tier", [
            'tier_id' => $vipTier->id,
            'reason' => 'قصير',
        ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_assignment_fails_if_target_tier_is_same_as_current(): void
    {
        $admin = $this->userWithRole('admin');
        $standardTier = CustomerTier::where('code', 'STANDARD')->firstOrFail();

        $customer = Customer::create([
            'name' => 'سالم عبده',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'current_tier_id' => $standardTier->id,
        ]);

        $response = $this->actingAs($admin)->post("/admin/customers/{$customer->id}/tier", [
            'tier_id' => $standardTier->id,
            'reason' => 'محاولة تغيير إلى نفس المستوى الحالي',
        ]);

        $response->assertSessionHasErrors(['tier_id']);
    }

    public function test_financial_and_loyalty_safety_invariants_during_tier_change(): void
    {
        $admin = $this->userWithRole('admin');
        $standardTier = CustomerTier::where('code', 'STANDARD')->firstOrFail();
        $vipTier = CustomerTier::where('code', 'VIP')->firstOrFail();

        $customer = Customer::create([
            'name' => 'فهد الأحمد',
            'phone' => '772223344',
            'normalized_phone' => '+967772223344',
            'current_tier_id' => $standardTier->id,
        ]);

        // Record baselines before tier change
        $initialWalletEntriesCount = CustomerWalletEntry::count();
        $initialLoyaltyEntriesCount = CustomerLoyaltyEntry::count();
        $initialJournalEntriesCount = JournalEntry::count();

        // Perform tier change to VIP
        $response = $this->actingAs($admin)->post("/admin/customers/{$customer->id}/tier", [
            'tier_id' => $vipTier->id,
            'reason' => 'ترقية استثنائية لكبار العملاء VIP',
        ]);

        $response->assertRedirect("/admin/customers/{$customer->id}");

        // Invariant: MUST NOT create any wallet entries
        $this->assertSame($initialWalletEntriesCount, CustomerWalletEntry::count(), 'Customer wallet entries must not be created on tier change.');

        // Invariant: MUST NOT create any loyalty entries
        $this->assertSame($initialLoyaltyEntriesCount, CustomerLoyaltyEntry::count(), 'Customer loyalty entries must not be created on tier change.');

        // Invariant: MUST NOT create any general ledger journal entries
        $this->assertSame($initialJournalEntriesCount, JournalEntry::count(), 'General ledger journal entries must not be created on tier change.');
    }
}
