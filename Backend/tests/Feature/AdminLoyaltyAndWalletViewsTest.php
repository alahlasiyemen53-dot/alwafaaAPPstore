<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\LoyaltyAccountStatus;
use App\Enums\ReturnReasonCode;
use App\Enums\SalesReturnStatus;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerWalletAccount;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\LoyaltyAndWalletPermissionsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminLoyaltyAndWalletViewsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Customer $customer;

    private Currency $currency;

    private Warehouse $warehouse;

    private AccountingPeriod $openPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(LoyaltyAndWalletPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'مدير النظام',
            'email' => 'views_admin@lamma.test',
            'password' => bcrypt('secret123'),
        ]);
        $this->superAdmin->syncRoles(['super_admin']);

        $this->customer = Customer::create([
            'name' => 'عميل تجريبي',
            'phone' => '771122334',
            'normalized_phone' => '771122334',
            'is_active' => true,
        ]);

        $this->currency = Currency::where('is_default', true)->firstOrFail();

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع تجريبي',
            'name_en' => 'Test Warehouse',
            'code' => 'WH-TEST',
            'is_active' => true,
        ]);

        $this->openPeriod = AccountingPeriod::create([
            'name' => 'فترة تجريبية 2026',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);
    }

    public function test_loyalty_index_and_show_views_render_properly(): void
    {
        $this->actingAs($this->superAdmin);

        $account = CustomerLoyaltyAccount::create([
            'customer_id' => $this->customer->id,
            'status' => LoyaltyAccountStatus::Active,
        ]);

        $responseIndex = $this->get(route('admin.loyalty.index'));
        $responseIndex->assertOk();
        $responseIndex->assertSee('أرصدة نقاط ولاء العملاء');
        $responseIndex->assertSee('تسوية نقاط يدوية');

        $responseShow = $this->get(route('admin.loyalty.show', $account));
        $responseShow->assertOk();
        $responseShow->assertSee('كشف حساب نقاط الولاء');
        $responseShow->assertSee('سجل حركات نقاط الولاء');
    }

    public function test_customer_wallets_index_and_show_views_render_properly(): void
    {
        $this->actingAs($this->superAdmin);

        $account = CustomerWalletAccount::create([
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'active',
        ]);

        $responseIndex = $this->get(route('admin.customer-wallets.index'));
        $responseIndex->assertOk();
        $responseIndex->assertSee('أرصدة محافظ العملاء المالية');
        $responseIndex->assertSee('تسوية رصيد يدوي');

        $responseShow = $this->get(route('admin.customer-wallets.show', $account));
        $responseShow->assertOk();
        $responseShow->assertSee('كشف حساب محفظة العميل');
        $responseShow->assertSee('سجل حركات المحفظة والأستاذ العام');
    }

    public function test_sales_return_show_renders_wallet_refund_button_when_posted(): void
    {
        $this->actingAs($this->superAdmin);

        $order = Order::create([
            'order_number' => 'ORD-V-001',
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'subtotal' => '8000.0000',
            'total' => '8000.0000',
            'base_currency_subtotal' => '8000.0000',
            'base_currency_total' => '8000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);

        $salesReturn = SalesReturn::create([
            'return_number' => 'SR-V-001',
            'order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => SalesReturnStatus::Posted,
            'reason_code' => ReturnReasonCode::DamagedItem,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => '1.00000000',
            'subtotal' => '8000.0000',
            'total' => '8000.0000',
            'base_currency_total' => '8000.0000',
            'posted_at' => now(),
            'posted_by' => $this->superAdmin->id,
        ]);

        $response = $this->get(route('admin.sales-returns.show', $salesReturn));
        $response->assertOk();
        $response->assertSee('استرداد القيمة إلى محفظة العميل');
    }
}
