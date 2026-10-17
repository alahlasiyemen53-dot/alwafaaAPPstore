<?php

namespace Tests\Feature;

use App\Enums\CustomerTierAssignmentSource;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\CustomerTierMembership;
use App\Models\Role;
use App\Models\User;
use App\Queries\AdminCustomerTierQuery;
use App\Queries\GetCustomerTierHistoryQuery;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CustomerTierQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }

    public function test_customer_tiers_query_does_not_grow_with_tier_count(): void
    {
        $queryService = app(AdminCustomerTierQuery::class);

        // 1. Measure with existing default tiers
        DB::flushQueryLog();
        DB::enableQueryLog();
        $queryService->paginate(new Request);
        $queriesBase = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 2. Add 15 more tiers
        for ($i = 1; $i <= 15; $i++) {
            CustomerTier::create([
                'code' => "TIER_N1_{$i}",
                'name_ar' => "مستوى تجريبي {$i}",
                'is_active' => true,
                'is_default' => false,
                'sort_order' => $i,
            ]);
        }

        // 3. Measure again with 15 more tiers
        DB::flushQueryLog();
        DB::enableQueryLog();
        $queryService->paginate(new Request, 50);
        $queriesWithManyTiers = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Assert query count is strictly constant (no N+1 for active_customers_count)
        $this->assertSame($queriesBase, $queriesWithManyTiers, "Customer tiers query must not grow with number of tiers. Baseline: {$queriesBase}, with 15 tiers: {$queriesWithManyTiers}");
        $this->assertLessThanOrEqual(3, $queriesWithManyTiers);
    }

    public function test_customer_listing_with_tiers_does_not_grow_per_customer_row(): void
    {
        $standardTier = CustomerTier::where('code', 'STANDARD')->firstOrFail();
        $vipTier = CustomerTier::where('code', 'VIP')->firstOrFail();

        // Create 2 customers
        for ($i = 1; $i <= 2; $i++) {
            Customer::create([
                'name' => "عميل {$i}",
                'phone' => "7710000{$i}",
                'normalized_phone' => "+9677710000{$i}",
                'current_tier_id' => $standardTier->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        Customer::query()->with(['tier:id,code,name_ar,name_en,is_default'])->withCount('addresses')->paginate(15);
        $queriesFor2 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Create 18 more customers with alternating tiers
        for ($i = 3; $i <= 20; $i++) {
            Customer::create([
                'name' => "عميل {$i}",
                'phone' => "7710000{$i}",
                'normalized_phone' => "+9677710000{$i}",
                'current_tier_id' => ($i % 2 === 0) ? $vipTier->id : $standardTier->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        Customer::query()->with(['tier:id,code,name_ar,name_en,is_default'])->withCount('addresses')->paginate(15);
        $queriesFor20 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Adding more customer rows must NOT add extra queries to fetch tiers
        $this->assertSame($queriesFor2, $queriesFor20, 'Eager loading prevents N+1 queries for customer tier relation.');
        $this->assertLessThanOrEqual(4, $queriesFor20);
    }

    public function test_customer_tier_history_query_does_not_grow_with_history_records(): void
    {
        $admin = $this->userWithRole('admin');
        $vipTier = CustomerTier::where('code', 'VIP')->firstOrFail();
        $historyService = app(GetCustomerTierHistoryQuery::class);

        $customer = Customer::create([
            'name' => 'فؤاد المقطري',
            'phone' => '775556677',
            'normalized_phone' => '+96775556677',
            'current_tier_id' => $vipTier->id,
        ]);

        // 1 record
        CustomerTierMembership::create([
            'customer_id' => $customer->id,
            'customer_tier_id' => $vipTier->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
            'assignment_source' => CustomerTierAssignmentSource::ADMIN,
            'assigned_by' => $admin->id,
            'change_reason' => 'تعيين 1',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $historyService->getBounded($customer, 20);
        $queriesFor1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Add 10 more records
        for ($i = 2; $i <= 11; $i++) {
            CustomerTierMembership::create([
                'customer_id' => $customer->id,
                'customer_tier_id' => $vipTier->id,
                'starts_at' => now()->subMonths(15 - $i),
                'ends_at' => now()->subMonths(14 - $i),
                'assignment_source' => CustomerTierAssignmentSource::ADMIN,
                'assigned_by' => $admin->id,
                'change_reason' => "تعيين {$i}",
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $historyService->getBounded($customer, 20);
        $queriesFor11 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queriesFor1, $queriesFor11, 'Eager loaded tier history query count must be constant.');
        $this->assertLessThanOrEqual(3, $queriesFor11);
    }
}
