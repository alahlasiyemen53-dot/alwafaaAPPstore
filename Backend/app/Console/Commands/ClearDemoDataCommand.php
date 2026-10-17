<?php

namespace App\Console\Commands;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:clear {--force : Force execute without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete demo/test data (products, variants, purchases, suppliers, brands, categories, demo users) from the database while preserving super admin and core master data.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('هل أنت متأكد من رغبتك في حذف كافة البيانات التجريبية من قاعدة البيانات؟')) {
            $this->warn('تم إلغاء العملية.');
            return self::SUCCESS;
        }

        $this->info('جارٍ حذف البيانات التجريبية...');

        $tablesToTruncate = [
            'variant_prices',
            'product_attribute_values',
            'variant_media',
            'product_media',
            'inventory_transactions',
            'inventory_levels',
            'purchase_invoice_items',
            'purchase_invoices',
            'purchase_receipt_items',
            'purchase_receipts',
            'supplier_ledger_entries',
            'suppliers',
            'cart_items',
            'wishlists',
            'reviews',
            'product_variants',
            'products',
            'attribute_values',
            'attributes',
            'categories',
            'brands',
        ];

        Schema::disableForeignKeyConstraints();

        foreach ($tablesToTruncate as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line(" - تم تفريغ جدول {$table} (حُذف {$count} سجل)");
            }
        }

        // Delete demo staff users while keeping super admin
        if (Schema::hasTable('users')) {
            $deletedUsers = DB::table('users')
                ->where('email', '!=', 'alahlasiyemen53@gmail.com')
                ->delete();
            $this->line(" - تم حذف المستخدمين التجريبيين ({$deletedUsers} مستخدم)");
        }

        Schema::enableForeignKeyConstraints();

        // Ensure super admin user exists and is properly seeded
        $this->call(AdminUserSeeder::class);

        $this->info('✓ تم حذف كافة البيانات التجريبية بنجاح والحفاظ على حساب مدير النظام والبيانات الأساسية.');

        return self::SUCCESS;
    }
}
