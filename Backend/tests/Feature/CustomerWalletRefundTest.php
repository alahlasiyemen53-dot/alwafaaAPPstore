<?php

namespace Tests\Feature;

use App\Actions\RefundToCustomerWalletAction;
use App\DTOs\CustomerWalletRefundData;
use App\Enums\AccountingPeriodStatus;
use App\Enums\CustomerWalletEntryType;
use App\Enums\JournalEntryStatus;
use App\Enums\OrderStatus;
use App\Enums\ReturnReasonCode;
use App\Enums\SalesReturnStatus;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerWalletAccount;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerWalletBalanceQuery;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerWalletRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Currency $currency;

    private Warehouse $warehouse;

    private Order $order;

    private AccountingPeriod $openPeriod;

    private CustomerWalletBalanceQuery $balanceQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        $this->admin = User::create([
            'name' => 'المشرف العام',
            'email' => 'admin@lamma.test',
            'password' => bcrypt('password123'),
        ]);

        $this->customer = Customer::create([
            'name' => 'سعيد باحاج',
            'phone' => '772233445',
            'normalized_phone' => '772233445',
            'is_active' => true,
        ]);

        $this->currency = Currency::where('is_default', true)->firstOrFail();

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع صنعاء',
            'name_en' => 'Sanaa Warehouse',
            'code' => 'WH-SANAA',
            'is_active' => true,
        ]);

        $this->openPeriod = AccountingPeriod::create([
            'name' => 'فترة سبتمبر 2026',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-REF-001',
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Delivered,
            'subtotal' => '25000.0000',
            'total' => '25000.0000',
            'base_currency_subtotal' => '25000.0000',
            'base_currency_total' => '25000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);

        $this->balanceQuery = new CustomerWalletBalanceQuery;
    }

    public function test_posted_sales_return_can_be_refunded_to_customer_wallet(): void
    {
        $salesReturn = SalesReturn::create([
            'return_number' => 'SR-001',
            'order_id' => $this->order->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => SalesReturnStatus::Posted,
            'reason_code' => ReturnReasonCode::DamagedItem,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => '1.00000000',
            'subtotal' => '25000.0000',
            'total' => '25000.0000',
            'base_currency_total' => '25000.0000',
            'posted_at' => now(),
            'posted_by' => $this->admin->id,
        ]);

        $action = app(RefundToCustomerWalletAction::class);

        $dto = new CustomerWalletRefundData(
            salesReturnId: $salesReturn->id,
            reason: 'استرداد قيمة مرتجع تالف إلى المحفظة',
            refundedBy: $this->admin->id,
        );

        $entry = $action->execute($dto);

        self::assertSame(CustomerWalletEntryType::RefundCredit, $entry->type);
        self::assertSame('25000.0000', $entry->amount);
        self::assertSame('SalesReturn', $entry->source_type);
        self::assertSame($salesReturn->id, $entry->source_id);
        self::assertSame('SALES_RETURN_REFUND', $entry->source_event);

        $journal = $entry->journalEntry;
        self::assertNotNull($journal);
        self::assertSame(JournalEntryStatus::Posted, $journal->status);
        self::assertSame('25000.0000', $journal->total_debit);
        self::assertSame('25000.0000', $journal->total_credit);

        $walletAccount = CustomerWalletAccount::where('customer_id', $this->customer->id)
            ->where('currency_id', $this->currency->id)
            ->firstOrFail();

        self::assertSame('25000.0000', $this->balanceQuery->getBalance($walletAccount));
    }

    public function test_draft_sales_return_cannot_be_refunded_to_wallet(): void
    {
        $salesReturn = SalesReturn::create([
            'return_number' => 'SR-002',
            'order_id' => $this->order->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => SalesReturnStatus::Draft,
            'reason_code' => ReturnReasonCode::DamagedItem,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => '1.00000000',
            'subtotal' => '10000.0000',
            'total' => '10000.0000',
            'base_currency_total' => '10000.0000',
        ]);

        $action = app(RefundToCustomerWalletAction::class);

        $this->expectException(DomainException::class);

        $action->execute(new CustomerWalletRefundData(
            salesReturnId: $salesReturn->id,
            reason: 'محاولة رد مرتجع مسودة',
            refundedBy: $this->admin->id,
        ));
    }

    public function test_already_refunded_sales_return_cannot_be_refunded_again(): void
    {
        $salesReturn = SalesReturn::create([
            'return_number' => 'SR-003',
            'order_id' => $this->order->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => SalesReturnStatus::Posted,
            'reason_code' => ReturnReasonCode::CustomerChangedMind,
            'currency_id' => $this->currency->id,
            'exchange_rate_snapshot' => '1.00000000',
            'subtotal' => '15000.0000',
            'total' => '15000.0000',
            'base_currency_total' => '15000.0000',
            'posted_at' => now(),
            'posted_by' => $this->admin->id,
        ]);

        $action = app(RefundToCustomerWalletAction::class);

        $dto = new CustomerWalletRefundData(
            salesReturnId: $salesReturn->id,
            reason: 'استرداد أول',
            refundedBy: $this->admin->id,
        );

        $action->execute($dto);

        // Second attempt must fail
        $this->expectException(DomainException::class);

        $action->execute(new CustomerWalletRefundData(
            salesReturnId: $salesReturn->id,
            reason: 'استرداد ثانٍ لنفس المرتجع',
            refundedBy: $this->admin->id,
        ));
    }
}
