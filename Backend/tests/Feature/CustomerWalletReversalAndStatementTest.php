<?php

namespace Tests\Feature;

use App\Actions\CreditCustomerWalletAction;
use App\Actions\DebitCustomerWalletAction;
use App\Actions\ReverseCustomerWalletEntryAction;
use App\DTOs\CustomerWalletAdjustmentData;
use App\Enums\AccountingPeriodStatus;
use App\Enums\CustomerWalletEntryType;
use App\Enums\JournalEntryStatus;
use App\Enums\LoyaltyAccountStatus;
use App\Enums\LoyaltyEntryType;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Models\CustomerWalletAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\CustomerWalletBalanceQuery;
use App\Services\CustomerWalletStatementQuery;
use App\Services\LoyaltyStatementQuery;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerWalletReversalAndStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Customer $customer;

    private Currency $currency;

    private AccountingPeriod $openPeriod;

    private CustomerWalletBalanceQuery $balanceQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        $this->accountant = User::create([
            'name' => 'المحاسب المعتمد',
            'email' => 'accountant_rev@lamma.test',
            'password' => bcrypt('password123'),
        ]);

        $this->customer = Customer::create([
            'name' => 'شركة الأمل',
            'phone' => '771234567',
            'normalized_phone' => '771234567',
            'is_active' => true,
        ]);

        $this->currency = Currency::where('is_default', true)->firstOrFail();

        $this->openPeriod = AccountingPeriod::create([
            'name' => 'فترة سبتمبر 2026',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);

        $this->balanceQuery = new CustomerWalletBalanceQuery;
    }

    public function test_reversal_of_credit_entry_deducts_balance_and_creates_reversal_entry_and_reverses_journal(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $reverseAction = app(ReverseCustomerWalletEntryAction::class);

        $creditEntry = $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '10000.0000',
            type: 'credit',
            reason: 'إيداع تسوية خاطئ',
            createdBy: $this->accountant->id,
        ));

        $walletAccount = CustomerWalletAccount::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame('10000.0000', $this->balanceQuery->getBalance($walletAccount));

        $reversalEntry = $reverseAction->execute(
            entry: $creditEntry,
            reason: 'تصحيح قيد خاطئ بناء على طلب الإدارة',
            userId: $this->accountant->id
        );

        self::assertSame(CustomerWalletEntryType::Reversal, $reversalEntry->type);
        self::assertSame($creditEntry->id, $reversalEntry->reversal_of_id);
        self::assertSame('10000.0000', $reversalEntry->amount);

        // Check wallet balance back to 0
        self::assertSame('0.0000', $this->balanceQuery->getBalance($walletAccount));

        // Check original GL journal was reversed
        $originalJournal = $creditEntry->journalEntry;
        self::assertNotNull($originalJournal);
        $reversalJournal = JournalEntry::where('reversal_of_id', $originalJournal->id)->first();
        self::assertNotNull($reversalJournal);
        self::assertSame(JournalEntryStatus::Posted, $reversalJournal->status);
    }

    public function test_reversal_of_credit_fails_if_insufficient_wallet_balance(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $debitAction = app(DebitCustomerWalletAction::class);
        $reverseAction = app(ReverseCustomerWalletEntryAction::class);

        $creditEntry = $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '10000.0000',
            type: 'credit',
            reason: 'إيداع',
            createdBy: $this->accountant->id,
        ));

        $debitAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '7000.0000',
            type: 'debit',
            reason: 'سحب جزئي',
            createdBy: $this->accountant->id,
        ));

        // Available balance is 3,000, reversing 10,000 would cause negative balance
        $this->expectException(DomainException::class);

        $reverseAction->execute(
            entry: $creditEntry,
            reason: 'محاولة عكس تتجاوز الرصيد',
            userId: $this->accountant->id
        );
    }

    public function test_reversal_of_debit_entry_restores_balance(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $debitAction = app(DebitCustomerWalletAction::class);
        $reverseAction = app(ReverseCustomerWalletEntryAction::class);

        $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '10000.0000',
            type: 'credit',
            reason: 'رصيد',
            createdBy: $this->accountant->id,
        ));

        $debitEntry = $debitAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '4000.0000',
            type: 'debit',
            reason: 'خصم خاطئ',
            createdBy: $this->accountant->id,
        ));

        $walletAccount = CustomerWalletAccount::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame('6000.0000', $this->balanceQuery->getBalance($walletAccount));

        $reverseAction->execute(
            entry: $debitEntry,
            reason: 'إلغاء الخصم الخاطئ',
            userId: $this->accountant->id
        );

        self::assertSame('10000.0000', $this->balanceQuery->getBalance($walletAccount));
    }

    public function test_duplicate_reversal_is_rejected(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $reverseAction = app(ReverseCustomerWalletEntryAction::class);

        $creditEntry = $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '5000.0000',
            type: 'credit',
            reason: 'إيداع تجريبي',
            createdBy: $this->accountant->id,
        ));

        $reverseAction->execute(
            entry: $creditEntry,
            reason: 'عكس أول',
            userId: $this->accountant->id
        );

        $this->expectException(DomainException::class);

        $reverseAction->execute(
            entry: $creditEntry,
            reason: 'عكس ثانٍ غير مسموح',
            userId: $this->accountant->id
        );
    }

    public function test_customer_wallet_statement_calculates_running_balance_correctly(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $debitAction = app(DebitCustomerWalletAction::class);
        $statementQuery = app(CustomerWalletStatementQuery::class);

        $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '5000.0000',
            type: 'credit',
            reason: 'دفعة 1',
            createdBy: $this->accountant->id,
        ));

        $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '3000.0000',
            type: 'credit',
            reason: 'دفعة 2',
            createdBy: $this->accountant->id,
        ));

        $debitAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '2000.0000',
            type: 'debit',
            reason: 'خصم 1',
            createdBy: $this->accountant->id,
        ));

        $walletAccount = CustomerWalletAccount::where('customer_id', $this->customer->id)->firstOrFail();
        $statement = $statementQuery->getStatement($walletAccount);

        self::assertCount(3, $statement['entries']);
        self::assertSame('6000.0000', $statement['closing_balance']);
        self::assertSame('5000.0000', $statement['entries'][0]['running_balance']);
        self::assertSame('8000.0000', $statement['entries'][1]['running_balance']);
        self::assertSame('6000.0000', $statement['entries'][2]['running_balance']);
    }

    public function test_loyalty_statement_calculates_running_points_balance_correctly(): void
    {
        $loyaltyAccount = CustomerLoyaltyAccount::create([
            'customer_id' => $this->customer->id,
            'status' => LoyaltyAccountStatus::Active,
        ]);

        CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $loyaltyAccount->id,
            'entry_number' => 'LOY-ST-001',
            'type' => LoyaltyEntryType::Earn,
            'points' => 100,
            'source_type' => 'Order',
            'source_id' => 1,
            'source_event' => 'DELIVERY_DELIVERED',
            'description' => 'نقاط طلب',
            'occurred_at' => now()->subHours(2),
        ]);

        CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $loyaltyAccount->id,
            'entry_number' => 'LOY-ST-002',
            'type' => LoyaltyEntryType::AdjustmentDebit,
            'points' => 30,
            'source_type' => 'ManualAdjustment',
            'source_id' => 1,
            'source_event' => 'ADMIN_MANUAL_ADJUSTMENT',
            'description' => 'خصم إداري',
            'occurred_at' => now()->subHour(),
        ]);

        $loyaltyStatementQuery = app(LoyaltyStatementQuery::class);
        $statement = $loyaltyStatementQuery->getStatement($loyaltyAccount);

        self::assertCount(2, $statement['entries']);
        self::assertSame(70, $statement['closing_points']);
        self::assertSame(100, $statement['entries'][0]['running_points']);
        self::assertSame(70, $statement['entries'][1]['running_points']);
    }
}
