<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\PostOnlineWalletPaymentAction;
use App\Enums\AccountNormalBalance;
use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\TreasuryTransactionStatus;
use App\Enums\TreasuryTransactionType;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPaymentMethod;
use App\Models\ElectronicWalletAccount;
use App\Models\Order;
use App\Models\TreasuryTransaction;
use App\Models\Warehouse;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CustomerPaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OnlineWalletPaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private ElectronicWalletAccount $jaibWallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingMappingSeeder::class);
        $this->seed(CustomerPaymentMethodSeeder::class);

        $this->currency = Currency::query()->create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        AccountingPeriod::query()->create([
            'name' => 'فترة قيود المحفظة',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);

        $gl = Account::query()->create([
            'code' => '1188',
            'name_ar' => 'محفظة جيب محاسبة',
            'name_en' => 'Jaib GL',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->jaibWallet = ElectronicWalletAccount::query()->create([
            'code' => 'WLT-JAIB-ACC',
            'provider_name' => 'Jaib',
            'account_label' => 'محفظة جيب',
            'account_identifier' => 'jaib-acc',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $gl->id,
            'is_active' => true,
        ]);

        CustomerPaymentMethod::query()->where('code', 'jaib')->update([
            'electronic_wallet_account_id' => $this->jaibWallet->id,
        ]);
    }

    public function test_posting_paid_jaib_order_creates_wallet_receipt_once(): void
    {
        $order = $this->makePaidOrder('jaib', '5500.0000');

        $action = app(PostOnlineWalletPaymentAction::class);
        $first = $action->execute($order);
        $second = $action->execute($order->fresh());

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, TreasuryTransaction::query()->count());
        $this->assertSame(TreasuryTransactionType::WalletReceipt, $first->transaction_type);
        $this->assertSame(TreasuryTransactionStatus::Posted, $first->status);
        $this->assertSame(ElectronicWalletAccount::class, $first->account_type);
        $this->assertSame($this->jaibWallet->id, $first->account_id);
        $this->assertSame($order->fresh()->treasury_transaction_id, $first->id);

        $clearingId = Account::query()->where('code', '1170')->value('id');
        $this->assertSame((int) $clearingId, (int) $first->offset_account_id);
    }

    public function test_cod_order_does_not_post_wallet_receipt(): void
    {
        $order = $this->makePaidOrder('cod', '3000.0000', OrderPaymentStatus::Unpaid);

        $result = app(PostOnlineWalletPaymentAction::class)->execute($order);

        $this->assertNull($result);
        $this->assertSame(0, TreasuryTransaction::query()->count());
        $this->assertNull($order->fresh()->treasury_transaction_id);
    }

    private function makePaidOrder(
        string $paymentMethodCode,
        string $total,
        OrderPaymentStatus $paymentStatus = OrderPaymentStatus::Paid,
    ): Order {
        $customer = Customer::query()->create([
            'name' => 'عميل محفظة',
            'phone' => '771111111',
            'normalized_phone' => '771111111',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'name_ar' => 'مستودع محاسبة',
            'code' => 'WH-ACC-WLT',
            'is_active' => true,
        ]);

        return Order::query()->create([
            'order_number' => 'ORD-WLT-'.uniqid(),
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Confirmed,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'subtotal' => $total,
            'discount_amount' => '0.0000',
            'delivery_fee' => '0.0000',
            'promotion_discount_total' => '0.0000',
            'coupon_discount_total' => '0.0000',
            'bundle_discount_total' => '0.0000',
            'total' => $total,
            'exchange_rate_snapshot' => '1.00000000',
            'base_currency_total' => $total,
            'base_currency_delivery_fee' => '0.0000',
            'payment_method_code' => $paymentMethodCode,
            'payment_status' => $paymentStatus,
            'payment_provider_reference' => 'FAKE-REF-TEST',
            'confirmed_at' => now(),
        ]);
    }
}
