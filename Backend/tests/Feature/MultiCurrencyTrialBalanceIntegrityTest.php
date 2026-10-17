<?php

namespace Tests\Feature;

use App\Actions\CreateDraftTreasuryTransactionAction;
use App\DTOs\TreasuryTransactionData;
use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Enums\TreasuryTransactionType;
use App\Models\Account;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Queries\TrialBalanceQuery;
use App\Services\TreasuryPostingService;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

/**
 * Covers spec sections 11-19, 28-29: Trial Balance must report in the
 * Settings base currency, must never fall back to a rate of 1 for a
 * missing foreign-currency rate, and must use the exchange rate that was
 * in effect (and snapshotted) at posting time — never a later/current rate
 * — when reporting an as_of_date in the past.
 */
final class MultiCurrencyTrialBalanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Currency $yer;

    private Currency $sar;

    private Account $sarGl;

    private Account $revenueGl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->yer = Currency::where('code', 'YER')->firstOrFail();
        $this->sar = Currency::where('code', 'SAR')->firstOrFail();

        $this->sarGl = Account::create([
            'code' => '1145',
            'name_ar' => 'صندوق ر.س',
            'name_en' => 'SAR Cashbox GL',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
        ]);

        $this->revenueGl = Account::where('code', '4100')->firstOrFail();

        $this->accountant = User::create([
            'name' => 'Treasury Accountant',
            'email' => 'trial_balance_integrity@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);
    }

    public function test_posting_treasury_transaction_in_foreign_currency_without_a_rate_fails_explicitly(): void
    {
        // No ExchangeRate row exists for SAR at all.
        $this->expectException(UnprocessableEntityHttpException::class);

        app(CreateDraftTreasuryTransactionAction::class)->execute(new TreasuryTransactionData(
            transactionType: TreasuryTransactionType::CashReceipt,
            accountType: Cashbox::class,
            accountId: Cashbox::create([
                'code' => 'CSH-NORATE',
                'name_ar' => 'صندوق بلا سعر صرف',
                'currency_id' => $this->sar->id,
                'gl_account_id' => $this->sarGl->id,
                'is_active' => true,
            ])->id,
            currencyId: $this->sar->id,
            amount: 1000,
            description: 'محاولة إيداع بدون سعر صرف معرف',
            offsetAccountId: $this->revenueGl->id,
        ));
    }

    public function test_trial_balance_uses_the_historical_rate_snapshot_not_a_later_rate(): void
    {
        // Rate in effect on 2026-01-01: 1 SAR = 100 YER
        ExchangeRate::create([
            'from_currency_id' => $this->sar->id,
            'to_currency_id' => $this->yer->id,
            'rate' => 100,
            'effective_at' => '2026-01-01 00:00:00',
        ]);

        $cashbox = Cashbox::create([
            'code' => 'CSH-HIST',
            'name_ar' => 'صندوق تاريخي',
            'currency_id' => $this->sar->id,
            'gl_account_id' => $this->sarGl->id,
            'is_active' => true,
        ]);

        // Post a 1,000 SAR receipt on 2026-01-05 -> base amount must be 100,000 YER (rate 100)
        $draftAction = app(CreateDraftTreasuryTransactionAction::class);
        $postingService = app(TreasuryPostingService::class);

        $trx = $draftAction->execute(new TreasuryTransactionData(
            transactionType: TreasuryTransactionType::CashReceipt,
            accountType: Cashbox::class,
            accountId: $cashbox->id,
            currencyId: $this->sar->id,
            amount: 1000,
            description: 'إيداع بالريال السعودي بتاريخ 2026-01-05',
            offsetAccountId: $this->revenueGl->id,
        ));

        $this->travelTo('2026-01-05 10:00:00');
        $posted = $postingService->post($trx);
        $this->travelBack();

        $posted->fresh()->journalEntry()->update(['entry_date' => '2026-01-05']);

        // Now a NEW, later rate is introduced: 1 SAR = 200 YER effective 2026-06-01.
        ExchangeRate::create([
            'from_currency_id' => $this->sar->id,
            'to_currency_id' => $this->yer->id,
            'rate' => 200,
            'effective_at' => '2026-06-01 00:00:00',
        ]);

        // Trial Balance as of 2026-01-31 (before the rate change) must still show
        // the SAR cashbox's GL line at its frozen base-currency amount (100,000 YER),
        // NOT re-converted using the later 200 rate.
        $report = app(TrialBalanceQuery::class)->execute(null, null, '2026-01-31');

        $sarCashboxRow = collect($report['accounts'])->first(
            fn (array $row) => $row['account']->id === $this->sarGl->id
        );

        self::assertNotNull($sarCashboxRow, 'SAR cashbox GL account must appear in the Trial Balance.');
        self::assertSame('100000.0000', $sarCashboxRow['closing_debit']);
        self::assertNotSame('200000.0000', $sarCashboxRow['closing_debit']);
        self::assertTrue($report['is_balanced']);
    }
}
