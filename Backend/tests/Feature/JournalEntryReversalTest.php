<?php

namespace Tests\Feature;

use App\Actions\CreateDraftJournalEntryAction;
use App\Actions\PostJournalEntryAction;
use App\Actions\ReverseJournalEntryAction;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalEntryLineData;
use App\DTOs\ReverseJournalEntryData;
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
use Tests\TestCase;

final class JournalEntryReversalTest extends TestCase
{
    use RefreshDatabase;

    private AccountingPeriod $period;

    private Currency $currency;

    private Account $cashAccount;

    private Account $payableAccount;

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
        $this->payableAccount = Account::where('code', '2110')->firstOrFail();
    }

    public function test_reversal_creates_inverse_balanced_entry_and_preserves_history(): void
    {
        $createAction = app(CreateDraftJournalEntryAction::class);
        $postAction = app(PostJournalEntryAction::class);
        $reverseAction = app(ReverseJournalEntryAction::class);

        // 1. Create and post original entry: Dr Cash 1500, Cr Payable 1500
        $original = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد سداد نقدي للمورد',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, 'حساب الصندوق', '1500.0000', '0.0000', $this->currency->id, '1500.0000', '1.00000000', '1500.0000'),
                new JournalEntryLineData($this->payableAccount->id, 'حساب المورد', '0.0000', '1500.0000', $this->currency->id, '1500.0000', '1.00000000', '1500.0000'),
            ]
        ));
        $postedOriginal = $postAction->execute($original);

        $this->assertEquals('1500.0000', $this->cashAccount->calculateBalance());
        $this->assertEquals('1500.0000', $this->payableAccount->calculateBalance());

        // 2. Reverse entry
        $reversalEntry = $reverseAction->execute($postedOriginal, new ReverseJournalEntryData(
            reason: 'خطأ في تحديد قيمة السداد',
            reversalDate: now()->toDateString()
        ));

        // 3. Verify reversal entry is balanced and inverted
        $this->assertTrue($reversalEntry->isPosted());
        $this->assertTrue($reversalEntry->isBalanced());
        $this->assertEquals($postedOriginal->id, $reversalEntry->reversal_of_id);

        // Lines in reversal: Cash credit 1500, Payable debit 1500
        $cashLine = $reversalEntry->lines()->where('account_id', $this->cashAccount->id)->firstOrFail();
        $this->assertEquals('0.0000', (string) $cashLine->debit);
        $this->assertEquals('1500.0000', (string) $cashLine->credit);

        $payableLine = $reversalEntry->lines()->where('account_id', $this->payableAccount->id)->firstOrFail();
        $this->assertEquals('1500.0000', (string) $payableLine->debit);
        $this->assertEquals('0.0000', (string) $payableLine->credit);

        // 4. Verify original entry is marked Reversed
        $freshOriginal = $postedOriginal->fresh();
        $this->assertTrue($freshOriginal->isReversed());
        $this->assertNotNull($freshOriginal->reversed_at);
        $this->assertEquals('خطأ في تحديد قيمة السداد', $freshOriginal->reversal_reason);

        // 5. Balances return to zero net
        $this->assertEquals('0.0000', $this->cashAccount->calculateBalance());
        $this->assertEquals('0.0000', $this->payableAccount->calculateBalance());
    }

    public function test_double_reversal_of_same_entry_is_rejected(): void
    {
        $createAction = app(CreateDraftJournalEntryAction::class);
        $postAction = app(PostJournalEntryAction::class);
        $reverseAction = app(ReverseJournalEntryAction::class);

        $original = $createAction->execute(new CreateJournalEntryData(
            accountingPeriodId: $this->period->id,
            entryDate: now()->toDateString(),
            description: 'قيد لاختبار منع العكس المزدوج',
            currencyId: $this->currency->id,
            lines: [
                new JournalEntryLineData($this->cashAccount->id, null, '500.0000', '0.0000', $this->currency->id, '500.0000', '1.00000000', '500.0000'),
                new JournalEntryLineData($this->payableAccount->id, null, '0.0000', '500.0000', $this->currency->id, '500.0000', '1.00000000', '500.0000'),
            ]
        ));
        $postedOriginal = $postAction->execute($original);

        // First reversal
        $reverseAction->execute($postedOriginal, new ReverseJournalEntryData(
            reason: 'العكس الأول',
            reversalDate: now()->toDateString()
        ));

        // Second reversal attempt MUST throw ConflictHttpException
        $this->expectException(ConflictHttpException::class);
        $reverseAction->execute($postedOriginal->fresh(), new ReverseJournalEntryData(
            reason: 'العكس الثاني الممنوع',
            reversalDate: now()->toDateString()
        ));
    }
}
