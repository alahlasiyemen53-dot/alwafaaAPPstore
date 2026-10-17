<?php

namespace Tests\Feature;

use App\Enums\CustomerWalletAccountStatus;
use App\Enums\CustomerWalletEntryType;
use App\Enums\LoyaltyAccountStatus;
use App\Enums\LoyaltyEntryType;
use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Models\CustomerWalletAccount;
use App\Models\CustomerWalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LoyaltyAndWalletSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_loyalty_and_wallet_tables_exist_and_have_approved_structure(): void
    {
        self::assertTrue(Schema::hasTable('customer_loyalty_accounts'));
        self::assertTrue(Schema::hasTable('customer_loyalty_entries'));
        self::assertTrue(Schema::hasTable('customer_loyalty_reservations'));
        self::assertTrue(Schema::hasTable('customer_wallet_accounts'));
        self::assertTrue(Schema::hasTable('customer_wallet_entries'));

        // INVARIANT: No mutable balance columns allowed
        self::assertFalse(Schema::hasColumn('customer_loyalty_accounts', 'balance'));
        self::assertFalse(Schema::hasColumn('customer_loyalty_accounts', 'points_balance'));
        self::assertFalse(Schema::hasColumn('customer_wallet_accounts', 'balance'));
        self::assertFalse(Schema::hasColumn('customer_wallet_accounts', 'wallet_balance'));
        self::assertFalse(Schema::hasColumn('customers', 'points_balance'));
        self::assertFalse(Schema::hasColumn('customers', 'wallet_balance'));
    }

    public function test_models_and_relationships_work_properly(): void
    {
        $customer = Customer::create([
            'name' => 'محمد عبدالله',
            'phone' => '771234567',
            'normalized_phone' => '771234567',
            'is_active' => true,
        ]);
        $currency = Currency::where('code', 'YER')->first() ?? Currency::create([
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'code' => 'YER',
            'symbol' => 'ر.ي',
            'is_base' => true,
            'is_active' => true,
        ]);

        // 1. Loyalty Account & Entry
        $loyaltyAccount = CustomerLoyaltyAccount::create([
            'customer_id' => $customer->id,
            'status' => LoyaltyAccountStatus::Active,
        ]);

        self::assertTrue($loyaltyAccount->customer->is($customer));
        self::assertTrue($customer->loyaltyAccount->is($loyaltyAccount));

        $entry = CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $loyaltyAccount->id,
            'entry_number' => 'LOY-0001',
            'type' => LoyaltyEntryType::Earn,
            'points' => 100,
            'source_type' => 'Order',
            'source_id' => 1,
            'source_event' => 'DELIVERY_DELIVERED',
            'description' => 'نقاط مكتسبة من الطلب',
            'occurred_at' => now(),
        ]);

        self::assertSame(100, $entry->points);
        self::assertSame(LoyaltyEntryType::Earn, $entry->type);
        self::assertTrue($loyaltyAccount->entries->contains($entry));

        // 2. Customer Wallet Account & Entry
        $walletAccount = CustomerWalletAccount::create([
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'status' => CustomerWalletAccountStatus::Active,
        ]);

        self::assertTrue($walletAccount->customer->is($customer));
        self::assertTrue($walletAccount->currency->is($currency));
        self::assertTrue($customer->walletAccounts->contains($walletAccount));

        $walletEntry = CustomerWalletEntry::create([
            'customer_wallet_account_id' => $walletAccount->id,
            'entry_number' => 'WAL-0001',
            'type' => CustomerWalletEntryType::ManualCredit,
            'amount' => '5000.0000',
            'currency_id' => $currency->id,
            'source_type' => 'ManualAdjustment',
            'source_id' => 1,
            'source_event' => 'ADMIN_ADJUSTMENT',
            'description' => 'شحن يدوي تجريبي',
            'posted_at' => now(),
        ]);

        self::assertSame('5000.0000', $walletEntry->amount);
        self::assertSame(CustomerWalletEntryType::ManualCredit, $walletEntry->type);
        self::assertTrue($walletAccount->entries->contains($walletEntry));
    }

    public function test_customer_wallet_liability_account_and_mappings_are_seeded(): void
    {
        $liabilityAccount = Account::where('code', '2120')->first();
        self::assertNotNull($liabilityAccount, 'Account 2120 must exist for customer wallets liability.');
        self::assertSame('محافظ وأرصدة العملاء الدائنة (التزامات)', $liabilityAccount->name_ar);

        $mapping = AccountingMapping::where('event_key', 'CUSTOMER_WALLET_REFUND_CREDIT')->first();
        self::assertNotNull($mapping, 'AccountingMapping CUSTOMER_WALLET_REFUND_CREDIT must exist.');
        self::assertSame($liabilityAccount->id, $mapping->credit_account_id);
    }
}
