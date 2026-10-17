<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CreateDraftDriverCodSettlementAction;
use App\Actions\PostDriverCodSettlementAction;
use App\Actions\ReverseDriverCodSettlementAction;
use App\DTOs\DriverCodSettlementData;
use App\DTOs\DriverCodSettlementItemData;
use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCodSettlement;
use App\Models\JournalEntry;
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

final class DriverCodSettlementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Driver $driver;

    private Currency $currency;

    private Cashbox $cashbox;

    private Account $cashboxGl;

    private Account $codClearingAccount;

    private Account $revenueAccount;

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

        $this->currency = Currency::where('code', 'YER')->first() ?? Currency::first();

        $this->cashboxGl = Account::where('code', '1110')->firstOrFail();
        $this->codClearingAccount = Account::where('code', '1160')->firstOrFail();
        $this->revenueAccount = Account::where('code', '4100')->firstOrFail();

        $this->cashbox = Cashbox::create([
            'code' => 'CSH-MAIN',
            'name_ar' => 'صندوق المركز الرئيسي',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $this->cashboxGl->id,
            'is_active' => true,
        ]);

        $this->driver = Driver::create([
            'name' => 'مندوب التوصيل عمر',
            'driver_code' => 'DRV-100',
            'phone' => '771122334',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'عميل السبرنت 15',
            'phone' => '773344556',
            'normalized_phone' => '773344556',
        ]);

        $this->warehouse = Warehouse::firstOrCreate(['code' => 'WH-01'], [
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'is_active' => true,
        ]);

        $this->accountant = User::create([
            'name' => 'المحاسب المالي',
            'email' => 'accountant_cod@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);
    }

    private function createDeliveredOrder(float $amount): Delivery
    {
        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
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
            'status' => DeliveryStatus::Delivered,
            'assigned_at' => now()->subHours(3),
            'delivered_at' => now()->subHour(),
            'cod_amount_to_collect' => $amount,
            'cod_collected_amount' => $amount,
            'cod_currency_id' => $this->currency->id,
        ]);
    }

    public function test_full_lifecycle_draft_post_balanced_journal_and_reversal(): void
    {
        // Delivery 1: 40,000 | Delivery 2: 25,000 (Total 65,000)
        $d1 = $this->createDeliveredOrder(40000);
        $d2 = $this->createDeliveredOrder(25000);

        $createAction = app(CreateDraftDriverCodSettlementAction::class);
        $postAction = app(PostDriverCodSettlementAction::class);
        $reverseAction = app(ReverseDriverCodSettlementAction::class);

        // 1. Create Draft Settlement (Settle 30,000 on d1, 20,000 on d2 = Total 50,000)
        $data = new DriverCodSettlementData(
            driverId: $this->driver->id,
            currencyId: $this->currency->id,
            treasuryAccountType: Cashbox::class,
            treasuryAccountId: $this->cashbox->id,
            items: [
                new DriverCodSettlementItemData($d1->id, 30000),
                new DriverCodSettlementItemData($d2->id, 20000),
            ],
            notes: 'تسوية جزئية تجريبية'
        );

        $settlement = $createAction->execute($data, $this->accountant->id);

        $this->assertEquals(DriverCodSettlementStatus::Draft, $settlement->status);
        $this->assertEquals(50000, $settlement->total_settled);
        $this->assertNull($settlement->journal_entry_id);
        $this->assertNull($settlement->posted_at);

        // Draft must NOT reduce delivery outstanding or touch GL
        $this->assertEquals(40000, $d1->fresh()->outstanding_cod_amount);
        $this->assertEquals(25000, $d2->fresh()->outstanding_cod_amount);
        $this->assertEquals(0, JournalEntry::where('source_type', DriverCodSettlement::class)->count());

        // 2. Post Settlement
        $postedSettlement = $postAction->execute($settlement, $this->accountant->id);

        $this->assertEquals(DriverCodSettlementStatus::Posted, $postedSettlement->status);
        $this->assertNotNull($postedSettlement->posted_at);
        $this->assertNotNull($postedSettlement->journal_entry_id);

        // Verify Delivery Outstanding reduced
        $this->assertEquals(10000, $d1->fresh()->outstanding_cod_amount);
        $this->assertEquals(5000, $d2->fresh()->outstanding_cod_amount);

        // 3. Accounting Verification: Exactly one balanced Journal Entry
        /** @var JournalEntry $journalEntry */
        $journalEntry = $postedSettlement->journalEntry;
        $this->assertNotNull($journalEntry);
        $this->assertEquals(JournalEntryStatus::Posted, $journalEntry->status);
        $this->assertEquals(50000, (float) $journalEntry->total_debit);
        $this->assertEquals(50000, (float) $journalEntry->total_credit);

        // Check lines: Dr Cashbox GL, Cr Driver COD Clearing
        $lines = $journalEntry->lines()->orderBy('line_order')->get();
        $this->assertCount(2, $lines);

        // Line 1: Debit Cashbox
        $this->assertEquals($this->cashboxGl->id, $lines[0]->account_id);
        $this->assertEquals(50000, (float) $lines[0]->debit);
        $this->assertEquals(0, (float) $lines[0]->credit);

        // Line 2: Credit Driver COD Clearing (1150)
        $this->assertEquals($this->codClearingAccount->id, $lines[1]->account_id);
        $this->assertEquals(0, (float) $lines[1]->debit);
        $this->assertEquals(50000, (float) $lines[1]->credit);
        $this->assertEquals(Driver::class, $lines[1]->party_type);
        $this->assertEquals($this->driver->id, $lines[1]->party_id);

        // 4. Critical: Ensure NO Sales Revenue (4100) was touched
        $revenueLinesCount = $journalEntry->lines()->where('account_id', $this->revenueAccount->id)->count();
        $this->assertEquals(0, $revenueLinesCount, 'COD Settlement MUST NOT touch Sales Revenue');

        // 5. Reversal: Reverse the settlement
        $reversedSettlement = $reverseAction->execute($postedSettlement, 'إلغاء لخطأ في حساب الاستلام', $this->accountant->id);

        $this->assertEquals(DriverCodSettlementStatus::Reversed, $reversedSettlement->status);
        $this->assertNotNull($reversedSettlement->reversed_at);
        $this->assertEquals('إلغاء لخطأ في حساب الاستلام', $reversedSettlement->reversal_reason);

        // Verify Delivery Outstanding is restored
        $this->assertEquals(40000, $d1->fresh()->outstanding_cod_amount);
        $this->assertEquals(25000, $d2->fresh()->outstanding_cod_amount);

        // Verify Journal Entry was reversed
        $this->assertNotNull($journalEntry->fresh()->reversed_at);
    }
}
