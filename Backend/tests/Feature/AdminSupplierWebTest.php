<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminSupplierWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_guest_is_redirected_from_suppliers(): void
    {
        $this->get('/admin/suppliers')->assertRedirect('/admin/login');
    }

    public function test_user_without_supplier_permissions_cannot_access(): void
    {
        $user = $this->userWithRole('customer_support');
        $supplier = Supplier::create([
            'code' => 'SUP-TEST-1',
            'name' => 'مورد بدون صلاحية',
            'phone' => '777000111',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/suppliers')->assertForbidden();
        $this->actingAs($user)->get('/admin/suppliers/create')->assertForbidden();
        $this->actingAs($user)->post('/admin/suppliers', ['name' => 'تجربة'])->assertForbidden();
        $this->actingAs($user)->get("/admin/suppliers/{$supplier->id}/edit")->assertForbidden();
        $this->actingAs($user)->get("/admin/suppliers/{$supplier->id}/statement")->assertForbidden();
        $this->actingAs($user)->patch("/admin/suppliers/{$supplier->id}/toggle-status")->assertForbidden();
    }

    public function test_authorized_admin_can_perform_supplier_crud_and_toggle_status(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        // Index page
        $this->get('/admin/suppliers')->assertOk()->assertSee('الموردين');

        // Create page
        $this->get('/admin/suppliers/create')->assertOk()->assertSee('إضافة مورد جديد');

        // Store
        $payload = [
            'code' => 'SUP-0099',
            'name' => 'شركة الرواد للتجميل',
            'contact_person' => 'عمر خالد',
            'phone' => '771234567',
            'secondary_phone' => '01445566',
            'email' => 'alrowad@test.local',
            'address' => 'صنعاء - شارع الزبيري',
            'tax_number' => '99887766',
            'notes' => 'ملاحظات تجريبية للمورد',
            'is_active' => '1',
        ];

        $response = $this->post('/admin/suppliers', $payload);
        $response->assertRedirect('/admin/suppliers');
        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-0099',
            'name' => 'شركة الرواد للتجميل',
            'phone' => '771234567',
            'is_active' => true,
        ]);

        $supplier = Supplier::where('code', 'SUP-0099')->firstOrFail();

        // Edit page
        $this->get("/admin/suppliers/{$supplier->id}/edit")->assertOk()->assertSee('بيانات المورد الأساسية');

        // Update
        $this->put("/admin/suppliers/{$supplier->id}", array_merge($payload, [
            'name' => 'شركة الرواد الدولية للتجميل',
        ]))->assertRedirect('/admin/suppliers');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'شركة الرواد الدولية للتجميل',
        ]);

        // Toggle status
        $this->from('/admin/suppliers')->patch("/admin/suppliers/{$supplier->id}/toggle-status")->assertRedirect('/admin/suppliers');
        self::assertFalse($supplier->fresh()->is_active);

        $this->from('/admin/suppliers')->patch("/admin/suppliers/{$supplier->id}/toggle-status")->assertRedirect('/admin/suppliers');
        self::assertTrue($supplier->fresh()->is_active);
    }

    public function test_supplier_statement_displays_entries_and_currency_balances(): void
    {
        $admin = $this->userWithRole('admin');
        $yer = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-STMT',
            'name' => 'مورد تجربة كشف الحساب',
            'phone' => '777112233',
            'is_active' => true,
        ]);

        // Create a ledger credit entry
        SupplierLedgerEntry::create([
            'supplier_id' => $supplier->id,
            'currency_id' => $yer->id,
            'entry_type' => 'manual',
            'reference_type' => Supplier::class,
            'reference_id' => $supplier->id,
            'amount' => 50000.00,
            'direction' => 'credit',
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_amount' => 50000.00,
            'description' => 'رصيد افتتاحي تجريبي',
            'posted_at' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/admin/suppliers/{$supplier->id}/statement");
        $response->assertOk();
        $response->assertSee('كشف حساب المورد');
        $response->assertSee('50,000.00');
        $response->assertSee('رصيد افتتاحي تجريبي');

        // Check balances array
        $balances = $supplier->getBalancesByCurrency();
        self::assertArrayHasKey('YER', $balances);
        self::assertEquals(50000.00, $balances['YER']['balance']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
