<?php

namespace Database\Seeders;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

final class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // 1000 - الأصول
            [
                'code' => '1000',
                'name_ar' => 'الأصول',
                'name_en' => 'Assets',
                'type' => AccountType::Asset,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => false,
                'parent_code' => null,
            ],
            [
                'code' => '1100',
                'name_ar' => 'الأصول المتداولة',
                'name_en' => 'Current Assets',
                'type' => AccountType::Asset,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => false,
                'parent_code' => '1000',
            ],
            [
                'code' => '1110',
                'name_ar' => 'النقد وما في حكمه',
                'name_en' => 'Cash & Cash Equivalents',
                'type' => AccountType::Asset,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'parent_code' => '1100',
            ],
            [
                'code' => '1120',
                'name_ar' => 'البنوك',
                'name_en' => 'Banks',
                'type' => AccountType::Asset,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'parent_code' => '1100',
            ],
            [
                'code' => '1130',
                'name_ar' => 'المدينون والعملاء',
                'name_en' => 'Accounts Receivable',
                'type' => AccountType::Asset,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'parent_code' => '1100',
            ],
            [
                'code' => '1140',
                'name_ar' => 'بضاعة المخزون والمشتريات',
                'name_en' => 'Inventory & Purchases Clearing',
                'type' => AccountType::Asset,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'parent_code' => '1100',
            ],
            [
                'code' => '1160',
                'name_ar' => 'وسيط تحصيل عهد المناديب (الدفع عند الاستلام)',
                'name_en' => 'Driver COD Clearing Account',
                'type' => AccountType::Asset,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'parent_code' => '1100',
            ],
            [
                'code' => '1170',
                'name_ar' => 'وسيط قبض المحافظ الإلكترونية',
                'name_en' => 'Online Wallet Receipts Clearing',
                'type' => AccountType::Asset,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'parent_code' => '1100',
            ],

            // 2000 - الالتزامات
            [
                'code' => '2000',
                'name_ar' => 'الالتزامات',
                'name_en' => 'Liabilities',
                'type' => AccountType::Liability,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => false,
                'parent_code' => null,
            ],
            [
                'code' => '2100',
                'name_ar' => 'الالتزامات المتداولة',
                'name_en' => 'Current Liabilities',
                'type' => AccountType::Liability,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => false,
                'parent_code' => '2000',
            ],
            [
                'code' => '2110',
                'name_ar' => 'الموردون والذمم الدائنة',
                'name_en' => 'Accounts Payable',
                'type' => AccountType::Liability,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => true,
                'parent_code' => '2100',
            ],
            [
                'code' => '2120',
                'name_ar' => 'محافظ وأرصدة العملاء الدائنة (التزامات)',
                'name_en' => 'Customer Wallets Liability',
                'type' => AccountType::Liability,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => true,
                'parent_code' => '2100',
            ],

            // 3000 - حقوق الملكية
            [
                'code' => '3000',
                'name_ar' => 'حقوق الملكية',
                'name_en' => 'Equity',
                'type' => AccountType::Equity,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => false,
                'parent_code' => null,
            ],
            [
                'code' => '3100',
                'name_ar' => 'رأس المال',
                'name_en' => 'Capital',
                'type' => AccountType::Equity,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => true,
                'parent_code' => '3000',
            ],
            [
                'code' => '3200',
                'name_ar' => 'الأرباح المبقاة',
                'name_en' => 'Retained Earnings',
                'type' => AccountType::Equity,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => true,
                'parent_code' => '3000',
            ],
            [
                'code' => '3300',
                'name_ar' => 'الأرصدة الافتتاحية',
                'name_en' => 'Opening Balance Equity',
                'type' => AccountType::Equity,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => true,
                'parent_code' => '3000',
            ],

            // 4000 - الإيرادات
            [
                'code' => '4000',
                'name_ar' => 'الإيرادات',
                'name_en' => 'Revenue',
                'type' => AccountType::Revenue,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => false,
                'parent_code' => null,
            ],
            [
                'code' => '4100',
                'name_ar' => 'إيرادات المبيعات',
                'name_en' => 'Sales Revenue',
                'type' => AccountType::Revenue,
                'normal_balance' => AccountNormalBalance::Credit,
                'is_postable' => true,
                'parent_code' => '4000',
            ],

            // 5000 - المصروفات
            [
                'code' => '5000',
                'name_ar' => 'المصروفات',
                'name_en' => 'Expenses',
                'type' => AccountType::Expense,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => false,
                'parent_code' => null,
            ],
            [
                'code' => '5100',
                'name_ar' => 'تكلفة البضاعة المباعة',
                'name_en' => 'Cost of Goods Sold',
                'type' => AccountType::Expense,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'parent_code' => '5000',
            ],
            [
                'code' => '5200',
                'name_ar' => 'المصروفات العمومية والإدارية',
                'name_en' => 'General & Administrative Expenses',
                'type' => AccountType::Expense,
                'normal_balance' => AccountNormalBalance::Debit,
                'is_postable' => true,
                'parent_code' => '5000',
            ],
        ];

        foreach ($accounts as $data) {
            $parentId = null;
            if (! empty($data['parent_code'])) {
                $parent = Account::where('code', $data['parent_code'])->first();
                $parentId = $parent?->id;
            }

            Account::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name_ar' => $data['name_ar'],
                    'name_en' => $data['name_en'],
                    'parent_id' => $parentId,
                    'type' => $data['type'],
                    'normal_balance' => $data['normal_balance'],
                    'is_postable' => $data['is_postable'],
                    'is_active' => true,
                ]
            );
        }
    }
}
