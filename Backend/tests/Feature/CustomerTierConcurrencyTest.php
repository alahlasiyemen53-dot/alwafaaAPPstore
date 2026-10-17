<?php

namespace Tests\Feature;

use App\Actions\AssignCustomerTierAction;
use App\Enums\CustomerTierAssignmentSource;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\CustomerTierMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerTierConcurrencyTest extends TestCase
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

    public function test_two_sequential_or_concurrent_tier_changes_produce_exactly_one_active_membership(): void
    {
        $adminA = $this->userWithRole('admin');
        $adminB = $this->userWithRole('admin');

        $standardTier = CustomerTier::where('code', 'STANDARD')->firstOrFail();
        $vipTier = CustomerTier::where('code', 'VIP')->firstOrFail();
        $goldTier = CustomerTier::create([
            'code' => 'GOLD',
            'name_ar' => 'المستوى الذهبي',
            'is_active' => true,
            'is_default' => false,
        ]);

        $customer = Customer::create([
            'name' => 'جمال عبدالملك',
            'phone' => '773334455',
            'normalized_phone' => '+967773334455',
            'current_tier_id' => $standardTier->id,
        ]);

        CustomerTierMembership::create([
            'customer_id' => $customer->id,
            'customer_tier_id' => $standardTier->id,
            'starts_at' => now()->subMonths(2),
            'ends_at' => null,
            'assignment_source' => CustomerTierAssignmentSource::SYSTEM_DEFAULT,
        ]);

        $action = app(AssignCustomerTierAction::class);

        // Simulation of Admin A changing to VIP
        $action->execute($customer, $vipTier, 'ترقية بواسطة الإدارة أ', $adminA);

        // Simulation of Admin B changing to Gold subsequently
        $action->execute($customer->fresh(), $goldTier, 'ترقية بواسطة الإدارة ب', $adminB);

        // Assert customer current tier is the latest (Gold)
        $this->assertSame($goldTier->id, $customer->fresh()->current_tier_id);

        // Assert exactly ONE active membership exists
        $activeMemberships = CustomerTierMembership::where('customer_id', $customer->id)
            ->whereNull('ends_at')
            ->get();

        $this->assertCount(1, $activeMemberships, 'Customer must have exactly one active membership at any time.');
        $this->assertSame($goldTier->id, $activeMemberships->first()->customer_tier_id);

        // Assert total membership history rows = 3 (STANDARD -> VIP -> GOLD)
        $totalMemberships = CustomerTierMembership::where('customer_id', $customer->id)->count();
        $this->assertSame(3, $totalMemberships);

        // Assert previous memberships have closed ends_at timestamps
        $closedMemberships = CustomerTierMembership::where('customer_id', $customer->id)
            ->whereNotNull('ends_at')
            ->get();
        $this->assertCount(2, $closedMemberships);
    }
}
