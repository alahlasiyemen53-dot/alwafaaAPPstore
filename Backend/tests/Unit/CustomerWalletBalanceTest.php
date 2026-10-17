<?php

namespace Tests\Unit;

use App\Enums\CustomerWalletEntryType;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerWalletAccount;
use App\Models\CustomerWalletEntry;
use App\Services\CustomerWalletBalanceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerWalletBalanceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerWalletBalanceQuery $balanceQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->balanceQuery = new CustomerWalletBalanceQuery;
    }

    public function test_derives_correct_wallet_balance_with_currency_isolation(): void
    {
        $customer = Customer::create([
            'name' => 'تاجر التجزئة',
            'phone' => '771122334',
            'normalized_phone' => '771122334',
        ]);

        $yer = Currency::create([
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'code' => 'YER',
            'symbol' => 'ر.ي',
            'is_base' => true,
            'is_active' => true,
        ]);

        $sar = Currency::create([
            'name_ar' => 'ريال سعودي',
            'name_en' => 'Saudi Riyal',
            'code' => 'SAR',
            'symbol' => 'ر.س',
            'exchange_rate' => 0.0038,
            'is_base' => false,
            'is_active' => true,
        ]);

        // YER Account: Credit 10,000, Debit 3,000 => Balance 7,000.0000
        $yerAccount = CustomerWalletAccount::create([
            'customer_id' => $customer->id,
            'currency_id' => $yer->id,
        ]);

        CustomerWalletEntry::create([
            'customer_wallet_account_id' => $yerAccount->id,
            'entry_number' => 'WAL-001',
            'type' => CustomerWalletEntryType::ManualCredit,
            'amount' => '10000.0000',
            'currency_id' => $yer->id,
            'source_type' => 'ManualAdjustment',
            'source_id' => 1,
            'source_event' => 'ADMIN_CREDIT',
            'posted_at' => now(),
        ]);

        CustomerWalletEntry::create([
            'customer_wallet_account_id' => $yerAccount->id,
            'entry_number' => 'WAL-002',
            'type' => CustomerWalletEntryType::ManualDebit,
            'amount' => '3000.0000',
            'currency_id' => $yer->id,
            'source_type' => 'ManualAdjustment',
            'source_id' => 1,
            'source_event' => 'ADMIN_DEBIT',
            'posted_at' => now(),
        ]);

        // SAR Account: Credit 500 => Balance 500.0000
        $sarAccount = CustomerWalletAccount::create([
            'customer_id' => $customer->id,
            'currency_id' => $sar->id,
        ]);

        CustomerWalletEntry::create([
            'customer_wallet_account_id' => $sarAccount->id,
            'entry_number' => 'WAL-003',
            'type' => CustomerWalletEntryType::ManualCredit,
            'amount' => '500.0000',
            'currency_id' => $sar->id,
            'source_type' => 'ManualAdjustment',
            'source_id' => 1,
            'source_event' => 'ADMIN_CREDIT',
            'posted_at' => now(),
        ]);

        self::assertSame('7000.0000', $this->balanceQuery->getBalance($yerAccount));
        self::assertSame('500.0000', $this->balanceQuery->getBalance($sarAccount));
    }
}
