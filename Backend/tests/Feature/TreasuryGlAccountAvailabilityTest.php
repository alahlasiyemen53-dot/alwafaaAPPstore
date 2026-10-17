<?php

namespace Tests\Feature;

use App\Actions\CreateCashboxAction;
use App\Actions\CreateElectronicWalletAccountAction;
use App\Actions\CreateTreasuryCurrencyAccountAction;
use App\DTOs\CashboxData;
use App\DTOs\CreateTreasuryCurrencyAccountData;
use App\DTOs\ElectronicWalletAccountData;
use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use App\Models\User;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * Shared-GL model: one chart account may back many cashboxes/banks/wallets.
 * Operational balances stay separated via treasury_currency_account_id on
 * journal lines; availability lists all postable asset GLs.
 */
final class TreasuryGlAccountAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Currency $yer;

    private Currency $sar;

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->yer = Currency::where('code', 'YER')->firstOrFail();
        $this->sar = Currency::where('code', 'SAR')->firstOrFail();
        $this->usd = Currency::where('code', 'USD')->firstOrFail();

        $this->accountant = User::create([
            'name' => 'Treasury Accountant',
            'email' => 'gl_availability@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);
    }

    private function makeAssetGlAccount(string $code): Account
    {
        return Account::create([
            'code' => $code,
            'name_ar' => "حساب أصول $code",
            'name_en' => "Asset Account $code",
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
        ]);
    }

    public function test_cashbox_index_dropdown_includes_already_used_gl_accounts(): void
    {
        $yerGl = Account::where('code', '1110')->firstOrFail();
        $freeGl = $this->makeAssetGlAccount('1190');

        Cashbox::create([
            'code' => 'CSH-A',
            'name_ar' => 'صندوق أ',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $yerGl->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->accountant)->get(route('admin.finance.treasury.cashboxes.index'));

        $response->assertOk();
        /** @var Collection<int, Account> $availableAccounts */
        $availableAccounts = $response->viewData('availableAccounts');

        self::assertTrue($availableAccounts->contains('id', $yerGl->id), 'Shared GL may still be offered.');
        self::assertTrue($availableAccounts->contains('id', $freeGl->id), 'Free GL account must still be offered.');
    }

    public function test_bank_account_index_dropdown_includes_already_used_gl_accounts(): void
    {
        $bankGl = Account::where('code', '1120')->firstOrFail();
        $freeGl = $this->makeAssetGlAccount('1191');

        BankAccount::create([
            'code' => 'BNK-A',
            'bank_name' => 'بنك أ',
            'account_name' => 'حساب أ',
            'account_number' => 'ACC-A',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $bankGl->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->accountant)->get(route('admin.finance.treasury.bank-accounts.index'));

        $response->assertOk();
        $availableAccounts = $response->viewData('availableAccounts');

        self::assertTrue($availableAccounts->contains('id', $bankGl->id));
        self::assertTrue($availableAccounts->contains('id', $freeGl->id));
    }

    public function test_electronic_wallet_index_dropdown_includes_already_used_gl_accounts(): void
    {
        $walletGl = $this->makeAssetGlAccount('1192');
        $freeGl = $this->makeAssetGlAccount('1193');

        ElectronicWalletAccount::create([
            'code' => 'WLT-A',
            'provider_name' => 'مزود أ',
            'account_label' => 'محفظة أ',
            'account_identifier' => 'ID-A',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $walletGl->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->accountant)->get(route('admin.finance.treasury.electronic-wallets.index'));

        $response->assertOk();
        $availableAccounts = $response->viewData('availableAccounts');

        self::assertTrue($availableAccounts->contains('id', $walletGl->id));
        self::assertTrue($availableAccounts->contains('id', $freeGl->id));
    }

    /**
     * Section 25 acceptance matrix, run once per treasury entity type.
     */
    public function test_cashbox_supports_three_distinct_currencies_and_rejects_a_repeat(): void
    {
        $yerGl = Account::where('code', '1110')->firstOrFail();

        $cashbox = Cashbox::create([
            'code' => 'CSH-MULTI',
            'name_ar' => 'صندوق متعدد العملات',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $yerGl->id,
            'is_active' => true,
        ]);

        $action = app(CreateTreasuryCurrencyAccountAction::class);

        $action->execute(new CreateTreasuryCurrencyAccountData(Cashbox::class, $cashbox->id, $this->sar->id, 0, null));
        $action->execute(new CreateTreasuryCurrencyAccountData(Cashbox::class, $cashbox->id, $this->usd->id, 0, null));

        $accounts = TreasuryCurrencyAccount::where('accountable_type', Cashbox::class)->where('accountable_id', $cashbox->id)->get();
        self::assertSame(3, $accounts->count());
        self::assertTrue($accounts->every(fn ($ca) => (int) $ca->gl_account_id === (int) $yerGl->id));

        $this->expectException(ConflictHttpException::class);
        $action->execute(new CreateTreasuryCurrencyAccountData(Cashbox::class, $cashbox->id, $this->sar->id, 0, null));
    }

    public function test_bank_account_supports_three_distinct_currencies_and_rejects_a_repeat(): void
    {
        $yerGl = Account::where('code', '1120')->firstOrFail();

        $bank = BankAccount::create([
            'code' => 'BNK-MULTI',
            'bank_name' => 'بنك متعدد العملات',
            'account_name' => 'حساب متعدد',
            'account_number' => 'ACC-MULTI',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $yerGl->id,
            'is_active' => true,
        ]);

        $action = app(CreateTreasuryCurrencyAccountAction::class);

        $action->execute(new CreateTreasuryCurrencyAccountData(BankAccount::class, $bank->id, $this->sar->id, 0, null));
        $action->execute(new CreateTreasuryCurrencyAccountData(BankAccount::class, $bank->id, $this->usd->id, 0, null));

        $accounts = TreasuryCurrencyAccount::where('accountable_type', BankAccount::class)->where('accountable_id', $bank->id)->get();
        self::assertSame(3, $accounts->count());
        self::assertTrue($accounts->every(fn ($ca) => (int) $ca->gl_account_id === (int) $yerGl->id));

        $this->expectException(ConflictHttpException::class);
        $action->execute(new CreateTreasuryCurrencyAccountData(BankAccount::class, $bank->id, $this->usd->id, 0, null));
    }

    public function test_electronic_wallet_supports_three_distinct_currencies_and_rejects_a_repeat(): void
    {
        $yerGl = $this->makeAssetGlAccount('1230');

        $wallet = ElectronicWalletAccount::create([
            'code' => 'WLT-MULTI',
            'provider_name' => 'مزود متعدد',
            'account_label' => 'محفظة متعددة',
            'account_identifier' => 'ID-MULTI',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $yerGl->id,
            'is_active' => true,
        ]);

        $action = app(CreateTreasuryCurrencyAccountAction::class);

        $action->execute(new CreateTreasuryCurrencyAccountData(ElectronicWalletAccount::class, $wallet->id, $this->sar->id, 0, null));
        $action->execute(new CreateTreasuryCurrencyAccountData(ElectronicWalletAccount::class, $wallet->id, $this->usd->id, 0, null));

        $accounts = TreasuryCurrencyAccount::where('accountable_type', ElectronicWalletAccount::class)->where('accountable_id', $wallet->id)->get();
        self::assertSame(3, $accounts->count());
        self::assertTrue($accounts->every(fn ($ca) => (int) $ca->gl_account_id === (int) $yerGl->id));

        $this->expectException(ConflictHttpException::class);
        $action->execute(new CreateTreasuryCurrencyAccountData(ElectronicWalletAccount::class, $wallet->id, $this->sar->id, 0, null));
    }

    /**
     * Different parents (even of different types) may each independently
     * hold the SAME currency — only the (parent, currency) pair and the
     * GL account itself must stay unique.
     */
    public function test_different_parents_of_different_types_can_each_hold_the_same_currency(): void
    {
        $cashboxGl = Account::where('code', '1110')->firstOrFail();
        $bankGl = Account::where('code', '1120')->firstOrFail();

        Cashbox::create([
            'code' => 'CSH-SAR',
            'name_ar' => 'صندوق ر.س',
            'currency_id' => $this->sar->id,
            'gl_account_id' => $cashboxGl->id,
            'is_active' => true,
        ]);

        $bank = BankAccount::create([
            'code' => 'BNK-SAR',
            'bank_name' => 'بنك ر.س',
            'account_name' => 'حساب ر.س',
            'account_number' => 'ACC-SAR',
            'currency_id' => $this->sar->id,
            'gl_account_id' => $bankGl->id,
            'is_active' => true,
        ]);

        self::assertNotNull($bank->fresh());
        self::assertSame($this->sar->id, $bank->currency_id);
    }

    /**
     * Cross-parent GL reuse is allowed: cashbox and bank may share one chart account.
     */
    public function test_creating_a_cashbox_can_reuse_a_gl_account_already_owned_by_a_bank_account(): void
    {
        $sharedGl = $this->makeAssetGlAccount('1300');

        BankAccount::create([
            'code' => 'BNK-SHARED',
            'bank_name' => 'بنك',
            'account_name' => 'حساب',
            'account_number' => 'ACC-SHARED',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $sharedGl->id,
            'is_active' => true,
        ]);

        $cashbox = app(CreateCashboxAction::class)->execute(new CashboxData(
            code: 'CSH-SHARED',
            nameAr: 'صندوق يشارك نفس الحساب',
            nameEn: null,
            currencyId: $this->yer->id,
            glAccountId: $sharedGl->id,
            isActive: true,
            notes: null,
        ));

        self::assertSame($sharedGl->id, $cashbox->gl_account_id);
    }

    public function test_creating_a_wallet_can_reuse_a_gl_account_already_owned_by_a_cashbox(): void
    {
        $sharedGl = $this->makeAssetGlAccount('1301');

        Cashbox::create([
            'code' => 'CSH-SHARED-2',
            'name_ar' => 'صندوق',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $sharedGl->id,
            'is_active' => true,
        ]);

        $wallet = app(CreateElectronicWalletAccountAction::class)->execute(new ElectronicWalletAccountData(
            code: 'WLT-SHARED',
            providerName: 'مزود',
            accountLabel: 'محفظة تشارك نفس الحساب',
            accountIdentifier: 'ID-SHARED',
            currencyId: $this->yer->id,
            glAccountId: $sharedGl->id,
            isActive: true,
            notes: null,
        ));

        self::assertSame($sharedGl->id, $wallet->gl_account_id);
    }

    /**
     * "Only one row persisted" under near-simultaneous requests for the same
     * (parent, currency): the second call must be rejected by the Action's
     * own re-check, with the DB unique constraint on
     * treasury_currency_accounts as the final backstop (verified separately
     * in MultiCurrencyTreasuryAccountTest::test_database_uniqueness_constraint_prevents_duplicate_currency_account).
     */
    public function test_two_requests_opening_the_same_currency_for_the_same_cashbox_only_one_succeeds(): void
    {
        $yerGl = Account::where('code', '1110')->firstOrFail();

        $cashbox = Cashbox::create([
            'code' => 'CSH-RACE',
            'name_ar' => 'صندوق اختبار التزامن',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $yerGl->id,
            'is_active' => true,
        ]);

        $action = app(CreateTreasuryCurrencyAccountAction::class);

        $action->execute(new CreateTreasuryCurrencyAccountData(Cashbox::class, $cashbox->id, $this->sar->id, 0, null));

        try {
            $action->execute(new CreateTreasuryCurrencyAccountData(Cashbox::class, $cashbox->id, $this->sar->id, 0, null));
            self::fail('Expected the second request for the same (parent, currency) to be rejected.');
        } catch (ConflictHttpException) {
            // expected
        }

        self::assertSame(1, TreasuryCurrencyAccount::where('accountable_type', Cashbox::class)
            ->where('accountable_id', $cashbox->id)
            ->where('currency_id', $this->sar->id)
            ->count());
    }

    public function test_updating_cashbox_currency_is_blocked_once_a_second_currency_account_exists(): void
    {
        $yerGl = Account::where('code', '1110')->firstOrFail();

        $cashbox = Cashbox::create([
            'code' => 'CSH-LOCKED',
            'name_ar' => 'صندوق مقفل التعديل',
            'currency_id' => $this->yer->id,
            'gl_account_id' => $yerGl->id,
            'is_active' => true,
        ]);

        app(CreateTreasuryCurrencyAccountAction::class)->execute(
            new CreateTreasuryCurrencyAccountData(Cashbox::class, $cashbox->id, $this->sar->id, 0, null)
        );

        $otherGl = $this->makeAssetGlAccount('1501');

        $response = $this->actingAs($this->accountant)->put(route('admin.finance.treasury.cashboxes.update', $cashbox), [
            'code' => $cashbox->code,
            'name_ar' => $cashbox->name_ar,
            'currency_ids' => [$this->usd->id],
            'gl_account_id' => $otherGl->id,
            'is_active' => 1,
        ]);

        $response->assertStatus(409);
        self::assertSame($this->yer->id, $cashbox->fresh()->currency_id);
    }
}
