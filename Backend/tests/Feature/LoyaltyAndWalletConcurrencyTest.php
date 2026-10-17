<?php

namespace Tests\Feature;

use App\Actions\AdjustLoyaltyPointsAction;
use App\Actions\CreditCustomerWalletAction;
use App\Actions\DebitCustomerWalletAction;
use App\Actions\ReverseCustomerWalletEntryAction;
use App\DTOs\CustomerWalletAdjustmentData;
use App\DTOs\LoyaltyAdjustmentData;
use App\Enums\AccountingPeriodStatus;
use App\Enums\LoyaltyAccountStatus;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerWalletAccount;
use App\Models\User;
use App\Services\CustomerWalletBalanceQuery;
use App\Services\LoyaltyBalanceQuery;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoyaltyAndWalletConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Customer $customer;

    private Currency $currency;

    private AccountingPeriod $openPeriod;

    private CustomerWalletBalanceQuery $walletBalanceQuery;

    private LoyaltyBalanceQuery $loyaltyBalanceQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        $this->accountant = User::create([
            'name' => 'المحاسب المالي',
            'email' => 'concurrency_accountant@lamma.test',
            'password' => bcrypt('password123'),
        ]);

        $this->customer = Customer::create([
            'name' => 'شركة التضامن',
            'phone' => '778899001',
            'normalized_phone' => '778899001',
            'is_active' => true,
        ]);

        $this->currency = Currency::where('is_default', true)->firstOrFail();

        $this->openPeriod = AccountingPeriod::create([
            'name' => 'فترة سبتمبر 2026',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);

        $this->walletBalanceQuery = new CustomerWalletBalanceQuery;
        $this->loyaltyBalanceQuery = new LoyaltyBalanceQuery;
    }

    public function test_wallet_debits_strictly_prevent_overdraw_and_maintain_non_negative_balance(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $debitAction = app(DebitCustomerWalletAction::class);

        // Initial credit of 10,000
        $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '10000.0000',
            type: 'credit',
            reason: 'رصيد افتتاحي للاختبار',
            createdBy: $this->accountant->id,
        ));

        $walletAccount = CustomerWalletAccount::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame('10000.0000', $this->walletBalanceQuery->getBalance($walletAccount));

        // First debit of 4,000 -> succeeds, balance = 6,000
        $debitAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '4000.0000',
            type: 'debit',
            reason: 'خصم 1',
            createdBy: $this->accountant->id,
        ));
        self::assertSame('6000.0000', $this->walletBalanceQuery->getBalance($walletAccount));

        // Second debit of 4,000 -> succeeds, balance = 2,000
        $debitAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '4000.0000',
            type: 'debit',
            reason: 'خصم 2',
            createdBy: $this->accountant->id,
        ));
        self::assertSame('2000.0000', $this->walletBalanceQuery->getBalance($walletAccount));

        // Third debit of 4,000 -> must fail because available balance is only 2,000
        $failed = false;
        try {
            $debitAction->execute(new CustomerWalletAdjustmentData(
                customerId: $this->customer->id,
                currencyId: $this->currency->id,
                amount: '4000.0000',
                type: 'debit',
                reason: 'خصم 3 متجاوز للرصيد',
                createdBy: $this->accountant->id,
            ));
        } catch (DomainException) {
            $failed = true;
        }

        self::assertTrue($failed, 'Expected debit exceeding balance to throw DomainException.');
        self::assertSame('2000.0000', $this->walletBalanceQuery->getBalance($walletAccount));
    }

    public function test_loyalty_adjustments_strictly_prevent_negative_points_balance(): void
    {
        $adjustAction = app(AdjustLoyaltyPointsAction::class);

        CustomerLoyaltyAccount::create([
            'customer_id' => $this->customer->id,
            'status' => LoyaltyAccountStatus::Active,
        ]);

        // Credit 100 points
        $adjustAction->execute(new LoyaltyAdjustmentData(
            customerId: $this->customer->id,
            points: 100,
            type: 'credit',
            reason: 'إيداع نقاط أولي',
            createdBy: $this->accountant->id,
        ));

        $loyaltyAccount = CustomerLoyaltyAccount::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame(100, $this->loyaltyBalanceQuery->getBalance($loyaltyAccount));

        // Debit 60 points -> succeeds (40 remaining)
        $adjustAction->execute(new LoyaltyAdjustmentData(
            customerId: $this->customer->id,
            points: 60,
            type: 'debit',
            reason: 'خصم أول',
            createdBy: $this->accountant->id,
        ));
        self::assertSame(40, $this->loyaltyBalanceQuery->getBalance($loyaltyAccount));

        // Debit 60 points -> must fail because only 40 are available
        $failed = false;
        try {
            $adjustAction->execute(new LoyaltyAdjustmentData(
                customerId: $this->customer->id,
                points: 60,
                type: 'debit',
                reason: 'خصم متجاوز للنقاط',
                createdBy: $this->accountant->id,
            ));
        } catch (DomainException) {
            $failed = true;
        }

        self::assertTrue($failed, 'Expected loyalty debit exceeding available points to throw DomainException.');
        self::assertSame(40, $this->loyaltyBalanceQuery->getBalance($loyaltyAccount));
    }

    public function test_entry_reversal_is_strictly_idempotent_and_cannot_be_executed_twice(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $reverseAction = app(ReverseCustomerWalletEntryAction::class);

        $entry = $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $this->customer->id,
            currencyId: $this->currency->id,
            amount: '3500.0000',
            type: 'credit',
            reason: 'شحن رصيد مراد عكسه',
            createdBy: $this->accountant->id,
        ));

        // First reversal succeeds
        $reversal = $reverseAction->execute($entry, 'عكس أول', $this->accountant->id);
        self::assertNotNull($reversal);

        // Second reversal must throw DomainException
        $this->expectException(DomainException::class);
        $reverseAction->execute($entry, 'عكس ثانٍ مرفوض', $this->accountant->id);
    }
}
