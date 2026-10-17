<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\ReturnReasonCode;
use App\Enums\SalesReturnStatus;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerWalletAccount;
use App\Models\CustomerWalletEntry;
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

final class AdminLoyaltyAndWalletWebTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $unauthorizedUser;

    private Customer $customer;

    private Currency $currency;

    private AccountingPeriod $openPeriod;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(LoyaltyAndWalletPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin_web_test@lamma.test',
            'password' => bcrypt('password123'),
        ]);
        $this->superAdmin->syncRoles(['super_admin']);

        $this->unauthorizedUser = User::create([
            'name' => 'Unauthorized User',
            'email' => 'unauth@lamma.test',
            'password' => bcrypt('password123'),
        ]);

        $this->customer = Customer::create([
            'name' => 'متجر السعادة',
            'phone' => '779988776',
            'normalized_phone' => '779988776',
            'is_active' => true,
        ]);

        $this->currency = Currency::where('is_default', true)->firstOrFail();

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع عدن',
            'name_en' => 'Aden Warehouse',
            'code' => 'WH-ADEN',
            'is_active' => true,
        ]);

        $this->openPeriod = AccountingPeriod::create([
            'name' => 'فترة سبتمبر 2026',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);
    }

    public function test_unauthorized_user_cannot_access_loyalty_or_wallet_endpoints(): void
    {
        $this->actingAs($this->unauthorizedUser);

        $this->get(route('admin.loyalty.index'))->assertForbidden();
        $this->get(route('admin.customer-wallets.index'))->assertForbidden();

        $this->post(route('admin.loyalty.adjust'), [
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'points' => 50,
            'reason' => 'غير مصرح',
        ])->assertForbidden();

        $this->post(route('admin.customer-wallets.adjust'), [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'type' => 'credit',
            'amount' => 500,
            'reason' => 'غير مصرح',
        ])->assertForbidden();
    }

    public function test_admin_can_view_loyalty_and_wallet_indexes(): void
    {
        $this->actingAs($this->superAdmin);

        $loyaltyAccount = CustomerLoyaltyAccount::firstOrCreate(
            ['customer_id' => $this->customer->id],
            ['status' => 'active']
        );

        $walletAccount = CustomerWalletAccount::firstOrCreate(
            ['customer_id' => $this->customer->id, 'currency_id' => $this->currency->id],
            ['status' => 'active']
        );

        $this->get(route('admin.loyalty.index'))->assertOk();
        $this->get(route('admin.loyalty.show', $loyaltyAccount))->assertOk();

        $this->get(route('admin.customer-wallets.index'))->assertOk();
        $this->get(route('admin.customer-wallets.show', $walletAccount))->assertOk();
    }

    public function test_admin_can_adjust_loyalty_points(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->post(route('admin.loyalty.adjust'), [
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'points' => 150,
            'reason' => 'بونص ترحيبي معتمد',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('customer_loyalty_entries', [
            'points' => 150,
            'type' => 'adjustment_credit',
            'description' => 'بونص ترحيبي معتمد',
        ]);
    }

    public function test_admin_can_adjust_customer_wallet_balance(): void
    {
        $this->actingAs($this->superAdmin);

        // 1. Credit 5,000
        $response = $this->post(route('admin.customer-wallets.adjust'), [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'type' => 'credit',
            'amount' => 5000,
            'reason' => 'إيداع تسوية تجريبي',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('customer_wallet_entries', [
            'type' => 'manual_credit',
            'amount' => '5000.0000',
            'description' => 'إيداع تسوية تجريبي',
        ]);

        // 2. Debit 2,000
        $response2 = $this->post(route('admin.customer-wallets.adjust'), [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'type' => 'debit',
            'amount' => 2000,
            'reason' => 'خصم تسوية تجريبي',
        ]);

        $response2->assertSessionHasNoErrors();
        $response2->assertRedirect();

        $this->assertDatabaseHas('customer_wallet_entries', [
            'type' => 'manual_debit',
            'amount' => '2000.0000',
            'description' => 'خصم تسوية تجريبي',
        ]);
    }

    public function test_admin_can_refund_posted_sales_return_to_wallet(): void
    {
        $this->actingAs($this->superAdmin);

        $order = Order::create([
            'order_number' => 'ORD-WEB-001',
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'subtotal' => '12000.0000',
            'total' => '12000.0000',
            'base_currency_subtotal' => '12000.0000',
            'base_currency_total' => '12000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);

        $salesReturn = SalesReturn::create([
            'return_number' => 'SR-WEB-001',
            'order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => SalesReturnStatus::Posted,
            'reason_code' => ReturnReasonCode::DamagedItem,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => '1.00000000',
            'subtotal' => '12000.0000',
            'total' => '12000.0000',
            'base_currency_total' => '12000.0000',
            'posted_at' => now(),
            'posted_by' => $this->superAdmin->id,
        ]);

        $response = $this->post(route('admin.customer-wallets.refund'), [
            'sales_return_id' => $salesReturn->id,
            'reason' => 'استرداد قيمة المرتجع من لوحة الإدارة',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('customer_wallet_entries', [
            'type' => 'refund_credit',
            'source_type' => 'SalesReturn',
            'source_id' => $salesReturn->id,
            'amount' => '12000.0000',
        ]);
    }

    public function test_admin_can_reverse_wallet_entry(): void
    {
        $this->actingAs($this->superAdmin);

        $this->post(route('admin.customer-wallets.adjust'), [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'type' => 'credit',
            'amount' => 4000,
            'reason' => 'إيداع مراد عكسه',
        ]);

        $entry = CustomerWalletEntry::where('description', 'إيداع مراد عكسه')->firstOrFail();

        $response = $this->post(route('admin.customer-wallets.reverse', $entry), [
            'reason' => 'تم العكس لخطأ في القيمة',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('customer_wallet_entries', [
            'type' => 'reversal',
            'reversal_of_id' => $entry->id,
            'amount' => '4000.0000',
        ]);
    }
}
