<?php

namespace Tests\Feature;

use App\Actions\CreditCustomerWalletAction;
use App\Actions\DebitCustomerWalletAction;
use App\DTOs\CustomerWalletAdjustmentData;
use App\Enums\AccountingPeriodStatus;
use App\Enums\CustomerWalletEntryType;
use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerWalletAccount;
use App\Models\User;
use App\Services\CustomerWalletBalanceQuery;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

final class CustomerWalletAccountingTest extends TestCase
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
            'name' => 'المحاسب المالي',
            'email' => 'accountant@lamma.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->customer = Customer::create([
            'name' => 'مؤسسة النور',
            'phone' => '773344556',
            'normalized_phone' => '773344556',
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

    public function test_credit_customer_wallet_creates_ledger_and_balanced_posted_journal_entry(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);

        $data = new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '15000.0000',
            type: 'credit',
            reason: 'إيداع تسوية يدوية معتمدة',
            createdBy: $this->accountant->id,
        );

        $entry = $creditAction->execute($data);

        self::assertSame(CustomerWalletEntryType::ManualCredit, $entry->type);
        self::assertSame('15000.0000', $entry->amount);
        self::assertNotNull($entry->journal_entry_id);

        $journal = $entry->journalEntry;
        self::assertNotNull($journal);
        self::assertSame(JournalEntryStatus::Posted, $journal->status);
        self::assertSame('15000.0000', $journal->total_debit);
        self::assertSame('15000.0000', $journal->total_credit);

        $account = CustomerWalletAccount::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame('15000.0000', $this->balanceQuery->getBalance($account));
    }

    public function test_debit_customer_wallet_creates_ledger_and_balanced_posted_journal_entry(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $debitAction = app(DebitCustomerWalletAction::class);

        // 1. Credit 20,000
        $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '20000.0000',
            type: 'credit',
            reason: 'رصيد افتتاحي',
            createdBy: $this->accountant->id,
        ));

        // 2. Debit 8,000
        $debitEntry = $debitAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '8000.0000',
            type: 'debit',
            reason: 'خصم تسوية إدارية',
            createdBy: $this->accountant->id,
        ));

        self::assertSame(CustomerWalletEntryType::ManualDebit, $debitEntry->type);
        self::assertSame('8000.0000', $debitEntry->amount);
        self::assertNotNull($debitEntry->journal_entry_id);

        $journal = $debitEntry->journalEntry;
        self::assertSame(JournalEntryStatus::Posted, $journal->status);
        self::assertSame('8000.0000', $journal->total_debit);
        self::assertSame('8000.0000', $journal->total_credit);

        $account = CustomerWalletAccount::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame('12000.0000', $this->balanceQuery->getBalance($account));
    }

    public function test_debit_exceeding_wallet_balance_is_strictly_rejected(): void
    {
        $debitAction = app(DebitCustomerWalletAction::class);

        $this->expectException(DomainException::class);

        $debitAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '5000.0000',
            type: 'debit',
            reason: 'خصم يفوق الرصيد',
            createdBy: $this->accountant->id,
        ));
    }

    public function test_closed_accounting_period_prevents_wallet_transaction(): void
    {
        // Close period
        $this->openPeriod->update(['status' => AccountingPeriodStatus::Closed]);

        $creditAction = app(CreditCustomerWalletAction::class);

        $this->expectException(UnprocessableEntityHttpException::class);

        $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '10000.0000',
            type: 'credit',
            reason: 'محاولة في فترة مغلقة',
            createdBy: $this->accountant->id,
        ));
    }
}
