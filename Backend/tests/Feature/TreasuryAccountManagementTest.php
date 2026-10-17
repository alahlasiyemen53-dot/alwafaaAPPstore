<?php

namespace Tests\Feature;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\ElectronicWalletAccount;
use App\Models\User;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TreasuryAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private User $unauthorizedUser;

    private Currency $currency;

    private Account $cashboxGlAccount;

    private Account $bankGlAccount;

    private Account $walletGlAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);

        $this->currency = Currency::where('code', 'YER')->first() ?? Currency::first();

        $this->cashboxGlAccount = Account::where('code', '1110')->first();
        $this->bankGlAccount = Account::where('code', '1120')->first();

        $this->walletGlAccount = Account::create([
            'code' => '1150',
            'name_ar' => 'المحافظ الإلكترونية',
            'name_en' => 'Electronic Wallets',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_active' => true,
            'is_postable' => true,
        ]);

        $this->accountant = User::create([
            'name' => 'Treasury Accountant',
            'email' => 'treasury_accountant@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->accountant->syncRoles(['accountant']);

        $this->unauthorizedUser = User::create([
            'name' => 'Customer Support',
            'email' => 'cs@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->unauthorizedUser->syncRoles(['customer_support']);
    }

    public function test_accountant_can_create_cashbox_with_valid_asset_gl_account(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.cashboxes.store'), [
            'code' => 'CSH-001',
            'name_ar' => 'الصندوق الرئيسي صنعاء',
            'name_en' => 'Main Head Office Cashbox',
            'currency_ids' => [$this->currency->id],
            'gl_account_id' => $this->cashboxGlAccount->id,
            'is_active' => 1,
            'notes' => 'Head office treasury',
        ]);

        $response->assertRedirect(route('admin.finance.treasury.cashboxes.index'));
        $this->assertDatabaseHas('cashboxes', [
            'code' => 'CSH-001',
            'name_ar' => 'الصندوق الرئيسي صنعاء',
            'gl_account_id' => $this->cashboxGlAccount->id,
        ]);
    }

    public function test_cashbox_cannot_use_non_asset_gl_account(): void
    {
        $revenueAccount = Account::where('code', '4100')->first();

        $response = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.cashboxes.store'), [
            'code' => 'CSH-INV',
            'name_ar' => 'صندوق غير صالح',
            'currency_ids' => [$this->currency->id],
            'gl_account_id' => $revenueAccount->id,
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors(['gl_account_id']);
    }

    public function test_accountant_can_create_bank_account_and_toggle_status(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.bank-accounts.store'), [
            'code' => 'BNK-001',
            'bank_name' => 'بنك الكريمي الإسلامي',
            'account_name' => 'الحساب التشغيلي الرئيسي',
            'account_number' => 'ACC-123456789',
            'iban' => 'YE98KURM12345678901234',
            'currency_ids' => [$this->currency->id],
            'gl_account_id' => $this->bankGlAccount->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.finance.treasury.bank-accounts.index'));
        $bankAccount = BankAccount::where('account_number', 'ACC-123456789')->first();
        $this->assertNotNull($bankAccount);
        $this->assertTrue($bankAccount->is_active);

        // Toggle status
        $toggleResponse = $this->actingAs($this->accountant)
            ->patch(route('admin.finance.treasury.bank-accounts.toggle', $bankAccount));
        $toggleResponse->assertRedirect();
        $this->assertFalse($bankAccount->fresh()->is_active);
    }

    public function test_accountant_can_create_and_update_electronic_wallet(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('admin.finance.treasury.electronic-wallets.store'), [
            'code' => 'WLT-001',
            'provider_name' => 'فلوسك / الكريمي',
            'account_label' => 'محفظة المبيعات اليومية',
            'account_identifier' => '777000111',
            'currency_ids' => [$this->currency->id],
            'gl_account_id' => $this->walletGlAccount->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.finance.treasury.electronic-wallets.index'));
        $wallet = ElectronicWalletAccount::where('account_identifier', '777000111')->first();
        $this->assertNotNull($wallet);

        $updateResponse = $this->actingAs($this->accountant)->put(route('admin.finance.treasury.electronic-wallets.update', $wallet), [
            'code' => 'WLT-001',
            'provider_name' => 'فلوسك / الكريمي',
            'account_label' => 'محفظة المبيعات - محدث',
            'account_identifier' => '777000111',
            'currency_ids' => [$this->currency->id],
            'gl_account_id' => $this->walletGlAccount->id,
            'is_active' => 1,
        ]);

        $updateResponse->assertRedirect(route('admin.finance.treasury.electronic-wallets.index'));
        $this->assertEquals('محفظة المبيعات - محدث', $wallet->fresh()->account_label);
    }

    public function test_unauthorized_user_cannot_access_treasury_accounts(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.finance.treasury.cashboxes.index'))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.finance.treasury.bank-accounts.index'))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.finance.treasury.electronic-wallets.index'))
            ->assertForbidden();
    }
}
