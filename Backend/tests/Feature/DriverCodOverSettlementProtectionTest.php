<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CreateDraftDriverCodSettlementAction;
use App\Actions\PostDriverCodSettlementAction;
use App\Actions\ReverseDriverCodSettlementAction;
use App\DTOs\DriverCodSettlementData;
use App\DTOs\DriverCodSettlementItemData;
use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Enums\DeliveryStatus;
use App\Models\Account;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DriverCodOverSettlementProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Driver $driver;

    private Currency $currencyYer;

    private Currency $currencySar;

    private Cashbox $cashboxYer;

    private Cashbox $cashboxSar;

    private Customer $customer;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        $this->currencyYer = Currency::where('code', 'YER')->first() ?? Currency::first();
        $this->currencySar = Currency::where('code', 'SAR')->first() ?? Currency::create([
            'code' => 'SAR',
            'name' => 'ريال سعودي',
            'symbol' => 'ر.س',
            'is_default' => false,
            'is_active' => true,
        ]);

        $cashboxGl = Account::where('code', '1110')->firstOrFail();
        $cashboxSarGl = Account::create([
            'code' => '1112',
            'name_ar' => 'صندوق الريال السعودي',
            'name_en' => 'SAR Cashbox GL',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->cashboxYer = Cashbox::create([
            'code' => 'CSH-YER',
            'name_ar' => 'صندوق يمني',
            'currency_id' => $this->currencyYer->id,
            'gl_account_id' => $cashboxGl->id,
            'is_active' => true,
        ]);

        $this->cashboxSar = Cashbox::create([
            'code' => 'CSH-SAR',
            'name_ar' => 'صندوق سعودي',
            'currency_id' => $this->currencySar->id,
            'gl_account_id' => $cashboxSarGl->id,
            'is_active' => true,
        ]);

        $this->driver = Driver::create([
            'name' => 'مندوب التوصيل سامي',
            'driver_code' => 'DRV-200',
            'phone' => '779988776',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'عميل فحص الحماية',
            'phone' => '771234567',
            'normalized_phone' => '771234567',
        ]);

        $this->warehouse = Warehouse::firstOrCreate(['code' => 'WH-01'], [
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'is_active' => true,
        ]);

        $this->accountant = User::create([
            'name' => 'محاسب تدقيق الحماية',
            'email' => 'auditor@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);
    }

    private function createDeliveredOrder(float $amount, ?Currency $currency = null, DeliveryStatus $status = DeliveryStatus::Delivered): Delivery
    {
        $curr = $currency ?? $this->currencyYer;

        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $curr->id,
            'status' => 'delivered',
            'subtotal' => $amount,
            'discount_total' => 0,
            'tax_total' => 0,
            'delivery_fee' => 0,
            'total' => $amount,
        ]);

        return Delivery::create([
            'delivery_number' => 'DEL-'.uniqid(),
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'status' => $status,
            'assigned_at' => now()->subHours(2),
            'delivered_at' => $status === DeliveryStatus::Delivered ? now()->subHour() : null,
            'cod_amount_to_collect' => $amount,
            'cod_collected_amount' => $status === DeliveryStatus::Delivered ? $amount : null,
            'cod_currency_id' => $curr->id,
        ]);
    }

    public function test_cannot_settle_more_than_delivery_outstanding(): void
    {
        $delivery = $this->createDeliveredOrder(20000);
        $action = app(CreateDraftDriverCodSettlementAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('يتجاوز الرصيد المستحق المتبقي عليها');

        $action->execute(new DriverCodSettlementData(
            driverId: $this->driver->id,
            currencyId: $this->currencyYer->id,
            treasuryAccountType: Cashbox::class,
            treasuryAccountId: $this->cashboxYer->id,
            items: [
                new DriverCodSettlementItemData($delivery->id, 25000), // Exceeds 20,000!
            ]
        ), $this->accountant->id);
    }

    public function test_cannot_settle_non_delivered_shipment(): void
    {
        $delivery = $this->createDeliveredOrder(20000, null, DeliveryStatus::Assigned);
        $action = app(CreateDraftDriverCodSettlementAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ليست بحالة تسليم ناجح');

        $action->execute(new DriverCodSettlementData(
            driverId: $this->driver->id,
            currencyId: $this->currencyYer->id,
            treasuryAccountType: Cashbox::class,
            treasuryAccountId: $this->cashboxYer->id,
            items: [
                new DriverCodSettlementItemData($delivery->id, 20000),
            ]
        ), $this->accountant->id);
    }

    public function test_cannot_settle_with_currency_mismatch_against_treasury(): void
    {
        $delivery = $this->createDeliveredOrder(20000, $this->currencyYer);
        $action = app(CreateDraftDriverCodSettlementAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('عملة حساب الخزينة المحدد لا تطابق عملة التسوية');

        // Trying to settle YER COD delivery into SAR cashbox
        $action->execute(new DriverCodSettlementData(
            driverId: $this->driver->id,
            currencyId: $this->currencyYer->id,
            treasuryAccountType: Cashbox::class,
            treasuryAccountId: $this->cashboxSar->id, // SAR Cashbox!
            items: [
                new DriverCodSettlementItemData($delivery->id, 20000),
            ]
        ), $this->accountant->id);
    }

    public function test_double_posting_is_prevented_or_idempotent(): void
    {
        $delivery = $this->createDeliveredOrder(15000);
        $createAction = app(CreateDraftDriverCodSettlementAction::class);
        $postAction = app(PostDriverCodSettlementAction::class);

        $settlement = $createAction->execute(new DriverCodSettlementData(
            driverId: $this->driver->id,
            currencyId: $this->currencyYer->id,
            treasuryAccountType: Cashbox::class,
            treasuryAccountId: $this->cashboxYer->id,
            items: [
                new DriverCodSettlementItemData($delivery->id, 15000),
            ]
        ), $this->accountant->id);

        $posted1 = $postAction->execute($settlement, $this->accountant->id);
        $posted2 = $postAction->execute($posted1, $this->accountant->id);

        $this->assertEquals($posted1->id, $posted2->id);
        $this->assertEquals($posted1->journal_entry_id, $posted2->journal_entry_id);
    }

    public function test_double_reversal_is_rejected(): void
    {
        $delivery = $this->createDeliveredOrder(15000);
        $createAction = app(CreateDraftDriverCodSettlementAction::class);
        $postAction = app(PostDriverCodSettlementAction::class);
        $reverseAction = app(ReverseDriverCodSettlementAction::class);

        $settlement = $createAction->execute(new DriverCodSettlementData(
            driverId: $this->driver->id,
            currencyId: $this->currencyYer->id,
            treasuryAccountType: Cashbox::class,
            treasuryAccountId: $this->cashboxYer->id,
            items: [
                new DriverCodSettlementItemData($delivery->id, 15000),
            ]
        ), $this->accountant->id);

        $posted = $postAction->execute($settlement, $this->accountant->id);
        $reversed = $reverseAction->execute($posted, 'سبب أول', $this->accountant->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('التسوية معكوسة/ملغاة مسبقاً');

        $reverseAction->execute($reversed, 'محاولة عكس ثانية', $this->accountant->id);
    }
}
