<?php

namespace Tests\Feature;

use App\Actions\CreateDraftJournalEntryAction;
use App\Actions\PostJournalEntryAction;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalEntryLineData;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\User;
use App\Queries\GeneralLedgerQuery;
use App\Queries\TrialBalanceQuery;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrialBalanceAndGeneralLedgerReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AccountingPeriod $period;

    private Currency $currency;

    private Account $cashAccount;

    private Account $payableAccount;

    private Account $revenueAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@lamma.test'],
            ['name' => 'Admin User', 'password' => bcrypt('password')]
        );
        $this->admin->syncRoles(['super_admin']);
        $this->actingAs($this->admin);

        $this->period = AccountingPeriod::where('status', 'open')->firstOrFail();
        $this->currency = Currency::where('is_default', true)->firstOrFail();

        $this->cashAccount = Account::where('code', '1110')->firstOrFail();
        $this->payableAccount = Account::where('code', '2110')->firstOrFail();
        $this->revenueAccount = Account::where('code', '4100')->firstOrFail();
        $this->expenseAccount = Account::where('code', '5200')->firstOrFail();
    }

    public function test_trial_balance_is_balanced_and_omits_draft_entries(): void
    {
        $createAction = app(CreateDraftJournalEntryAction::class);
        $postAction = app(PostJournalEntryAction::class);
        $trialBalanceQuery = app(TrialBalanceQuery::class);

        // 1. Create a DRAFT entry (should be omitted from report)
        $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'مسودة مهملة في التقارير',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '9999.0000', '0.0000', $this->currency->id, '9999.0000', '1.00000000', '9999.0000'),
                new JournalEntryLineData($this->revenueAccount->id, null, '0.0000', '9999.0000', $this->currency->id, '9999.0000', '1.00000000', '9999.0000'),
            ]
        ));

        // 2. Create and POST Entry 1: Dr Cash 3000, Cr Revenue 3000
        $entry1 = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'إيراد نقدي مرحل',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '3000.0000', '0.0000', $this->currency->id, '3000.0000', '1.00000000', '3000.0000'),
                new JournalEntryLineData($this->revenueAccount->id, null, '0.0000', '3000.0000', $this->currency->id, '3000.0000', '1.00000000', '3000.0000'),
            ]
        ));
        $postAction->execute($entry1);

        // 3. Create and POST Entry 2: Dr Expense 1000, Cr Cash 1000
        $entry2 = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'مصروف نقدي مرحل',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->expenseAccount->id, null, '1000.0000', '0.0000', $this->currency->id, '1000.0000', '1.00000000', '1000.0000'),
                new JournalEntryLineData($this->cashAccount->id, null, '0.0000', '1000.0000', $this->currency->id, '1000.0000', '1.00000000', '1000.0000'),
            ]
        ));
        $postAction->execute($entry2);

        // 4. Run Trial Balance
        $report = $trialBalanceQuery->execute($this->period->id);

        $this->assertTrue($report['is_balanced']);
        $this->assertEquals('0.0000', $report['difference']);

        // Grand Total Period Debits = 3000 + 1000 = 4000
        $this->assertEquals('4000.0000', $report['total_period_debit']);
        $this->assertEquals('4000.0000', $report['total_period_credit']);

        // Closing: Cash = Net Debit 2000, Expense = Net Debit 1000, Revenue = Net Credit 3000
        $this->assertEquals('3000.0000', $report['total_closing_debit']);
        $this->assertEquals('3000.0000', $report['total_closing_credit']);
    }

    public function test_general_ledger_calculates_running_balance(): void
    {
        $createAction = app(CreateDraftJournalEntryAction::class);
        $postAction = app(PostJournalEntryAction::class);
        $glQuery = app(GeneralLedgerQuery::class);

        // Movement 1: Dr Cash 5000
        $e1 = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: '2026-02-01',
            description: 'إيداع نقدي أول',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '5000.0000', '0.0000', $this->currency->id, '5000.0000', '1.00000000', '5000.0000'),
                new JournalEntryLineData($this->revenueAccount->id, null, '0.0000', '5000.0000', $this->currency->id, '5000.0000', '1.00000000', '5000.0000'),
            ]
        ));
        $postAction->execute($e1);

        // Movement 2: Cr Cash 1200
        $e2 = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: '2026-02-05',
            description: 'صرف نقدي',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->expenseAccount->id, null, '1200.0000', '0.0000', $this->currency->id, '1200.0000', '1.00000000', '1200.0000'),
                new JournalEntryLineData($this->cashAccount->id, null, '0.0000', '1200.0000', $this->currency->id, '1200.0000', '1.00000000', '1200.0000'),
            ]
        ));
        $postAction->execute($e2);

        $report = $glQuery->execute($this->cashAccount->id);

        $this->assertEquals(2, count($report['rows']));
        $this->assertEquals('5000.0000', $report['rows'][0]['running_balance']);
        $this->assertEquals('3800.0000', $report['rows'][1]['running_balance']);
        $this->assertEquals('3800.0000', $report['closing_balance']);
    }

    public function test_financial_report_views_render_successfully(): void
    {
        $response1 = $this->get(route('admin.finance.reports.trial-balance'));
        $response1->assertOk();
        $response1->assertSee('ميزان المراجعة');

        $response2 = $this->get(route('admin.finance.reports.general-ledger', ['account_id' => $this->cashAccount->id]));
        $response2->assertOk();
        $response2->assertSee('دفتر الأستاذ العام');

        $response3 = $this->get(route('admin.finance.reports.supplier-reconciliation'));
        $response3->assertOk();
        $response3->assertSee('مطابقة حساب الموردين والذمم الدائنة');
    }
}
