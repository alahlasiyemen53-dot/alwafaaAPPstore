<?php

namespace Tests\Feature;

use App\Actions\CreateDraftJournalEntryAction;
use App\Actions\GenerateJournalEntryNumberAction;
use App\Actions\PostJournalEntryAction;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalEntryLineData;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

final class JournalEntryBalanceAndInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private AccountingPeriod $period;

    private Currency $baseCurrency;

    private Currency $foreignCurrency;

    private Account $debitAccount;

    private Account $creditAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@lamma.test'],
            ['name' => 'Admin User', 'password' => bcrypt('password')]
        );
        $admin->syncRoles(['super_admin']);
        $this->actingAs($admin);

        $this->period = AccountingPeriod::where('status', 'open')->firstOrFail();
        $this->baseCurrency = Currency::where('is_default', true)->firstOrFail();
        $this->foreignCurrency = Currency::where('code', 'USD')->firstOrFail();

        $this->debitAccount = Account::where('code', '1110')->firstOrFail(); // Cash
        $this->creditAccount = Account::where('code', '2110')->firstOrFail(); // Payable
    }

    public function test_generates_sequential_unique_entry_numbers(): void
    {
        $generator = app(GenerateJournalEntryNumberAction::class);

        $num1 = $generator->execute(now());
        $this->assertStringStartsWith('JE-', $num1);

        // Manually insert to test sequence
        $dto = new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد رقم 1',
            currencyId: $this->baseCurrency->id,
            lines: [
                new JournalEntryLineData($this->debitAccount->id, null, '50.0000', '0.0000', $this->baseCurrency->id, '50.0000', '1.00000000', '50.0000'),
                new JournalEntryLineData($this->creditAccount->id, null, '0.0000', '50.0000', $this->baseCurrency->id, '50.0000', '1.00000000', '50.0000'),
            ]
        );

        $entry1 = app(CreateDraftJournalEntryAction::class)->execute($dto);
        $this->assertEquals($num1, $entry1->entry_number);

        $entry2 = app(CreateDraftJournalEntryAction::class)->execute($dto);
        $this->assertNotEquals($entry1->entry_number, $entry2->entry_number);
    }

    public function test_unbalanced_entry_is_rejected_on_post(): void
    {
        $action = app(CreateDraftJournalEntryAction::class);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('كل سطر في القيد يجب أن يحتوي إما على مدين موجب أو دائن موجب');

        // Debit and credit on same line
        $action->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد خاطئ السطور',
            currencyId: $this->baseCurrency->id,
            lines: [
                new JournalEntryLineData($this->debitAccount->id, null, '100.0000', '50.0000', $this->baseCurrency->id, '100.0000', '1.00000000', '100.0000'),
                new JournalEntryLineData($this->creditAccount->id, null, '0.0000', '50.0000', $this->baseCurrency->id, '50.0000', '1.00000000', '50.0000'),
            ]
        ));
    }

    public function test_line_with_both_zero_is_rejected(): void
    {
        $action = app(CreateDraftJournalEntryAction::class);

        $this->expectException(UnprocessableEntityHttpException::class);

        $action->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد صفر',
            currencyId: $this->baseCurrency->id,
            lines: [
                new JournalEntryLineData($this->debitAccount->id, null, '0.0000', '0.0000', $this->baseCurrency->id, '0.0000', '1.00000000', '0.0000'),
                new JournalEntryLineData($this->creditAccount->id, null, '0.0000', '100.0000', $this->baseCurrency->id, '100.0000', '1.00000000', '100.0000'),
            ]
        ));
    }

    public function test_entry_with_less_than_two_lines_is_rejected(): void
    {
        $action = app(CreateDraftJournalEntryAction::class);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('سطرين');

        $action->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد بسطر واحد',
            currencyId: $this->baseCurrency->id,
            lines: [
                new JournalEntryLineData($this->debitAccount->id, null, '100.0000', '0.0000', $this->baseCurrency->id, '100.0000', '1.00000000', '100.0000'),
            ]
        ));
    }

    public function test_multicurrency_snapshot_calculates_base_currency_amount(): void
    {
        $action = app(CreateDraftJournalEntryAction::class);
        $postAction = app(PostJournalEntryAction::class);

        // Foreign currency: USD with exchange rate 535.00000000
        $dto = new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد بالدولار الأمريكي',
            currencyId: $this->foreignCurrency->id,
            exchangeRateSnapshot: '535.00000000',
            lines: [
                new JournalEntryLineData(
                    accountId: $this->debitAccount->id,
                    description: 'استلام 100 دولار نقداً',
                    debit: '100.0000',
                    credit: '0.0000',
                    transactionCurrencyId: $this->foreignCurrency->id,
                    transactionAmount: '100.0000',
                    exchangeRateSnapshot: '535.00000000',
                    baseCurrencyAmount: '0.0000'
                ),
                new JournalEntryLineData(
                    accountId: $this->creditAccount->id,
                    description: 'استحقاق المورد 100 دولار',
                    debit: '0.0000',
                    credit: '100.0000',
                    transactionCurrencyId: $this->foreignCurrency->id,
                    transactionAmount: '100.0000',
                    exchangeRateSnapshot: '535.00000000',
                    baseCurrencyAmount: '0.0000'
                ),
            ]
        );

        $entry = $action->execute($dto);

        // 100 * 535 = 53500.0000 in base currency
        $this->assertEquals('53500.0000', (string) $entry->total_debit);
        $this->assertEquals('53500.0000', (string) $entry->total_credit);

        $posted = $postAction->execute($entry);
        $this->assertTrue($posted->isPosted());
        $this->assertTrue($posted->isBalanced());
    }
}
