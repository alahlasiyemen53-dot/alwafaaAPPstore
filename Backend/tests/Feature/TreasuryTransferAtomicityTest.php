<?php

namespace Tests\Feature;

use App\Enums\JournalEntryStatus;
use App\Enums\TreasuryTransactionStatus;
use App\Enums\TreasuryTransactionType;
use App\Models\Account;
use App\Models\BankAccount;
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

final class TreasuryTransferAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Currency $currency;

    private Cashbox $cashbox;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->currency = Currency::where('code', 'YER')->first() ?? Currency::first();

        $cashboxGl = Account::where('code', '1110')->first();
        $bankGl = Account::where('code', '1120')->first();
        $revenueGl = Account::where('code', '4100')->first();

        $this->cashbox = Cashbox::create([
            'code' => 'CSH-001',
            'name_ar' => 'صندوق الفرع',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $cashboxGl->id,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::create([
            'code' => 'BNK-001',
            'bank_name' => 'بنك التضامن',
            'account_name' => 'الحساب البنكي',
            'account_number' => 'ACC-987654321',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $bankGl->id,
            'is_active' => true,
        ]);

        $this->accountant = User::create([
            'name' => 'Treasury Accountant',
            'email' => 'treasury_transfer@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);

        // Fund the cashbox with 200,000 via a posted CashReceipt
        $fundTrx = TreasuryTransaction::create([
            'transaction_number' => 'TRX-FUND-001',
            'transaction_type' => TreasuryTransactionType::CashReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
            'offset_account_id' => $revenueGl->id,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 200000,
            'base_currency_amount' => 200000,
            'description' => 'Initial capital injection',
            'created_by' => $this->accountant->id,
        ]);

        $this->actingAs($this->accountant)
            ->post(route('admin.finance.treasury.transactions.post', $fundTrx));
    }

    public function test_can_create_draft_transfer_between_cashbox_and_bank(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.transfers.store'), [
            'source_type' => Cashbox::class,
            'source_id' => $this->cashbox->id,
            'destination_type' => BankAccount::class,
            'destination_id' => $this->bankAccount->id,
            'amount' => 80000,
            'exchange_rate_snapshot' => 1.0,
            'description' => 'Transfer surplus cash to bank account',
        ]);

        $transfer = TreasuryTransaction::where('description', 'Transfer surplus cash to bank account')->first();
        $this->assertNotNull($transfer);
        $response->assertRedirect(route('admin.finance.treasury.transactions.show', $transfer));

        $this->assertEquals(TreasuryTransactionType::Transfer, $transfer->transaction_type);
        $this->assertEquals(TreasuryTransactionStatus::Draft, $transfer->status);
        $this->assertEquals(Cashbox::class, $transfer->account_type);
        $this->assertEquals($this->cashbox->id, $transfer->account_id);
        $this->assertEquals(BankAccount::class, $transfer->destination_type);
        $this->assertEquals($this->bankAccount->id, $transfer->destination_id);
        $this->assertEquals(80000, (float) $transfer->amount);
    }

    public function test_posting_transfer_creates_single_atomic_journal_entry_moving_funds(): void
    {
        $transfer = TreasuryTransaction::create([
            'transaction_number' => 'TRX-TRF-0001',
            'transaction_type' => TreasuryTransactionType::Transfer,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
            'destination_type' => BankAccount::class,
            'destination_id' => $this->bankAccount->id,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 50000,
            'base_currency_amount' => 50000,
            'description' => 'Transfer deposit to bank',
            'created_by' => $this->accountant->id,
        ]);

        $postResponse = $this->actingAs($this->accountant)
            ->post(route('admin.finance.treasury.transactions.post', $transfer));

        $postResponse->assertRedirect();
        $transfer->refresh();

        $this->assertEquals(TreasuryTransactionStatus::Posted, $transfer->status);
        $this->assertNotNull($transfer->journal_entry_id);

        $journal = $transfer->journalEntry;
        $this->assertNotNull($journal);
        $this->assertEquals(JournalEntryStatus::Posted, $journal->status);
        $this->assertEquals(50000, (float) $journal->total_debit);
        $this->assertEquals(50000, (float) $journal->total_credit);

        // Atomic Journal Invariant:
        // Destination (Bank GL) is Debited with 50,000
        // Source (Cashbox GL) is Credited with 50,000
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => $this->bankAccount->gl_account_id,
            'debit' => 50000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => $this->cashbox->gl_account_id,
            'debit' => 0,
            'credit' => 50000,
        ]);

        // Verifying balance projections derived from GL lines
        $this->assertEquals(150000.0, (float) $this->cashbox->calculateBalance());
        $this->assertEquals(50000.0, (float) $this->bankAccount->calculateBalance());
    }

    public function test_cannot_transfer_between_same_account(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.transfers.store'), [
            'source_type' => Cashbox::class,
            'source_id' => $this->cashbox->id,
            'destination_type' => Cashbox::class,
            'destination_id' => $this->cashbox->id,
            'amount' => 10000,
            'description' => 'Self transfer',
        ]);

        $response->assertSessionHasErrors();
    }
}
