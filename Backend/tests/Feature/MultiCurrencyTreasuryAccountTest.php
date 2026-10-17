<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CreateDraftDriverCodSettlementAction;
use App\Actions\PostDriverCodSettlementAction;
use App\DTOs\DriverCodSettlementData;
use App\DTOs\DriverCodSettlementItemData;
use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\TreasuryTransactionStatus;
use App\Enums\TreasuryTransactionType;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\ElectronicWalletAccount;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\TreasuryCurrencyAccount;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TreasuryBalanceService;
use App\Services\TreasuryPostingService;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

final class MultiCurrencyTreasuryAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private User $unauthorizedUser;

    private Currency $yer;

    private Currency $sar;

    private Currency $usd;

    private Account $yerGlAccount;

    private Account $sarGlAccount;

    private Account $usdGlAccount;

    private Account $revenueGlAccount;

    private TreasuryBalanceService $balanceService;

    private TreasuryPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->balanceService = app(TreasuryBalanceService::class);
        $this->postingService = app(TreasuryPostingService::class);

        $this->yer = Currency::where('code', 'YER')->firstOrFail();
        $this->sar = Currency::where('code', 'SAR')->firstOrFail();
        $this->usd = Currency::where('code', 'USD')->firstOrFail();

        ExchangeRate::create([
            'from_currency_id' => $this->sar->id,
            'to_currency_id' => $this->yer->id,
            'rate' => 66.5,
            'effective_at' => now()->subDay(),
        ]);
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->yer->id,
            'rate' => 250.0,
            'effective_at' => now()->subDay(),
        ]);

        $this->yerGlAccount = Account::where('code', '1110')->firstOrFail();

        $this->sarGlAccount = Account::create([
            'code' => '1111',
            'name_ar' => 'صناديق وبنوك بالريال السعودي',
            'name_en' => 'Treasury SAR Asset GL',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
            'parent_code' => '1100',
        ]);

        $this->usdGlAccount = Account::create([
            'code' => '1112',
            'name_ar' => 'صناديق وبنوك بالدولار الأمريكي',
            'name_en' => 'Treasury USD Asset GL',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
            'parent_code' => '1100',
        ]);

        $this->revenueGlAccount = Account::where('code', '4100')->firstOrFail();

        $this->accountant = User::create([
            'name' => 'Finance Chief Accountant',
            'email' => 'chief_accountant@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);

        $this->unauthorizedUser = User::create([
            'name' => 'Call Center Operator',
            'email' => 'operator@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->unauthorizedUser->syncRoles(['customer_support']);
    }

    /**
     * Shared-GL model: one wallet, three currencies, one parent GL.
     * Balances stay per-currency; ledger/statement still scoped by currency.
     */
    public function test_acceptance_jeeb_wallet_opened_with_yer_sar_usd_and_has_distinct_gl_and_statements(): void
    {
        $wallet = ElectronicWalletAccount::create([
            'code' => 'WLT-JEEB',
            'provider_name' => 'Jeeb',
            'account_label' => 'محفظة جيب للشركة',
            'account_identifier' => '777000111',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('treasury_currency_accounts', [
            'accountable_type' => ElectronicWalletAccount::class,
            'accountable_id' => $wallet->id,
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
        ]);

        $sarResponse = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.currency-accounts.store'), [
            'accountable_type' => ElectronicWalletAccount::class,
            'accountable_id' => $wallet->id,
            'currency_id' => $this->sar->id,
            'notes' => 'حساب جيب بالريال السعودي',
        ]);
        $sarResponse->assertRedirect();

        $usdResponse = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.currency-accounts.store'), [
            'accountable_type' => ElectronicWalletAccount::class,
            'accountable_id' => $wallet->id,
            'currency_id' => $this->usd->id,
            'notes' => 'حساب جيب بالدولار',
        ]);
        $usdResponse->assertRedirect();

        $currencyAccounts = TreasuryCurrencyAccount::where('accountable_type', ElectronicWalletAccount::class)
            ->where('accountable_id', $wallet->id)
            ->get();

        $this->assertCount(3, $currencyAccounts);
        $this->assertTrue($currencyAccounts->every(fn ($ca) => (int) $ca->gl_account_id === (int) $this->yerGlAccount->id));

        $yerAccount = $currencyAccounts->firstWhere('currency_id', $this->yer->id);
        $sarAccount = $currencyAccounts->firstWhere('currency_id', $this->sar->id);
        $usdAccount = $currencyAccounts->firstWhere('currency_id', $this->usd->id);

        $this->assertNotNull($yerAccount);
        $this->assertNotNull($sarAccount);
        $this->assertNotNull($usdAccount);

        $trx = TreasuryTransaction::create([
            'transaction_number' => 'TRX-SAR-001',
            'transaction_type' => TreasuryTransactionType::CashReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => ElectronicWalletAccount::class,
            'account_id' => $wallet->id,
            'treasury_currency_account_id' => $sarAccount->id,
            'offset_account_id' => $this->revenueGlAccount->id,
            'currency_id' => $this->sar->id,
            'exchange_rate_snapshot' => 66.5,
            'amount' => 5000,
            'base_currency_amount' => 332500,
            'description' => 'إيداع نقدي بالريال السعودي',
            'created_by' => $this->accountant->id,
        ]);

        $this->postingService->post($trx);

        $yerBalance = $this->balanceService->getAuthoritativeBalance($yerAccount);
        $sarBalance = $this->balanceService->getAuthoritativeBalance($sarAccount);
        $usdBalance = $this->balanceService->getAuthoritativeBalance($usdAccount);

        $this->assertSame('0.0000', $yerBalance);
        $this->assertSame('5000.0000', $sarBalance);
        $this->assertSame('0.0000', $usdBalance);

        $this->assertSame('332500.0000', $this->balanceService->getAuthoritativeBaseBalance($sarAccount));

        $statementResponse = $this->actingAs($this->accountant)
            ->get(route('admin.finance.treasury.currency-accounts.statement', $sarAccount));

        $statementResponse->assertOk();
        $statementResponse->assertSee('5,000.00');
        $statementResponse->assertSee('SAR');

        $yerStatementResponse = $this->actingAs($this->accountant)
            ->get(route('admin.finance.treasury.currency-accounts.statement', $yerAccount));
        $yerStatementResponse->assertOk();
        $yerStatementResponse->assertSee('0.00');
    }

    /**
     * Requirement: Database uniqueness constraint & duplication prevention
     * - (accountable_type, accountable_id, currency_id) MUST be unique at DB level.
     * - Store action must reject duplicates with 422.
     */
    public function test_database_uniqueness_constraint_prevents_duplicate_currency_account(): void
    {
        $cashbox = Cashbox::create([
            'code' => 'CSH-TEST',
            'name_ar' => 'صندوق الاختبار',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);

        // Attempting to post another YER account for this cashbox via controller
        $response = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.currency-accounts.store'), [
            'accountable_type' => Cashbox::class,
            'accountable_id' => $cashbox->id,
            'currency_id' => $this->yer->id,
        ]);

        $response->assertSessionHasErrors(['currency_id']);

        // Direct DB attempt must trigger SQL unique constraint failure
        $this->expectException(QueryException::class);
        TreasuryCurrencyAccount::create([
            'accountable_type' => Cashbox::class,
            'accountable_id' => $cashbox->id,
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);
    }

    /**
     * Requirement: Same-Currency Transfer
     * - Same currency transfer between two different entities (Cashbox SAR -> Bank Account SAR) succeeds.
     * - Balances accurately update on both currency accounts.
     */
    public function test_same_currency_transfer_between_treasury_currency_accounts_succeeds(): void
    {
        $cashbox = Cashbox::create([
            'code' => 'CSH-SRC',
            'name_ar' => 'صندوق الفرع',
            'currency_id' => $this->sar->id,
            'gl_account_id' => $this->sarGlAccount->id,
            'is_active' => true,
        ]);
        $cashboxSarAcc = $cashbox->currencyAccounts()->first();

        $bankSarGlAccount = Account::create([
            'code' => '1121',
            'name_ar' => 'حساب بنكي بالريال السعودي',
            'name_en' => 'Bank SAR Asset GL',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
            'parent_code' => '1100',
        ]);

        $bankAccount = BankAccount::create([
            'code' => 'BNK-DEST',
            'bank_name' => 'بنك التضامن',
            'account_name' => 'حساب ر.س',
            'account_number' => 'ACC-SAR-999',
            'currency_id' => $this->sar->id,
            'gl_account_id' => $bankSarGlAccount->id,
            'is_active' => true,
        ]);
        $bankSarAcc = $bankAccount->currencyAccounts()->first();

        // Initial deposit to cashbox: 10,000 SAR
        $deposit = TreasuryTransaction::create([
            'transaction_number' => 'TRX-DEP-001',
            'transaction_type' => TreasuryTransactionType::CashReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $cashbox->id,
            'treasury_currency_account_id' => $cashboxSarAcc->id,
            'offset_account_id' => $this->revenueGlAccount->id,
            'currency_id' => $this->sar->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 10000,
            'base_currency_amount' => 10000,
            'description' => 'رأس مال أولي بالريال السعودي',
            'created_by' => $this->accountant->id,
        ]);
        $this->postingService->post($deposit);

        // Perform Transfer: 3,000 SAR from Cashbox to Bank Account
        $transfer = TreasuryTransaction::create([
            'transaction_number' => 'TRX-TRF-001',
            'transaction_type' => TreasuryTransactionType::Transfer,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $cashbox->id,
            'treasury_currency_account_id' => $cashboxSarAcc->id,
            'destination_account_type' => BankAccount::class,
            'destination_account_id' => $bankAccount->id,
            'destination_currency_account_id' => $bankSarAcc->id,
            'currency_id' => $this->sar->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 3000,
            'base_currency_amount' => 3000,
            'description' => 'تحويل بين حسابين بعملة الريال السعودي',
            'created_by' => $this->accountant->id,
        ]);

        $this->postingService->post($transfer);

        $transfer->refresh();
        $this->assertEquals(TreasuryTransactionStatus::Posted, $transfer->status);
        $this->assertEquals(JournalEntryStatus::Posted, $transfer->journalEntry?->status);

        // Check balances
        $cashboxBalance = $this->balanceService->getAuthoritativeBalance($cashboxSarAcc);
        $bankBalance = $this->balanceService->getAuthoritativeBalance($bankSarAcc);

        $this->assertSame('7000.0000', $cashboxBalance);
        $this->assertSame('3000.0000', $bankBalance);
    }

    /**
     * Requirement: Cross-Currency Transfer Rejection
     * - Ordinary transfer between different currencies must be strictly REJECTED (HTTP 422).
     */
    public function test_cross_currency_ordinary_transfer_is_strictly_rejected(): void
    {
        $cashbox = Cashbox::create([
            'code' => 'CSH-SAR-SRC',
            'name_ar' => 'صندوق ر.س',
            'currency_id' => $this->sar->id,
            'gl_account_id' => $this->sarGlAccount->id,
            'is_active' => true,
        ]);
        $cashboxSarAcc = $cashbox->currencyAccounts()->first();

        $bankAccount = BankAccount::create([
            'code' => 'BNK-YER-DEST',
            'bank_name' => 'بنك الكريمي',
            'account_name' => 'حساب يمني',
            'account_number' => 'ACC-YER-111',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);
        $bankYerAcc = $bankAccount->currencyAccounts()->first();

        // Fund the cashbox with 10,000 SAR
        $deposit = TreasuryTransaction::create([
            'transaction_number' => 'TRX-DEP-CROSS',
            'transaction_type' => TreasuryTransactionType::CashReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $cashbox->id,
            'treasury_currency_account_id' => $cashboxSarAcc->id,
            'offset_account_id' => $this->revenueGlAccount->id,
            'currency_id' => $this->sar->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 10000,
            'base_currency_amount' => 10000,
            'description' => 'إيداع',
            'created_by' => $this->accountant->id,
        ]);
        $this->postingService->post($deposit);

        // Attempt Cross-Currency Transfer (SAR -> YER)
        $crossTransfer = TreasuryTransaction::create([
            'transaction_number' => 'TRX-TRF-CROSS',
            'transaction_type' => TreasuryTransactionType::Transfer,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $cashbox->id,
            'treasury_currency_account_id' => $cashboxSarAcc->id,
            'destination_account_type' => BankAccount::class,
            'destination_account_id' => $bankAccount->id,
            'destination_currency_account_id' => $bankYerAcc->id,
            'currency_id' => $this->sar->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 1000,
            'base_currency_amount' => 1000,
            'description' => 'محاولة تحويل بين عملتين مختلفتين',
            'created_by' => $this->accountant->id,
        ]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->postingService->post($crossTransfer);
    }

    /**
     * Requirement: Driver COD Settlement Currency Matching
     * - Driver COD in SAR settled to matching SAR treasury account succeeds.
     * - Driver COD in SAR settled to treasury without SAR account fails.
     */
    public function test_driver_cod_settlement_requires_matching_treasury_currency_account(): void
    {
        $driverUser = User::create([
            'name' => 'Driver Express',
            'email' => 'driver_cod@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'name' => 'Driver Express',
            'code' => 'DRV-COD-001',
            'phone' => '771122334',
            'normalized_phone' => '771122334',
            'license_number' => 'DL-9900',
            'vehicle_type' => 'Motorcycle',
            'is_active' => true,
        ]);

        $cashbox = Cashbox::create([
            'code' => 'CSH-COD',
            'name_ar' => 'صندوق تحصيل المناديب',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'عميل تجريبي',
            'phone' => '773344556',
            'normalized_phone' => '773344556',
        ]);

        $warehouse = Warehouse::firstOrCreate(['code' => 'WH-01'], [
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'is_active' => true,
        ]);

        // Create delivered order in SAR with COD collected 500 SAR
        $order = Order::create([
            'order_number' => 'ORD-COD-SAR-1',
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'warehouse_id' => $warehouse->id,
            'currency_id' => $this->sar->id,
            'status' => 'delivered',
            'subtotal' => 500,
            'discount_total' => 0,
            'tax_total' => 0,
            'delivery_fee' => 0,
            'total' => 500,
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-COD-SAR-1',
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => DeliveryStatus::Delivered,
            'assigned_at' => now()->subHours(2),
            'delivered_at' => now()->subHour(),
            'cod_amount_to_collect' => 500,
            'cod_collected_amount' => 500,
            'cod_currency_id' => $this->sar->id,
        ]);

        $createAction = app(CreateDraftDriverCodSettlementAction::class);
        $postAction = app(PostDriverCodSettlementAction::class);

        // 1. Attempt settlement when cashbox has NO SAR account -> Fails at draft creation
        $settlementData = new DriverCodSettlementData(
            driverId: $driver->id,
            currencyId: $this->sar->id,
            treasuryAccountType: Cashbox::class,
            treasuryAccountId: $cashbox->id,
            items: [
                new DriverCodSettlementItemData($delivery->id, 500),
            ],
            notes: 'تسوية 500 ريال سعودي'
        );

        try {
            $createAction->execute($settlementData, $this->accountant->id);
            $this->fail('Expected DomainException because cashbox does not have a SAR account');
        } catch (DomainException $e) {
            $this->assertStringContainsString('عملة حساب الخزينة المحدد لا تطابق عملة التسوية', $e->getMessage());
        }

        // 2. Open SAR account for the Cashbox with unique GL account
        $sarCodGl = Account::create([
            'code' => '1119',
            'name_ar' => 'صندوق تحصيل ر.س',
            'name_en' => 'Cashbox SAR COD GL',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
            'parent_code' => '1100',
        ]);

        $sarAcc = TreasuryCurrencyAccount::create([
            'accountable_type' => Cashbox::class,
            'accountable_id' => $cashbox->id,
            'currency_id' => $this->sar->id,
            'gl_account_id' => $sarCodGl->id,
            'is_active' => true,
        ]);

        // 3. Create draft settlement now that SAR account exists -> Must succeed
        $settlement = $createAction->execute($settlementData, $this->accountant->id);
        $this->assertEquals(DriverCodSettlementStatus::Draft, $settlement->status);
        $this->assertEquals($sarAcc->id, $settlement->treasury_currency_account_id);

        // 4. Post settlement -> Must succeed and update SAR account balance
        $postedSettlement = $postAction->execute($settlement, $this->accountant->id);

        $this->assertEquals(DriverCodSettlementStatus::Posted, $postedSettlement->status);
        $this->assertEquals($sarAcc->id, $postedSettlement->treasury_currency_account_id);

        $balance = $this->balanceService->getAuthoritativeBalance($sarAcc);
        $this->assertSame('500.0000', $balance);
    }

    /**
     * Requirement: Activation / Deactivation of Currency Account
     */
    public function test_toggling_currency_account_status(): void
    {
        $bankAccount = BankAccount::create([
            'code' => 'BNK-TOGGLE',
            'bank_name' => 'بنك سبأ',
            'account_name' => 'حساب سبأ',
            'account_number' => 'ACC-TOGGLE-1',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);

        $currencyAccount = $bankAccount->currencyAccounts()->first();
        $this->assertTrue($currencyAccount->is_active);

        // Deactivate via Web route
        $response = $this->actingAs($this->accountant)
            ->patch(route('admin.finance.treasury.currency-accounts.toggle', $currencyAccount));

        $response->assertRedirect();
        $this->assertFalse($currencyAccount->fresh()->is_active);

        // Reactivate
        $response2 = $this->actingAs($this->accountant)
            ->patch(route('admin.finance.treasury.currency-accounts.toggle', $currencyAccount));

        $response2->assertRedirect();
        $this->assertTrue($currencyAccount->fresh()->is_active);
    }

    /**
     * Requirement: Authorization & IDOR protection
     */
    public function test_unauthorized_user_cannot_manage_currency_accounts(): void
    {
        $cashbox = Cashbox::create([
            'code' => 'CSH-SEC',
            'name_ar' => 'صندوق سري',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->unauthorizedUser)->post(route('admin.finance.treasury.currency-accounts.store'), [
            'accountable_type' => Cashbox::class,
            'accountable_id' => $cashbox->id,
            'currency_id' => $this->sar->id,
        ]);

        $response->assertForbidden();
    }

    public function test_create_cashbox_bank_and_wallet_with_two_currencies_share_parent_gl(): void
    {
        $this->actingAs($this->accountant);

        $cashboxGl = Account::create([
            'code' => '1191',
            'name_ar' => 'أصل صندوق مشترك',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
        ]);
        $bankGl = Account::create([
            'code' => '1192',
            'name_ar' => 'أصل بنك مشترك',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
        ]);
        $walletGl = Account::create([
            'code' => '1193',
            'name_ar' => 'أصل محفظة مشترك',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
        ]);

        $cashboxRes = $this->post(route('admin.finance.treasury.cashboxes.store'), [
            'code' => 'CSH-MULTI-GL',
            'name_ar' => 'صندوق عملتين',
            'currency_ids' => [$this->yer->id, $this->sar->id],
            'gl_account_id' => $cashboxGl->id,
            'is_active' => 1,
        ]);
        $cashboxRes->assertRedirect();

        $bankRes = $this->post(route('admin.finance.treasury.bank-accounts.store'), [
            'code' => 'BNK-MULTI-GL',
            'bank_name' => 'بنك',
            'account_name' => 'حساب',
            'account_number' => 'ACC-MULTI-GL',
            'currency_ids' => [$this->yer->id, $this->usd->id],
            'gl_account_id' => $bankGl->id,
            'is_active' => 1,
        ]);
        $bankRes->assertRedirect();

        $walletRes = $this->post(route('admin.finance.treasury.electronic-wallets.store'), [
            'code' => 'WLT-MULTI-GL',
            'provider_name' => 'مزود',
            'account_label' => 'محفظة',
            'account_identifier' => 'ID-MULTI-GL',
            'currency_ids' => [$this->sar->id, $this->usd->id],
            'gl_account_id' => $walletGl->id,
            'is_active' => 1,
        ]);
        $walletRes->assertRedirect();

        $cashbox = Cashbox::where('code', 'CSH-MULTI-GL')->firstOrFail();
        $bank = BankAccount::where('code', 'BNK-MULTI-GL')->firstOrFail();
        $wallet = ElectronicWalletAccount::where('code', 'WLT-MULTI-GL')->firstOrFail();

        $this->assertCount(2, $cashbox->currencyAccounts);
        $this->assertTrue($cashbox->currencyAccounts->every(fn ($ca) => (int) $ca->gl_account_id === (int) $cashboxGl->id));
        $this->assertTrue($bank->currencyAccounts->every(fn ($ca) => (int) $ca->gl_account_id === (int) $bankGl->id));
        $this->assertTrue($wallet->currencyAccounts->every(fn ($ca) => (int) $ca->gl_account_id === (int) $walletGl->id));
    }

    public function test_draft_treasury_transaction_binds_currency_account_and_rejects_unopened_currency(): void
    {
        $cashbox = Cashbox::create([
            'code' => 'CSH-BIND',
            'name_ar' => 'صندوق ربط',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->accountant)->post(route('admin.finance.treasury.currency-accounts.store'), [
            'accountable_type' => Cashbox::class,
            'accountable_id' => $cashbox->id,
            'currency_id' => $this->sar->id,
        ])->assertRedirect();

        $sarAcc = $cashbox->currencyAccounts()->where('currency_id', $this->sar->id)->firstOrFail();

        $ok = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.transactions.store'), [
            'transaction_type' => TreasuryTransactionType::CashReceipt->value,
            'account_type' => Cashbox::class,
            'account_id' => $cashbox->id,
            'currency_id' => $this->sar->id,
            'amount' => 100,
            'description' => 'قبض SAR',
            'offset_account_id' => $this->revenueGlAccount->id,
            'exchange_rate_snapshot' => 66.5,
        ]);
        $ok->assertRedirect();

        $trx = TreasuryTransaction::where('account_id', $cashbox->id)->where('currency_id', $this->sar->id)->firstOrFail();
        $this->assertSame($sarAcc->id, $trx->treasury_currency_account_id);
        $this->assertSame('66.50000000', $trx->exchange_rate_snapshot);
        $this->assertSame('6650.0000', $trx->base_currency_amount);

        $this->expectException(UnprocessableEntityHttpException::class);
        app(\App\Actions\CreateDraftTreasuryTransactionAction::class)->execute(
            new \App\DTOs\TreasuryTransactionData(
                transactionType: TreasuryTransactionType::CashReceipt,
                accountType: Cashbox::class,
                accountId: $cashbox->id,
                currencyId: $this->usd->id,
                amount: 10,
                description: 'عملة غير مفتوحة',
                offsetAccountId: $this->revenueGlAccount->id,
            )
        );
    }

    public function test_create_transaction_form_exposes_opened_currency_options_per_account(): void
    {
        $cashbox = Cashbox::create([
            'code' => 'CSH-UI-CUR',
            'name_ar' => 'صندوق واجهة',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->accountant)->post(route('admin.finance.treasury.currency-accounts.store'), [
            'accountable_type' => Cashbox::class,
            'accountable_id' => $cashbox->id,
            'currency_id' => $this->sar->id,
        ])->assertRedirect();

        $response = $this->actingAs($this->accountant)
            ->get(route('admin.finance.treasury.transactions.create'));

        $response->assertOk();
        $response->assertSee('data-currency-options', false);
        $response->assertSee((string) $this->yer->id, false);
        $response->assertSee((string) $this->sar->id, false);
        $response->assertSee('data-account-type', false);
        $response->assertSee('اختر حساب الخزينة أولاً', false);
    }

    public function test_update_draft_rebinds_treasury_currency_account_and_rejects_unopened_currency(): void
    {
        $cashbox = Cashbox::create([
            'code' => 'CSH-UPD-CUR',
            'name_ar' => 'صندوق تحديث',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $this->yerGlAccount->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->accountant)->post(route('admin.finance.treasury.currency-accounts.store'), [
            'accountable_type' => Cashbox::class,
            'accountable_id' => $cashbox->id,
            'currency_id' => $this->sar->id,
        ])->assertRedirect();

        $yerAcc = $cashbox->currencyAccounts()->where('currency_id', $this->yer->id)->firstOrFail();
        $sarAcc = $cashbox->currencyAccounts()->where('currency_id', $this->sar->id)->firstOrFail();

        $trx = TreasuryTransaction::create([
            'transaction_number' => 'TRX-UPD-CUR',
            'transaction_type' => TreasuryTransactionType::CashReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $cashbox->id,
            'treasury_currency_account_id' => $yerAcc->id,
            'offset_account_id' => $this->revenueGlAccount->id,
            'currency_id' => $this->yer->id,
            'exchange_rate_snapshot' => 1,
            'amount' => 50,
            'base_currency_amount' => 50,
            'description' => 'مسودة أولية',
            'created_by' => $this->accountant->id,
        ]);

        $this->actingAs($this->accountant)->put(route('admin.finance.treasury.transactions.update', $trx), [
            'transaction_type' => TreasuryTransactionType::CashReceipt->value,
            'account_type' => Cashbox::class,
            'account_id' => $cashbox->id,
            'currency_id' => $this->sar->id,
            'amount' => 80,
            'description' => 'مسودة بعد تغيير العملة',
            'offset_account_id' => $this->revenueGlAccount->id,
            'exchange_rate_snapshot' => 66.5,
        ])->assertRedirect();

        $trx->refresh();
        $this->assertSame($sarAcc->id, $trx->treasury_currency_account_id);
        $this->assertSame($this->sar->id, $trx->currency_id);
        $this->assertSame('5320.0000', $trx->base_currency_amount);

        $this->expectException(UnprocessableEntityHttpException::class);
        app(\App\Actions\UpdateDraftTreasuryTransactionAction::class)->execute(
            $trx,
            new \App\DTOs\TreasuryTransactionData(
                transactionType: TreasuryTransactionType::CashReceipt,
                accountType: Cashbox::class,
                accountId: $cashbox->id,
                currencyId: $this->usd->id,
                amount: 10,
                description: 'عملة غير مفتوحة',
                offsetAccountId: $this->revenueGlAccount->id,
            )
        );
    }

    public function test_two_wallets_sharing_one_gl_keep_separate_currency_balances_and_gl_sums(): void
    {
        $sharedGl = Account::create([
            'code' => '1188',
            'name_ar' => 'حسابات المحافظ المشتركة',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
        ]);

        $walletA = ElectronicWalletAccount::create([
            'code' => 'WLT-SHARE-A',
            'provider_name' => 'Jaib',
            'account_label' => 'محفظة أ',
            'account_identifier' => '777111',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $sharedGl->id,
            'is_active' => true,
        ]);
        $walletB = ElectronicWalletAccount::create([
            'code' => 'WLT-SHARE-B',
            'provider_name' => 'Floosak',
            'account_label' => 'محفظة ب',
            'account_identifier' => '777222',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $sharedGl->id,
            'is_active' => true,
        ]);

        $tcaA = $walletA->currencyAccounts()->firstOrFail();
        $tcaB = $walletB->currencyAccounts()->firstOrFail();
        $this->assertSame($sharedGl->id, $tcaA->gl_account_id);
        $this->assertSame($sharedGl->id, $tcaB->gl_account_id);

        $trxA = TreasuryTransaction::create([
            'transaction_number' => 'TRX-SHARE-A',
            'transaction_type' => TreasuryTransactionType::WalletReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => ElectronicWalletAccount::class,
            'account_id' => $walletA->id,
            'treasury_currency_account_id' => $tcaA->id,
            'offset_account_id' => $this->revenueGlAccount->id,
            'currency_id' => $this->yer->id,
            'exchange_rate_snapshot' => 1,
            'amount' => 1000,
            'base_currency_amount' => 1000,
            'description' => 'قبض محفظة أ',
            'created_by' => $this->accountant->id,
        ]);
        $trxB = TreasuryTransaction::create([
            'transaction_number' => 'TRX-SHARE-B',
            'transaction_type' => TreasuryTransactionType::WalletReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => ElectronicWalletAccount::class,
            'account_id' => $walletB->id,
            'treasury_currency_account_id' => $tcaB->id,
            'offset_account_id' => $this->revenueGlAccount->id,
            'currency_id' => $this->yer->id,
            'exchange_rate_snapshot' => 1,
            'amount' => 250,
            'base_currency_amount' => 250,
            'description' => 'قبض محفظة ب',
            'created_by' => $this->accountant->id,
        ]);

        $this->postingService->post($trxA);
        $this->postingService->post($trxB);

        $this->assertSame('1000.0000', $this->balanceService->getAuthoritativeBalance($tcaA));
        $this->assertSame('250.0000', $this->balanceService->getAuthoritativeBalance($tcaB));
        $this->assertSame('1000.0000', $this->balanceService->getAuthoritativeBaseBalance($tcaA));
        $this->assertSame('250.0000', $this->balanceService->getAuthoritativeBaseBalance($tcaB));

        $this->assertDatabaseHas('journal_entry_lines', [
            'treasury_currency_account_id' => $tcaA->id,
            'account_id' => $sharedGl->id,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'treasury_currency_account_id' => $tcaB->id,
            'account_id' => $sharedGl->id,
        ]);

        $glTotals = \Illuminate\Support\Facades\DB::table('journal_entry_lines')
            ->where('account_id', $sharedGl->id)
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();
        $glNet = bcsub((string) $glTotals->d, (string) $glTotals->c, 4);
        $this->assertSame('1250.0000', $glNet);
    }
}
