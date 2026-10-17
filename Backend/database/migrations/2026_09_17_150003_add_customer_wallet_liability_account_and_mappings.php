<?php

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\AccountingMapping;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $parentAccount = Account::where('code', '2100')->first();

        $liabilityAccount = Account::firstOrCreate(
            ['code' => '2120'],
            [
                'name_ar' => 'محافظ وأرصدة العملاء الدائنة (التزامات)',
                'name_en' => 'Customer Wallets Liability',
                'parent_id' => $parentAccount?->id,
                'type' => AccountType::Liability,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => true,
                'is_active' => true,
            ]
        );

        $returnClearingAccount = Account::where('code', '1140')->first() ?? Account::firstOrCreate(
            ['code' => '1140'],
            [
                'name_ar' => 'بضاعة المخزون والمشتريات',
                'name_en' => 'Inventory & Purchases Clearing',
                'type' => AccountType::Asset,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'is_active' => true,
            ]
        );

        $expenseAccount = Account::where('code', '5200')->first() ?? Account::firstOrCreate(
            ['code' => '5200'],
            [
                'name_ar' => 'المصروفات العمومية والإدارية',
                'name_en' => 'General & Administrative Expenses',
                'type' => AccountType::Expense,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'is_active' => true,
            ]
        );

        $revenueAccount = Account::where('code', '4100')->first() ?? Account::firstOrCreate(
            ['code' => '4100'],
            [
                'name_ar' => 'إيرادات المبيعات',
                'name_en' => 'Sales Revenue',
                'type' => AccountType::Revenue,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => true,
                'is_active' => true,
            ]
        );

        AccountingMapping::firstOrCreate(
            ['event_key' => 'CUSTOMER_WALLET_REFUND_CREDIT'],
            [
                'name_ar' => 'استرداد قيمة المرتجع إلى محفظة العميل',
                'debit_account_id' => $returnClearingAccount->id,
                'credit_account_id' => $liabilityAccount->id,
                'is_active' => true,
                'description' => 'إثبات التزام الرصيد الدائن بمحفظة العميل مقابل وسيط المرتجعات والمخزون',
            ]
        );

        AccountingMapping::firstOrCreate(
            ['event_key' => 'CUSTOMER_WALLET_MANUAL_CREDIT'],
            [
                'name_ar' => 'شحن تسوية يدوية دائنة لمحفظة العميل',
                'debit_account_id' => $expenseAccount->id,
                'credit_account_id' => $liabilityAccount->id,
                'is_active' => true,
                'description' => 'إثبات رصيد دائن بمحفظة العميل كتسوية إدارية',
            ]
        );

        AccountingMapping::firstOrCreate(
            ['event_key' => 'CUSTOMER_WALLET_MANUAL_DEBIT'],
            [
                'name_ar' => 'خصم تسوية يدوية مدينة من محفظة العميل',
                'debit_account_id' => $liabilityAccount->id,
                'credit_account_id' => $revenueAccount->id,
                'is_active' => true,
                'description' => 'تخفيض التزام رصيد محفظة العميل كتسوية إدارية',
            ]
        );
    }

    public function down(): void
    {
        AccountingMapping::whereIn('event_key', [
            'CUSTOMER_WALLET_REFUND_CREDIT',
            'CUSTOMER_WALLET_MANUAL_CREDIT',
            'CUSTOMER_WALLET_MANUAL_DEBIT',
        ])->delete();

        Account::where('code', '2120')->delete();
    }
};
