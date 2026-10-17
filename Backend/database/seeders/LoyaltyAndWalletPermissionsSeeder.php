<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class LoyaltyAndWalletPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'admin.loyalty.view' => 'عرض حسابات ولاء العملاء',
            'admin.loyalty.adjust' => 'إجراء تسوية يدوية لنقاط ولاء العملاء',
            'admin.loyalty.statement' => 'عرض كشف حساب نقاط ولاء العميل',
            'admin.customer_wallets.view' => 'عرض محافظ العملاء',
            'admin.customer_wallets.adjust' => 'إجراء تسوية يدوية لمحفظة العميل',
            'admin.customer_wallets.refund' => 'استرداد مرتجع مبيعات إلى محفظة العميل',
            'admin.customer_wallets.reverse' => 'عكس حركة في محفظة العميل',
            'admin.customer_wallets.statement' => 'عرض كشف حساب محفظة العميل',
        ];

        foreach ($permissions as $name => $displayName) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $displayName]
            );
        }

        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
        }

        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $existing = $admin->permissions()->pluck('name')->toArray();
            $merged = array_unique(array_merge($existing, array_keys($permissions)));
            $admin->syncPermissions(Permission::whereIn('name', $merged)->get());
        }

        $accountant = Role::where('name', 'accountant')->first();
        if ($accountant) {
            $existing = $accountant->permissions()->pluck('name')->toArray();
            $merged = array_unique(array_merge($existing, [
                'admin.loyalty.view',
                'admin.customer_wallets.view',
                'admin.customer_wallets.adjust',
                'admin.customer_wallets.refund',
                'admin.customer_wallets.reverse',
                'admin.customer_wallets.statement',
            ]));
            $accountant->syncPermissions(Permission::whereIn('name', $merged)->get());
        }

        $support = Role::where('name', 'customer_support')->first();
        if ($support) {
            $existing = $support->permissions()->pluck('name')->toArray();
            $merged = array_unique(array_merge($existing, [
                'admin.loyalty.view',
                'admin.loyalty.statement',
                'admin.customer_wallets.view',
                'admin.customer_wallets.statement',
            ]));
            $support->syncPermissions(Permission::whereIn('name', $merged)->get());
        }
    }
}
