<?php

namespace Tests\Feature;

use App\Actions\CreateDraftTreasuryTransactionAction;
use App\DTOs\ReportDateRangeData;
use App\DTOs\TreasuryTransactionData;
use App\Enums\TreasuryTransactionType;
use App\Models\Account;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Queries\Reporting\FinancialIntegrityQuery;
use App\Services\TreasuryPostingService;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);
    }

    public function test_healthy_ledger_reports_no_reconciliation_issues(): void
    {
        $range = ReportDateRangeData::fromPreset('this_month');

        $result = app(FinancialIntegrityQuery::class)->execute($range);

        $this->assertTrue($result['trial_balance']['is_balanced']);
        $this->assertSame('OK', $result['treasury']['status']);
        $this->assertSame(0, $result['treasury']['mismatched_currency_lines']);
        $this->assertSame('OK', $result['inventory']['status']);
        $this->assertSame('OK', $result['wallet']['status']);
        $this->assertSame('OK', $result['coupons']['status']);
    }

    public function test_treasury_currency_isolation_is_reported_not_silently_repaired(): void
    {
        $yer = Currency::where('code', 'YER')->first() ?? Currency::first();
        $usd = Currency::where('code', 'USD')->first();
        $this->assertNotNull($usd, 'Test requires both YER and USD to be seeded.');

        ExchangeRate::create([
            'from_currency_id' => $yer->id,
            'to_currency_id' => $usd->id,
            'rate' => 0.004,
            'effective_at' => now()->subDay(),
        ]);

        $cashboxGl = Account::where('code', '1110')->firstOrFail();
        $cashbox = Cashbox::create([
            'code' => 'CSH-RECON-01',
            'name_ar' => 'صندوق مطابقة',
            'currency_id' => $yer->id,
            'gl_account_id' => $cashboxGl->id,
            'is_active' => true,
        ]);

        // Post a valid transaction in cashbox currency, then simulate an isolated DB corruption / legacy mismatch
        $data = new TreasuryTransactionData(
            transactionType: TreasuryTransactionType::CashReceipt,
            accountType: Cashbox::class,
            accountId: $cashbox->id,
            currencyId: $yer->id,
            amount: 1000.0,
            description: 'إيداع نقدي (اختبار مطابقة)',
            offsetAccountId: Account::where('code', '4100')->firstOrFail()->id,
        );

        $draft = app(CreateDraftTreasuryTransactionAction::class)->execute($data);
        $posted = app(TreasuryPostingService::class)->post($draft);

        // Directly corrupt one line to simulate a currency mismatch against the treasury account currency
        \Illuminate\Support\Facades\DB::table('journal_entry_lines')
            ->where('journal_entry_id', $posted->journal_entry_id)
            ->where('account_id', $cashboxGl->id)
            ->update(['transaction_currency_id' => $usd->id]);

        $range = ReportDateRangeData::fromPreset('this_month');
        $result = app(FinancialIntegrityQuery::class)->execute($range);

        $this->assertSame('ISSUES', $result['treasury']['status']);
        $this->assertGreaterThan(0, $result['treasury']['mismatched_currency_lines']);
    }
}
