<?php

namespace Tests\Unit;

use App\Actions\AssignCustomerTierAction;
use App\Actions\DeleteCustomerTierAction;
use App\Actions\UpdateCustomerTierAction;
use App\Enums\CustomerTierAssignmentSource;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\CustomerTierMembership;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CustomerTierDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_assign_tier_requires_active_tier(): void
    {
        $customer = Customer::create([
            'name' => 'عميل تجريبي',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $inactiveTier = CustomerTier::create([
            'code' => 'PLATINUM',
            'name_ar' => 'بلاتيني',
            'is_active' => false,
            'is_default' => false,
        ]);

        $action = app(AssignCustomerTierAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($customer, $inactiveTier, 'ترقية إدارية');
    }

    public function test_assign_tier_prevents_duplicate_same_tier_transition(): void
    {
        $tier = CustomerTier::firstOrCreate(
            ['code' => 'GOLD'],
            ['name_ar' => 'ذهبي', 'is_active' => true, 'is_default' => false]
        );

        $customer = Customer::create([
            'name' => 'عميل تجريبي',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'current_tier_id' => $tier->id,
        ]);

        CustomerTierMembership::create([
            'customer_id' => $customer->id,
            'customer_tier_id' => $tier->id,
            'starts_at' => now()->subDays(5),
            'ends_at' => null,
            'assignment_source' => CustomerTierAssignmentSource::ADMIN,
            'change_reason' => 'تعيين أولي',
        ]);

        $action = app(AssignCustomerTierAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($customer, $tier, 'إعادة تعيين نفس المستوى');
    }

    public function test_assign_tier_requires_mandatory_reason_for_admin_source(): void
    {
        $tier = CustomerTier::where('code', 'VIP')->firstOrFail();

        $customer = Customer::create([
            'name' => 'عميل تجريبي',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $action = app(AssignCustomerTierAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($customer, $tier, '    ', source: CustomerTierAssignmentSource::ADMIN);
    }

    public function test_exactly_one_active_membership_at_any_time(): void
    {
        $standardTier = CustomerTier::where('code', 'STANDARD')->firstOrFail();
        $vipTier = CustomerTier::where('code', 'VIP')->firstOrFail();

        $customer = Customer::create([
            'name' => 'عميل تجريبي',
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

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@lamma.test',
            'password' => bcrypt('password'),
        ]);

        $action = app(AssignCustomerTierAction::class);
        $action->execute($customer, $vipTier, 'ترقية بسبب تفاعل العميل المتميز', $admin);

        // Verify initial membership is closed
        $initialMembership->refresh();
        $this->assertNotNull($initialMembership->ends_at);

        // Verify exactly one active membership exists
        $activeMemberships = CustomerTierMembership::where('customer_id', $customer->id)->whereNull('ends_at')->get();
        $this->assertCount(1, $activeMemberships);
        $this->assertSame($vipTier->id, $activeMemberships->first()->customer_tier_id);
        $this->assertSame($vipTier->id, $customer->fresh()->current_tier_id);
    }

    public function test_cannot_deactivate_or_delete_default_tier(): void
    {
        $defaultTier = CustomerTier::where('code', 'STANDARD')->firstOrFail();

        $updateAction = app(UpdateCustomerTierAction::class);
        $this->expectException(ValidationException::class);
        $updateAction->execute($defaultTier, ['is_active' => false]);
    }

    public function test_cannot_delete_tier_with_membership_history(): void
    {
        $tier = CustomerTier::firstOrCreate(
            ['code' => 'SILVER'],
            ['name_ar' => 'فضي', 'is_active' => true, 'is_default' => false]
        );

        $customer = Customer::create([
            'name' => 'عميل',
            'phone' => '770000000',
            'normalized_phone' => '+967770000000',
        ]);

        CustomerTierMembership::create([
            'customer_id' => $customer->id,
            'customer_tier_id' => $tier->id,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(1),
            'assignment_source' => CustomerTierAssignmentSource::ADMIN,
            'change_reason' => 'سجل قديم',
        ]);

        $deleteAction = app(DeleteCustomerTierAction::class);
        $this->expectException(ValidationException::class);
        $deleteAction->execute($tier);
    }
}
