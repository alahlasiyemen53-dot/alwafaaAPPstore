<?php

namespace Tests\Feature;

use App\Actions\CloseAccountingPeriodAction;
use App\Actions\CreateDraftJournalEntryAction;
use App\Actions\PostJournalEntryAction;
use App\Actions\UpdateDraftJournalEntryAction;
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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

final class JournalEntryPostingAndImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private AccountingPeriod $period;

    private Currency $currency;

    private Account $cashAccount;

    private Account $capitalAccount;

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
        $this->currency = Currency::where('is_default', true)->firstOrFail();

        $this->cashAccount = Account::where('code', '1110')->firstOrFail();
        $this->capitalAccount = Account::where('code', '3100')->firstOrFail();
    }

    public function test_draft_journal_entry_has_no_general_ledger_effect(): void
    {
        $createAction = app(CreateDraftJournalEntryAction::class);

        $dto = new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'مسودة قيد إيداع رأس مال',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '5000.0000', '0.0000', $this->currency->id, '5000.0000', '1.00000000', '5000.0000'),
                new JournalEntryLineData($this->capitalAccount->id, null, '0.0000', '5000.0000', $this->currency->id, '5000.0000', '1.00000000', '5000.0000'),
            ]
        );

        $entry = $createAction->execute($dto);
        $this->assertTrue($entry->isDraft());

        // Account balances should be 0 because entry is not posted
        $this->assertEquals('0.0000', $this->cashAccount->calculateBalance());
        $this->assertEquals('0.0000', $this->capitalAccount->calculateBalance());
    }

    public function test_posting_journal_entry_updates_account_balances(): void
    {
        $createAction = app(CreateDraftJournalEntryAction::class);
        $postAction = app(PostJournalEntryAction::class);

        $entry = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد إيداع رأس مال معتمد',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '5000.0000', '0.0000', $this->currency->id, '5000.0000', '1.00000000', '5000.0000'),
                new JournalEntryLineData($this->capitalAccount->id, null, '0.0000', '5000.0000', $this->currency->id, '5000.0000', '1.00000000', '5000.0000'),
            ]
        ));

        $posted = $postAction->execute($entry);
        $this->assertTrue($posted->isPosted());
        $this->assertNotNull($posted->posted_at);

        // Account balances are now affected
        $this->assertEquals('5000.0000', $this->cashAccount->calculateBalance());
        $this->assertEquals('5000.0000', $this->capitalAccount->calculateBalance());
    }

    public function test_cannot_post_into_closed_accounting_period(): void
    {
        $createAction = app(CreateDraftJournalEntryAction::class);
        $closePeriodAction = app(CloseAccountingPeriodAction::class);
        $postAction = app(PostJournalEntryAction::class);

        $entry = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد قبل إغلاق الفترة',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '100.0000', '0.0000', $this->currency->id, '100.0000', '1.00000000', '100.0000'),
                new JournalEntryLineData($this->capitalAccount->id, null, '0.0000', '100.0000', $this->currency->id, '100.0000', '1.00000000', '100.0000'),
            ]
        ));

        // Close the period
        $closePeriodAction->execute($this->period);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('مغلقة');

        $postAction->execute($entry);
    }

    public function test_posted_journal_entry_cannot_be_edited(): void
    {
        $createAction = app(CreateDraftJournalEntryAction::class);
        $postAction = app(PostJournalEntryAction::class);
        $updateAction = app(UpdateDraftJournalEntryAction::class);

        $entry = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد للترحيل',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '100.0000', '0.0000', $this->currency->id, '100.0000', '1.00000000', '100.0000'),
                new JournalEntryLineData($this->capitalAccount->id, null, '0.0000', '100.0000', $this->currency->id, '100.0000', '1.00000000', '100.0000'),
            ]
        ));

        $posted = $postAction->execute($entry);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('لا يمكن تعديل قيد مرحل');

        $updateAction->execute($posted, new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'محاولة تعديل غير مسموحة',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '200.0000', '0.0000', $this->currency->id, '200.0000', '1.00000000', '200.0000'),
                new JournalEntryLineData($this->capitalAccount->id, null, '0.0000', '200.0000', $this->currency->id, '200.0000', '1.00000000', '200.0000'),
            ]
        ));
    }

    public function test_double_posting_same_journal_is_idempotent(): void
    {
        $createAction = app(CreateDraftJournalEntryAction::class);
        $postAction = app(PostJournalEntryAction::class);

        $entry = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد لاختبار الترحيل المكرر',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '100.0000', '0.0000', $this->currency->id, '100.0000', '1.00000000', '100.0000'),
                new JournalEntryLineData($this->capitalAccount->id, null, '0.0000', '100.0000', $this->currency->id, '100.0000', '1.00000000', '100.0000'),
            ]
        ));

        $posted1 = $postAction->execute($entry);
        $posted2 = $postAction->execute($entry);

        $this->assertEquals($posted1->id, $posted2->id);
        $this->assertEquals('100.0000', $this->cashAccount->calculateBalance());
    }
}
