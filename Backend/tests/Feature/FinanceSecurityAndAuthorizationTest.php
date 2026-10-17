<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FinanceSecurityAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->accountant = User::create([
            'name' => 'Accountant User',
            'email' => 'accountant@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);

        $this->unauthorizedUser = User::create([
            'name' => 'Support User',
            'email' => 'support@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->unauthorizedUser->syncRoles(['customer_support']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.finance.accounts.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.finance.journal-entries.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.finance.periods.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.finance.reports.trial-balance'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.finance.reports.general-ledger'))->assertRedirect(route('admin.login'));
    }

    public function test_unauthorized_user_is_forbidden_from_finance(): void
    {
        $this->actingAs($this->unauthorizedUser);

        $this->get(route('admin.finance.accounts.index'))->assertForbidden();
        $this->get(route('admin.finance.journal-entries.index'))->assertForbidden();
        $this->get(route('admin.finance.periods.index'))->assertForbidden();
        $this->get(route('admin.finance.reports.trial-balance'))->assertForbidden();
        $this->get(route('admin.finance.reports.general-ledger'))->assertForbidden();
    }

    public function test_accountant_can_access_finance_and_manage_journal_entries(): void
    {
        $this->actingAs($this->accountant);

        $this->get(route('admin.finance.accounts.index'))->assertOk();
        $this->get(route('admin.finance.journal-entries.index'))->assertOk();
        $this->get(route('admin.finance.periods.index'))->assertOk();
        $this->get(route('admin.finance.reports.trial-balance'))->assertOk();
        $this->get(route('admin.finance.reports.general-ledger'))->assertOk();

        $period = AccountingPeriod::firstOrFail();
        $currency = Currency::where('is_default', true)->firstOrFail();
        $cash = Account::where('code', '1110')->firstOrFail();
        $revenue = Account::where('code', '4100')->firstOrFail();

        // Create draft journal entry through Web Form
        $response = $this->post(route('admin.finance.journal-entries.store'), [
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'قيد تجريبي بواسطة المحاسب',
            'currency_id' => $currency->id,
            'lines' => [
                [
                    'account_id' => $cash->id,
                    'description' => 'مدين صندوق',
                    'debit' => 500,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenue->id,
                    'description' => 'دائن مبيعات',
                    'debit' => 0,
                    'credit' => 500,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('journal_entries', [
            'description' => 'قيد تجريبي بواسطة المحاسب',
            'status' => 'draft',
        ]);
    }
}
