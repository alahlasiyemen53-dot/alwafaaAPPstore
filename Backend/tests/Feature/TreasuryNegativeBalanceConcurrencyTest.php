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

final class TreasuryNegativeBalanceConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Currency $currency;

    private Cashbox $cashbox;

    private Account $expenseGl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->currency = Currency::where('code', 'YER')->first() ?? Currency::first();

        $cashboxGl = Account::where('code', '1110')->first();
        $this->expenseGl = Account::where('code', '5100')->first() ?? Account::where('type', 'expense')->first();

        $this->cashbox = Cashbox::create([
            'code' => 'CSH-001',
            'name_ar' => 'صندوق محمي من الرصيد السالب',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $cashboxGl->id,
            'is_active' => true,
        ]);

        $this->accountant = User::create([
            'name' => 'Treasury Accountant',
            'email' => 'treasury_overdraft@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);
    }

    public function test_cannot_post_outgoing_payment_when_cashbox_has_insufficient_gl_balance(): void
    {
        // Initial GL balance is 0.00
        $this->assertEquals('0.0000', $this->cashbox->calculateBalance());

        // Attempt to create and post a CashPayment of 5,000
        $payment = TreasuryTransaction::create([
            'transaction_number' => 'TRX-PAY-OVERDRAFT',
            'transaction_type' => TreasuryTransactionType::CashPayment,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
            'offset_account_id' => $this->expenseGl->id,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 5000,
            'base_currency_amount' => 5000,
            'description' => 'Office stationery expense',
            'created_by' => $this->accountant->id,
        ]);

        $response = $this->actingAs($this->accountant)
            ->post(route('admin.finance.treasury.transactions.post', $payment));

        // It must fail with 422 Unprocessable Entity due to insufficient balance
        $response->assertStatus(422);

        // Transaction must remain Draft and NO journal entry should be created
        $payment->refresh();
        $this->assertEquals(TreasuryTransactionStatus::Draft, $payment->status);
        $this->assertNull($payment->journal_entry_id);
    }
}
