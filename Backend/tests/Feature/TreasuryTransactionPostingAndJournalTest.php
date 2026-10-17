<?php

namespace Tests\Feature;

use App\Enums\JournalEntryStatus;
use App\Enums\TreasuryTransactionStatus;
use App\Enums\TreasuryTransactionType;
use App\Models\Account;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TreasuryTransactionPostingAndJournalTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Currency $currency;

    private Cashbox $cashbox;

    private Account $revenueAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->currency = Currency::where('code', 'YER')->first() ?? Currency::first();

        $cashboxGl = Account::where('code', '1110')->first();
        $this->revenueAccount = Account::where('code', '4100')->first(); // Sales Revenue
        $this->expenseAccount = Account::where('code', '5100')->first() ?? Account::where('type', 'expense')->first();

        $this->cashbox = Cashbox::create([
            'code' => 'CSH-001',
            'name_ar' => 'الصندوق الرئيسي',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $cashboxGl->id,
            'is_active' => true,
        ]);

        $this->accountant = User::create([
            'name' => 'Treasury Accountant',
            'email' => 'treasury_posting@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);
    }

    public function test_can_create_draft_receipt_transaction_and_update_it(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.transactions.store'), [
            'transaction_type' => TreasuryTransactionType::CashReceipt->value,
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
            'currency_id' => $this->currency->id,
            'offset_account_id' => $this->revenueAccount->id,
            'amount' => 50000,
            'exchange_rate_snapshot' => 1.0,
            'description' => 'Draft cash receipt for services',
        ]);

        $trx = TreasuryTransaction::where('description', 'Draft cash receipt for services')->first();
        $this->assertNotNull($trx);
        $response->assertRedirect(route('admin.finance.treasury.transactions.show', $trx));

        $this->assertEquals(TreasuryTransactionStatus::Draft, $trx->status);
        $this->assertEquals(50000, (float) $trx->amount);
        $this->assertNull($trx->journal_entry_id);

        // Update draft
        $updateResponse = $this->actingAs($this->accountant)->put(route('admin.finance.treasury.transactions.update', $trx), [
            'transaction_type' => TreasuryTransactionType::CashReceipt->value,
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
            'currency_id' => $this->currency->id,
            'offset_account_id' => $this->revenueAccount->id,
            'amount' => 60000,
            'exchange_rate_snapshot' => 1.0,
            'description' => 'Updated draft cash receipt',
        ]);

        $updateResponse->assertRedirect(route('admin.finance.treasury.transactions.show', $trx));
        $this->assertEquals(60000, (float) $trx->fresh()->amount);
    }

    public function test_posting_cash_receipt_creates_balanced_gl_journal_entry(): void
    {
        $trx = TreasuryTransaction::create([
            'transaction_number' => 'TRX-202609-0001',
            'transaction_type' => TreasuryTransactionType::CashReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
            'offset_account_id' => $this->revenueAccount->id,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 75000,
            'base_currency_amount' => 75000,
            'description' => 'Post cash receipt test',
            'created_by' => $this->accountant->id,
        ]);

        $response = $this->actingAs($this->accountant)
            ->post(route('admin.finance.treasury.transactions.post', $trx));

        $response->assertRedirect();
        $trx->refresh();

        $this->assertEquals(TreasuryTransactionStatus::Posted, $trx->status);
        $this->assertNotNull($trx->posted_at);
        $this->assertNotNull($trx->journal_entry_id);

        $journal = $trx->journalEntry;
        $this->assertNotNull($journal);
        $this->assertEquals(JournalEntryStatus::Posted, $journal->status);
        $this->assertEquals(75000, (float) $journal->total_debit);
        $this->assertEquals(75000, (float) $journal->total_credit);

        // Verify journal lines: Cashbox GL is Debited, Revenue GL is Credited
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => $this->cashbox->gl_account_id,
            'debit' => 75000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => $this->revenueAccount->id,
            'debit' => 0,
            'credit' => 75000,
        ]);
    }

    public function test_posted_transaction_is_immutable_and_cannot_be_edited_or_deleted(): void
    {
        $trx = TreasuryTransaction::create([
            'transaction_number' => 'TRX-202609-0002',
            'transaction_type' => TreasuryTransactionType::CashReceipt,
            'status' => TreasuryTransactionStatus::Posted,
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
            'offset_account_id' => $this->revenueAccount->id,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 20000,
            'base_currency_amount' => 20000,
            'description' => 'Already posted',
            'created_by' => $this->accountant->id,
            'posted_at' => now(),
        ]);

        $response = $this->actingAs($this->accountant)
            ->put(route('admin.finance.treasury.transactions.update', $trx), [
                'transaction_type' => TreasuryTransactionType::CashReceipt->value,
                'account_type' => Cashbox::class,
                'account_id' => $this->cashbox->id,
                'currency_id' => $this->currency->id,
                'offset_account_id' => $this->revenueAccount->id,
                'amount' => 99999,
                'description' => 'Try to change',
            ]);

        $response->assertStatus(409);
        $this->assertEquals(20000, (float) $trx->fresh()->amount);
    }

    public function test_reversing_posted_transaction_creates_inverse_journal_and_marks_reversed(): void
    {
        $trx = TreasuryTransaction::create([
            'transaction_number' => 'TRX-202609-0003',
            'transaction_type' => TreasuryTransactionType::CashReceipt,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => Cashbox::class,
            'account_id' => $this->cashbox->id,
            'offset_account_id' => $this->revenueAccount->id,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => 1.0,
            'amount' => 30000,
            'base_currency_amount' => 30000,
            'description' => 'Transaction to reverse',
            'created_by' => $this->accountant->id,
        ]);

        $this->actingAs($this->accountant)
            ->post(route('admin.finance.treasury.transactions.post', $trx));

        $trx->refresh();
        $this->assertEquals(TreasuryTransactionStatus::Posted, $trx->status);
        $originalJournalId = $trx->journal_entry_id;

        // Reverse transaction
        $reverseResponse = $this->actingAs($this->accountant)
            ->post(route('admin.finance.treasury.transactions.reverse', $trx), [
                'reason' => 'Customer refunded error payment',
            ]);

        $reverseResponse->assertRedirect();
        $trx->refresh();

        $this->assertEquals(TreasuryTransactionStatus::Reversed, $trx->status);
        $this->assertEquals('Customer refunded error payment', $trx->reversal_reason);

        // Find reversal journal entry that references this journal as reversal_of_id
        $reversalJournal = JournalEntry::where('reversal_of_id', $originalJournalId)
            ->first();

        $this->assertNotNull($reversalJournal);
        $this->assertEquals(JournalEntryStatus::Posted, $reversalJournal->status);
        $this->assertEquals(30000, (float) $reversalJournal->total_debit);
        $this->assertEquals(30000, (float) $reversalJournal->total_credit);

        // In the reversal journal, the original debit/credit is swapped:
        // Revenue is Debited, Cashbox is Credited
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $reversalJournal->id,
            'account_id' => $this->revenueAccount->id,
            'debit' => 30000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $reversalJournal->id,
            'account_id' => $this->cashbox->gl_account_id,
            'debit' => 0,
            'credit' => 30000,
        ]);
    }
}
