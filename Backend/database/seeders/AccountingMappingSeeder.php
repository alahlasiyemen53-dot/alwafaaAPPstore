<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountingMapping;
use Illuminate\Database\Seeder;

final class AccountingMappingSeeder extends Seeder
{
    public function run(): void
    {
        $inventoryAccount = Account::where('code', '1140')->first();
        $payableAccount = Account::where('code', '2110')->first();

        if ($inventoryAccount && $payableAccount) {
            AccountingMapping::firstOrCreate(
                ['event_key' => 'PURCHASE_INVOICE_POSTED'],
                [
                    'name_ar' => 'استحقاق فواتير الشراء للموردين',
                    'debit_account_id' => $inventoryAccount->id,
                    'credit_account_id' => $payableAccount->id,
                    'is_active' => true,
                    'description' => 'إثبات مديونية الموردين في حساب الذمم الدائنة مقابل حجز/وسيط المشتريات والمخزون',
                ]
            );
        }

        $cashAccount = Account::where('code', '1110')->first();
        $codClearingAccount = Account::where('code', '1160')->first();

        if ($codClearingAccount) {
            AccountingMapping::firstOrCreate(
                ['event_key' => 'DRIVER_COD_SETTLEMENT_POSTED'],
                [
                    'name_ar' => 'تسوية عهدة المندوب وإيداع النقدية بالخزينة',
                    'debit_account_id' => $cashAccount?->id ?? $codClearingAccount->id,
                    'credit_account_id' => $codClearingAccount->id,
                    'is_active' => true,
                    'description' => 'إثبات استلام النقدية في الخزينة/البنك/المحفظة وتخفيض عهدة المندوب في حساب وسيط التحصيل',
                ]
            );
        }

        $walletLiability = Account::where('code', '2120')->first();
        $inventoryClearing = Account::where('code', '1140')->first();
        $expenseAccount = Account::where('code', '5200')->first();
        $revenueAccount = Account::where('code', '4100')->first();

        if ($walletLiability && $inventoryClearing) {
            AccountingMapping::firstOrCreate(
                ['event_key' => 'CUSTOMER_WALLET_REFUND_CREDIT'],
                [
                    'name_ar' => 'استرداد قيمة المرتجع إلى محفظة العميل',
                    'debit_account_id' => $inventoryClearing->id,
                    'credit_account_id' => $walletLiability->id,
                    'is_active' => true,
                    'description' => 'إثبات التزام الرصيد الدائن بمحفظة العميل مقابل وسيط المرتجعات والمخزون',
                ]
            );
        }

        if ($walletLiability && $expenseAccount) {
            AccountingMapping::firstOrCreate(
                ['event_key' => 'CUSTOMER_WALLET_MANUAL_CREDIT'],
                [
                    'name_ar' => 'شحن تسوية يدوية دائنة لمحفظة العميل',
                    'debit_account_id' => $expenseAccount->id,
                    'credit_account_id' => $walletLiability->id,
                    'is_active' => true,
                    'description' => 'إثبات رصيد دائن بمحفظة العميل كتسوية إدارية',
                ]
            );
        }

        if ($walletLiability && $revenueAccount) {
            AccountingMapping::firstOrCreate(
                ['event_key' => 'CUSTOMER_WALLET_MANUAL_DEBIT'],
                [
                    'name_ar' => 'خصم تسوية يدوية مدينة من محفظة العميل',
                    'debit_account_id' => $walletLiability->id,
                    'credit_account_id' => $revenueAccount->id,
                    'is_active' => true,
                    'description' => 'تخفيض التزام رصيد محفظة العميل كتسوية إدارية',
                ]
            );
        }

        $onlineWalletClearing = Account::where('code', '1170')->first();
        $cashPlaceholder = Account::where('code', '1110')->first();

        if ($onlineWalletClearing) {
            // updateOrCreate: repair rows created before 1170 existed (null credit / inactive).
            AccountingMapping::updateOrCreate(
                ['event_key' => 'ONLINE_WALLET_PAYMENT_CAPTURED'],
                [
                    'name_ar' => 'قبض محفظة إلكترونية عند دفع طلب العميل',
                    'debit_account_id' => $cashPlaceholder?->id ?? $onlineWalletClearing->id,
                    'credit_account_id' => $onlineWalletClearing->id,
                    'is_active' => true,
                    'description' => 'إثبات دخول النقدية إلى محفظة الشركة الإلكترونية (المدين يُستبدل بحساب المحفظة الفعلي عند الترحيل)',
                ]
            );
        }
    }
}
