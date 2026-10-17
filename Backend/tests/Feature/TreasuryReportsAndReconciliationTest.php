<?php

namespace Tests\Feature;

use App\Enums\TreasuryTransactionStatus;
use App\Enums\TreasuryTransactionType;
use App\Models\Account;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TreasuryReportsAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Currency $currency;

    private Cashbox $cashbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->currency = Currency::where('code', 'YER')->first() ?? Currency::first();

        $cashboxGl = Account::where('code', '1110')->first();
        $revenueGl = Account::where('code', '4100')->first();

        $this->cashbox = Cashbox::create([
            'code' => 'CSH-REP-01',
            'name_ar' => 'صندوق التقارير',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $cashboxGl->id,
            'is_active' => true,
        ]);

        $this->accountant = User::create([
            'name' => 'Treasury Reporter',
            'email' => 'treasury_report@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);

        // Post a transaction
        $trx = TreasuryTransaction::create([
            'transaction_number' => 'TRX-REP-0001',
            'transaction_type' => TreasuryTransactionType::CashReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
            'offset_account_id' => $revenueGl->id,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 45000,
            'base_currency_amount' => 45000,
            'description' => 'Report verification transaction',
            'created_by' => $this->accountant->id,
        ]);

        $this->actingAs($this->accountant)
            ->post(route('admin.finance.treasury.transactions.post', $trx));
    }

    public function test_accountant_can_view_treasury_ledger_report(): void
    {
        $response = $this->actingAs($this->accountant)->get(route('admin.finance.treasury.reports.ledger', [
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
        ]));

        $response->assertOk();
        $response->assertSee('كشف حساب الخزينة');
        $response->assertSee('45,000.00');
    }

    public function test_accountant_can_view_treasury_reconciliation_report(): void
    {
        $response = $this->actingAs($this->accountant)->get(route('admin.finance.treasury.reports.reconciliation'));

        $response->assertOk();
        $response->assertSee('تقرير مطابقة أرصدة الخزينة مع الأستاذ العام');
        $response->assertSee('صندوق التقارير');
        $response->assertSee('سليم ومتطابق');
    }
}
