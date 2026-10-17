<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class StagingSmokeGateTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->addRole('super_admin');
    }

    public function test_smoke_login_page_renders(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('تسجيل الدخول');
    }

    public function test_smoke_dashboard_renders(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('الرئيسية')
            ->assertSee('مساحة إدارة المتجر');
    }

    public function test_smoke_catalog_products_and_variants(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('المنتجات');

        $this->actingAs($this->superAdmin)
            ->get('/admin/products/create')
            ->assertOk()
            ->assertSee('منتج جديد')
            ->assertSee('حفظ المنتج');
    }

    public function test_smoke_inventory_and_warehouses(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/inventory')
            ->assertOk()
            ->assertSee('المخزون');

        $this->actingAs($this->superAdmin)
            ->get('/admin/stock-movements')
            ->assertOk()
            ->assertSee('حركات المخزون');
    }

    public function test_smoke_orders_and_deliveries(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('الطلبات');

        $this->actingAs($this->superAdmin)
            ->get('/admin/deliveries')
            ->assertOk()
            ->assertSee('عمليات التوصيل');
    }

    public function test_smoke_finance_treasury_and_gl(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/finance/accounts')
            ->assertOk()
            ->assertSee('دليل الحسابات');

        $this->actingAs($this->superAdmin)
            ->get('/admin/finance/journal-entries')
            ->assertOk()
            ->assertSee('قيود اليومية');

        $this->actingAs($this->superAdmin)
            ->get('/admin/finance/treasury/cashboxes')
            ->assertOk()
            ->assertSee('الصناديق النقدية');
    }

    public function test_smoke_reports_views(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/reports/sales')
            ->assertOk()
            ->assertSee('المبيعات');

        $this->actingAs($this->superAdmin)
            ->get('/admin/reports/inventory')
            ->assertOk()
            ->assertSee('المخزون');
    }

    public function test_smoke_scheduler_and_queue_can_execute(): void
    {
        $this->assertEquals(0, Artisan::call('schedule:list'));
        $this->assertEquals(0, Artisan::call('queue:work', ['--once' => true]));
    }

    public function test_smoke_logout_invalidates_session(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/logout');

        $response->assertRedirect('/admin/login');
        $this->assertGuest();
    }
}
