<?php

namespace Tests\Feature;

use App\Actions\CreateAccountAction;
use App\Actions\CreateDraftJournalEntryAction;
use App\Actions\UpdateAccountAction;
use App\DTOs\CreateAccountData;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalEntryLineData;
use App\DTOs\UpdateAccountData;
use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
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

final class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
    }

    public function test_can_create_account_with_parent_and_unique_code(): void
    {
        $action = app(CreateAccountAction::class);

        $parent = Account::where('code', '1100')->firstOrFail();

        $dto = new CreateAccountData(
            code: '1199',
            nameAr: 'حساب تجريبي للاختبار',
            nameEn: 'Test Account',
            parentId: $parent->id,
            type: AccountType::Asset,
            normalBalance: AccountNormalBalance::Debit,
            isPostable: true,
            isActive: true
        );

        $account = $action->execute($dto);

        $this->assertDatabaseHas('accounts', [
            'code' => '1199',
            'name_ar' => 'حساب تجريبي للاختبار',
            'parent_id' => $parent->id,
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => 1,
            'is_active' => 1,
        ]);
        $this->assertEquals($parent->id, $account->parent_id);
    }

    public function test_duplicate_account_code_is_rejected(): void
    {
        $action = app(CreateAccountAction::class);

        $this->expectException(ConflictHttpException::class);

        $dto = new CreateAccountData(
            code: '1000', // Already exists in ChartOfAccountsSeeder
            nameAr: 'أصول مكررة',
            nameEn: null,
            parentId: null,
            type: AccountType::Asset,
            normalBalance: AccountNormalBalance::Debit
        );

        $action->execute($dto);
    }

    public function test_account_cycle_prevention(): void
    {
        $action = app(UpdateAccountAction::class);

        $parent = Account::where('code', '1000')->firstOrFail();
        $child = Account::where('code', '1100')->firstOrFail();

        // 1. Account cannot be parent of itself
        $this->expectException(UnprocessableEntityHttpException::class);
        $action->execute($parent, new UpdateAccountData(
            nameAr: 'الأصول',
            nameEn: null,
            parentId: $parent->id,
            isPostable: false,
            isActive: true
        ));
    }

    public function test_account_cannot_be_child_of_its_own_descendant(): void
    {
        $action = app(UpdateAccountAction::class);

        $grandParent = Account::where('code', '1000')->firstOrFail();
        $child = Account::where('code', '1100')->firstOrFail();

        // Trying to set child (1100) as the parent of grandParent (1000)
        $this->expectException(UnprocessableEntityHttpException::class);
        $action->execute($grandParent, new UpdateAccountData(
            nameAr: 'الأصول',
            nameEn: null,
            parentId: $child->id,
            isPostable: false,
            isActive: true
        ));
    }

    public function test_group_non_postable_account_cannot_receive_journal_lines(): void
    {
        $period = AccountingPeriod::where('status', 'open')->firstOrFail();
        $baseCurrency = Currency::where('is_default', true)->firstOrFail();
        $groupAccount = Account::where('code', '1000')->firstOrFail(); // is_postable = false
        $postableAccount = Account::where('code', '2110')->firstOrFail();

        $createDraft = app(CreateDraftJournalEntryAction::class);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('تجميعي');

        $createDraft->execute(new CreateJournalEntryData(
            accountingPeriodId: $period->id,
            entryDate: now()->toDateString(),
            description: 'محاولة قيد على حساب تجميعي',
            currencyId: $baseCurrency->id,
            lines: [
                new JournalEntryLineData(
                    accountId: $groupAccount->id,
                    description: 'سطر مدين',
                    debit: '100.0000',
                    credit: '0.0000',
                    transactionCurrencyId: $baseCurrency->id,
                    transactionAmount: '100.0000',
                    exchangeRateSnapshot: '1.00000000',
                    baseCurrencyAmount: '100.0000'
                ),
                new JournalEntryLineData(
                    accountId: $postableAccount->id,
                    description: 'سطر دائن',
                    debit: '0.0000',
                    credit: '100.0000',
                    transactionCurrencyId: $baseCurrency->id,
                    transactionAmount: '100.0000',
                    exchangeRateSnapshot: '1.00000000',
                    baseCurrencyAmount: '100.0000'
                ),
            ]
        ));
    }

    public function test_inactive_account_cannot_receive_journal_lines(): void
    {
        $period = AccountingPeriod::where('status', 'open')->firstOrFail();
        $baseCurrency = Currency::where('is_default', true)->firstOrFail();
        $activeAccount = Account::where('code', '1110')->firstOrFail();
        $inactiveAccount = Account::where('code', '2110')->firstOrFail();
        $inactiveAccount->update(['is_active' => false]);

        $createDraft = app(CreateDraftJournalEntryAction::class);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('معطل');

        $createDraft->execute(new CreateJournalEntryData(
            accountingPeriodId: $period->id,
            entryDate: now()->toDateString(),
            description: 'محاولة قيد على حساب معطل',
            currencyId: $baseCurrency->id,
            lines: [
                new JournalEntryLineData(
                    accountId: $activeAccount->id,
                    description: 'سطر مدين',
                    debit: '100.0000',
                    credit: '0.0000',
                    transactionCurrencyId: $baseCurrency->id,
                    transactionAmount: '100.0000',
                    exchangeRateSnapshot: '1.00000000',
                    baseCurrencyAmount: '100.0000'
                ),
                new JournalEntryLineData(
                    accountId: $inactiveAccount->id,
                    description: 'سطر دائن',
                    debit: '0.0000',
                    credit: '100.0000',
                    transactionCurrencyId: $baseCurrency->id,
                    transactionAmount: '100.0000',
                    exchangeRateSnapshot: '1.00000000',
                    baseCurrencyAmount: '100.0000'
                ),
            ]
        ));
    }
}
