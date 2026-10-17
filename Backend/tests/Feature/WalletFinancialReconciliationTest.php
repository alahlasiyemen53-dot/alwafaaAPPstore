<?php

namespace Tests\Feature;

use App\Actions\CreditCustomerWalletAction;
use App\Actions\DebitCustomerWalletAction;
use App\Actions\RefundToCustomerWalletAction;
use App\Actions\ReverseCustomerWalletEntryAction;
use App\DTOs\CustomerWalletAdjustmentData;
use App\DTOs\CustomerWalletRefundData;
use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\ReturnReasonCode;
use App\Enums\SalesReturnStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerWalletAccount;
use App\Models\JournalEntryLine;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerWalletBalanceQuery;
use App\Support\Decimal;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WalletFinancialReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Currency $currency;

    private Warehouse $warehouse;

    private AccountingPeriod $openPeriod;

    private CustomerWalletBalanceQuery $walletBalanceQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        $this->accountant = User::create([
            'name' => 'المدقق المالي',
            'email' => 'auditor@lamma.test',
            'password' => bcrypt('password123'),
        ]);

        $this->currency = Currency::where('is_default', true)->firstOrFail();

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع صنعاء الرئيسي',
            'name_en' => 'Sanaa Central WH',
            'code' => 'WH-SANAA-C',
            'is_active' => true,
        ]);

        $this->openPeriod = AccountingPeriod::create([
            'name' => 'فترة سبتمبر 2026',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);

        $this->walletBalanceQuery = new CustomerWalletBalanceQuery;
    }

    public function test_customer_wallets_system_wide_balance_strictly_reconciles_with_gl_account_2120(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $debitAction = app(DebitCustomerWalletAction::class);
        $refundAction = app(RefundToCustomerWalletAction::class);
        $reverseAction = app(ReverseCustomerWalletEntryAction::class);

        // Create 3 distinct customers
        $customerA = Customer::create(['name' => 'عميل أ', 'phone' => '770000001', 'normalized_phone' => '770000001', 'is_active' => true]);
        $customerB = Customer::create(['name' => 'عميل ب', 'phone' => '770000002', 'normalized_phone' => '770000002', 'is_active' => true]);
        $customerC = Customer::create(['name' => 'عميل ج', 'phone' => '770000003', 'normalized_phone' => '770000003', 'is_active' => true]);

        // Customer A: Credit 50,000, Debit 15,000 -> net 35,000
        $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $customerA->id,
            currencyId: $this->currency->id,
            amount: '50000.0000',
            type: 'credit',
            reason: 'رصيد أ',
            createdBy: $this->accountant->id,
        ));
        $debitAction->execute(new CustomerWalletAdjustmentData(
            customerId: $customerA->id,
            currencyId: $this->currency->id,
            amount: '15000.0000',
            type: 'debit',
            reason: 'خصم أ',
            createdBy: $this->accountant->id,
        ));

        // Customer B: Credit 20,000, Reverse 20,000 -> net 0
        $entryB = $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $customerB->id,
            currencyId: $this->currency->id,
            amount: '20000.0000',
            type: 'credit',
            reason: 'شحن ب مراد عكسه',
            createdBy: $this->accountant->id,
        ));
        $reverseAction->execute($entryB, 'عكس شحن ب', $this->accountant->id);

        // Customer C: Order + Sales return refund 18,000 -> net 18,000
        $orderC = Order::create([
            'order_number' => 'ORD-REC-001',
            'customer_id' => $customerC->id,
            'customer_name_snapshot' => $customerC->name,
            'customer_phone_snapshot' => $customerC->phone,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'subtotal' => '18000.0000',
            'total' => '18000.0000',
            'base_currency_subtotal' => '18000.0000',
            'base_currency_total' => '18000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);
        $returnC = SalesReturn::create([
            'return_number' => 'SR-REC-001',
            'order_id' => $orderC->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => SalesReturnStatus::Posted,
            'reason_code' => ReturnReasonCode::DamagedItem,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => '1.00000000',
            'subtotal' => '18000.0000',
            'total' => '18000.0000',
            'base_currency_total' => '18000.0000',
            'posted_at' => now(),
            'posted_by' => $this->accountant->id,
        ]);
        $refundAction->execute(new CustomerWalletRefundData(
            salesReturnId: $returnC->id,
            reason: 'استرداد مرتجع ج',
            refundedBy: $this->accountant->id,
        ));

        // 1. Calculate sum of all customer wallets
        $allAccounts = CustomerWalletAccount::where('currency_id', $this->currency->id)->get();
        $totalWalletsBalance = '0.0000';
        foreach ($allAccounts as $acc) {
            $bal = $this->walletBalanceQuery->getBalance($acc);
            $totalWalletsBalance = Decimal::add($totalWalletsBalance, $bal);
        }

        // Expected total: 35,000 + 0 + 18,000 = 53,000.0000
        self::assertSame('53000.0000', $totalWalletsBalance);

        // 2. Query General Ledger Account 2120 (محافظ العملاء)
        $glAccount = Account::where('code', '2120')->firstOrFail();

        $postedLines = JournalEntryLine::where('account_id', $glAccount->id)
            ->whereHas('journalEntry', function ($q) {
                $q->whereIn('status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value]);
            })
            ->get();

        $glCredit = '0.0000';
        $glDebit = '0.0000';
        foreach ($postedLines as $line) {
            $glCredit = Decimal::add($glCredit, Decimal::normalize((string) $line->credit));
            $glDebit = Decimal::add($glDebit, Decimal::normalize((string) $line->debit));
        }

        // For Liability account: Net Balance = Credit - Debit
        $glNetLiabilityBalance = Decimal::sub($glCredit, $glDebit);

        // 3. Strict financial reconciliation assert
        self::assertSame(
            $totalWalletsBalance,
            $glNetLiabilityBalance,
            "Reconciliation mismatch! Wallets balance ({$totalWalletsBalance}) does not match GL Account 2120 ({$glNetLiabilityBalance})"
        );
    }

    public function test_all_wallet_operations_generate_detailed_audit_logs(): void
    {
        $creditAction = app(CreditCustomerWalletAction::class);
        $debitAction = app(DebitCustomerWalletAction::class);
        $reverseAction = app(ReverseCustomerWalletEntryAction::class);

        $customer = Customer::create(['name' => 'عميل تدقيق', 'phone' => '770000099', 'normalized_phone' => '770000099', 'is_active' => true]);

        // Credit
        $creditEntry = $creditAction->execute(new CustomerWalletAdjustmentData(
            customerId: $customer->id,
            currencyId: $this->currency->id,
            amount: '12000.0000',
            type: 'credit',
            reason: 'إيداع تدقيق',
            createdBy: $this->accountant->id,
        ));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.customer_wallet.credited',
            'subject_type' => $creditEntry->getMorphClass(),
            'subject_id' => $creditEntry->id,
        ]);

        // Debit
        $debitEntry = $debitAction->execute(new CustomerWalletAdjustmentData(
            customerId: $customer->id,
            currencyId: $this->currency->id,
            amount: '3000.0000',
            type: 'debit',
            reason: 'خصم تدقيق',
            createdBy: $this->accountant->id,
        ));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.customer_wallet.debited',
            'subject_type' => $debitEntry->getMorphClass(),
            'subject_id' => $debitEntry->id,
        ]);

        // Reverse
        $reversalEntry = $reverseAction->execute($debitEntry, 'عكس تدقيق', $this->accountant->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.customer_wallet.reversed',
            'subject_type' => $reversalEntry->getMorphClass(),
            'subject_id' => $reversalEntry->id,
        ]);
    }
}
