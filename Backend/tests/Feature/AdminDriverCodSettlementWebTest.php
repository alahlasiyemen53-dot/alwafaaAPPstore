<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Models\Account;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCodSettlement;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminDriverCodSettlementWebTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private User $unauthorizedUser;

    private Driver $driver;

    private Currency $currency;

    private Cashbox $cashbox;

    private Delivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        $this->currency = Currency::where('code', 'YER')->first() ?? Currency::first();
        $cashboxGl = Account::where('code', '1110')->firstOrFail();

        $this->cashbox = Cashbox::create([
            'code' => 'CSH-01',
            'name_ar' => 'صندوق الاختبار',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $cashboxGl->id,
            'is_active' => true,
        ]);

        $this->driver = Driver::create([
            'name' => 'مندوب الويب',
            'driver_code' => 'DRV-WEB',
            'phone' => '770011223',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'عميل الويب',
            'phone' => '775566778',
            'normalized_phone' => '775566778',
        ]);
        $warehouse = Warehouse::firstOrCreate(['code' => 'WH-01'], [
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-WEB-01',
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'warehouse_id' => $warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => 'delivered',
            'subtotal' => 20000,
            'discount_total' => 0,
            'tax_total' => 0,
            'delivery_fee' => 0,
            'total' => 20000,
        ]);

        $this->delivery = Delivery::create([
            'delivery_number' => 'DEL-WEB-01',
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'status' => DeliveryStatus::Delivered,
            'assigned_at' => now()->subHours(2),
            'delivered_at' => now()->subHour(),
            'cod_amount_to_collect' => 20000,
            'cod_collected_amount' => 20000,
            'cod_currency_id' => $this->currency->id,
        ]);

        $this->accountant = User::create([
            'name' => 'Accountant Web',
            'email' => 'web_accountant@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);

        $this->unauthorizedUser = User::create([
            'name' => 'Customer Support User',
            'email' => 'support_user@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->unauthorizedUser->syncRoles(['customer_support']);
    }

    public function test_unauthorized_user_cannot_access_cod_settlements(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.finance.cod-settlements.index'));

        $response->assertStatus(403);
    }

    public function test_accountant_can_view_index_and_create_views(): void
    {
        $responseIndex = $this->actingAs($this->accountant)
            ->get(route('admin.finance.cod-settlements.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('تسويات عهد المناديب النقدية');

        $responseCreate = $this->actingAs($this->accountant)
            ->get(route('admin.finance.cod-settlements.create'));
        $responseCreate->assertStatus(200);
        $responseCreate->assertSee('إنشاء تسوية عهدة نقدية لمندوب');
    }

    public function test_eligible_deliveries_ajax_endpoint(): void
    {
        $response = $this->actingAs($this->accountant)
            ->getJson(route('admin.finance.cod-settlements.eligible-deliveries', [
                'driver_id' => $this->driver->id,
                'currency_id' => $this->currency->id,
            ]));

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $this->assertNotEmpty($response->json('deliveries'));
        $this->assertEquals(20000, $response->json('outstanding.total_outstanding'));
    }

    public function test_can_store_and_post_settlement_via_web(): void
    {
        // 1. Store Draft
        $response = $this->actingAs($this->accountant)
            ->post(route('admin.finance.cod-settlements.store'), [
                'driver_id' => $this->driver->id,
                'currency_id' => $this->currency->id,
                'treasury_account_type' => 'cashbox',
                'treasury_account_id' => $this->cashbox->id,
                'items' => [
                    [
                        'delivery_id' => $this->delivery->id,
                        'settlement_amount' => 15000,
                    ],
                ],
                'notes' => 'حفظ مسودة عبر الويب',
                'action_type' => 'draft',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('driver_cod_settlements', [
            'driver_id' => $this->driver->id,
            'status' => 'draft',
            'total_settled' => 15000,
        ]);

        $settlement = DriverCodSettlement::latest()->first();
        $response->assertRedirect(route('admin.finance.cod-settlements.show', $settlement));

        // 2. Post Draft
        $postResponse = $this->actingAs($this->accountant)
            ->post(route('admin.finance.cod-settlements.post', $settlement));

        $postResponse->assertSessionHasNoErrors();
        $this->assertEquals(DriverCodSettlementStatus::Posted, $settlement->fresh()->status);
        $this->assertNotNull($settlement->fresh()->journal_entry_id);

        // 3. View Statements
        $statementResponse = $this->actingAs($this->accountant)
            ->get(route('admin.finance.cod-settlements.statements', ['driver_id' => $this->driver->id]));
        $statementResponse->assertStatus(200);
        $statementResponse->assertSee('كشف عهدة المندوب النقدية');

        // 4. View Reconciliation
        $recResponse = $this->actingAs($this->accountant)
            ->get(route('admin.finance.cod-settlements.reconciliation'));
        $recResponse->assertStatus(200);
        $recResponse->assertSee('مطابقة عهد وتحصيلات الدفع عند الاستلام');

        // 5. Reverse Settlement
        $reverseResponse = $this->actingAs($this->accountant)
            ->post(route('admin.finance.cod-settlements.reverse', $settlement), [
                'reason' => 'عكس عبر شاشة الويب للاختبار',
            ]);

        $reverseResponse->assertSessionHasNoErrors();
        $this->assertEquals(DriverCodSettlementStatus::Reversed, $settlement->fresh()->status);
    }
}
