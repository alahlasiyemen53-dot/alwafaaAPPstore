<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'admin.dashboard.view',
            'admin.users.view', 'admin.users.create', 'admin.users.update', 'admin.users.activate', 'admin.users.deactivate',
            'admin.roles.view', 'admin.roles.create', 'admin.roles.update', 'admin.roles.assign',
            'admin.permissions.view', 'admin.profile.view', 'admin.profile.update', 'admin.password.change',
            'admin.currencies.view', 'admin.currencies.create', 'admin.currencies.update', 'admin.currencies.activate', 'admin.currencies.deactivate',
            'admin.exchange_rates.view', 'admin.exchange_rates.create', 'admin.exchange_rates.update',
            'admin.units.view', 'admin.units.create', 'admin.units.update', 'admin.units.activate', 'admin.units.deactivate',
            'admin.locations.view', 'admin.locations.create', 'admin.locations.update', 'admin.locations.activate', 'admin.locations.deactivate',
            'admin.customer_payment_methods.view', 'admin.customer_payment_methods.create', 'admin.customer_payment_methods.update', 'admin.customer_payment_methods.activate', 'admin.customer_payment_methods.deactivate', 'admin.customer_payment_methods.media.upload', 'admin.customer_payment_methods.media.delete',
            'admin.brands.view', 'admin.brands.create', 'admin.brands.update', 'admin.brands.activate', 'admin.brands.deactivate',
            'admin.brands.media.upload', 'admin.brands.media.delete',
            'admin.categories.view', 'admin.categories.create', 'admin.categories.update', 'admin.categories.activate', 'admin.categories.deactivate',
            'admin.categories.media.upload', 'admin.categories.media.delete',
            'admin.products.view', 'admin.products.create', 'admin.products.update', 'admin.products.activate', 'admin.products.deactivate',
            'admin.products.media.view', 'admin.products.media.upload', 'admin.products.media.update', 'admin.products.media.delete',
            'admin.attributes.view', 'admin.attributes.create', 'admin.attributes.update', 'admin.attributes.activate', 'admin.attributes.deactivate',
            'admin.variants.view', 'admin.variants.create', 'admin.variants.update', 'admin.variants.activate', 'admin.variants.deactivate', 'admin.variants.set_default',
            'admin.variants.media.view', 'admin.variants.media.upload', 'admin.variants.media.update', 'admin.variants.media.delete',
            'admin.variant_prices.view', 'admin.variant_prices.update',
            'admin.warehouses.view', 'admin.warehouses.create', 'admin.warehouses.update', 'admin.warehouses.activate', 'admin.warehouses.deactivate',
            'admin.settings.view', 'admin.settings.update',
            'admin.suppliers.view', 'admin.suppliers.create', 'admin.suppliers.update', 'admin.suppliers.activate', 'admin.suppliers.deactivate', 'admin.suppliers.statement.view',
            'admin.purchases.view', 'admin.purchases.create', 'admin.purchases.update', 'admin.purchases.delete', 'admin.purchases.post',
            'admin.inventory.view',
            'admin.purchase_receipts.view', 'admin.purchase_receipts.create', 'admin.purchase_receipts.update', 'admin.purchase_receipts.delete', 'admin.purchase_receipts.post',
            'admin.stock_transfers.view', 'admin.stock_transfers.create', 'admin.stock_transfers.update', 'admin.stock_transfers.delete', 'admin.stock_transfers.post',
            'admin.stock_adjustments.view', 'admin.stock_adjustments.create', 'admin.stock_adjustments.update', 'admin.stock_adjustments.delete', 'admin.stock_adjustments.post',
            'admin.stock_movements.view',
            'admin.customers.view', 'admin.customers.create', 'admin.customers.update', 'admin.customers.activate', 'admin.customers.deactivate', 'admin.customers.verify_phone', 'admin.otp.view',
            'admin.customer_addresses.view', 'admin.customer_addresses.create', 'admin.customer_addresses.update', 'admin.customer_addresses.delete', 'admin.customer_addresses.set_default',
            'admin.customer_tiers.view', 'admin.customer_tiers.create', 'admin.customer_tiers.update', 'admin.customer_tiers.activate', 'admin.customer_tiers.deactivate', 'admin.customer_tiers.assign',
            'admin.wishlists.view',
            'admin.reviews.view', 'admin.reviews.moderate',
            'admin.orders.view', 'admin.orders.create', 'admin.orders.update', 'admin.orders.confirm', 'admin.orders.cancel',
            'admin.sales_returns.view', 'admin.sales_returns.create', 'admin.sales_returns.update', 'admin.sales_returns.post', 'admin.sales_returns.cancel',
            'admin.drivers.view', 'admin.drivers.create', 'admin.drivers.update', 'admin.drivers.activate', 'admin.drivers.deactivate',
            'admin.deliveries.view', 'admin.deliveries.assign', 'admin.deliveries.mark_delivered', 'admin.deliveries.mark_failed', 'admin.deliveries.return_to_warehouse',
            'admin.accounts.view', 'admin.accounts.create', 'admin.accounts.update', 'admin.accounts.toggle',
            'admin.accounting_periods.view', 'admin.accounting_periods.create', 'admin.accounting_periods.close',
            'admin.journal_entries.view', 'admin.journal_entries.create', 'admin.journal_entries.update', 'admin.journal_entries.post', 'admin.journal_entries.reverse',
            'admin.trial_balance.view', 'admin.general_ledger.view',
            'admin.cashboxes.view', 'admin.cashboxes.create', 'admin.cashboxes.update', 'admin.cashboxes.toggle',
            'admin.bank_accounts.view', 'admin.bank_accounts.create', 'admin.bank_accounts.update', 'admin.bank_accounts.toggle',
            'admin.electronic_wallets.view', 'admin.electronic_wallets.create', 'admin.electronic_wallets.update', 'admin.electronic_wallets.toggle',
            'admin.treasury_transactions.view', 'admin.treasury_transactions.create', 'admin.treasury_transactions.update', 'admin.treasury_transactions.post', 'admin.treasury_transactions.reverse',
            'admin.treasury_transfers.create',
            'admin.treasury_reports.ledger', 'admin.treasury_reports.reconciliation',
            'admin.driver_cod_settlements.view', 'admin.driver_cod_settlements.create', 'admin.driver_cod_settlements.post', 'admin.driver_cod_settlements.reverse',
            'admin.driver_cod_statements.view', 'admin.driver_cod_reconciliation.view',
            'admin.promotions.view', 'admin.promotions.create', 'admin.promotions.update', 'admin.promotions.activate', 'admin.promotions.deactivate',
            'admin.home_banners.view', 'admin.home_banners.create', 'admin.home_banners.update', 'admin.home_banners.delete', 'admin.home_banners.activate',
            'admin.home_sections.view', 'admin.home_sections.create', 'admin.home_sections.update', 'admin.home_sections.activate', 'admin.home_sections.delete',
            'admin.product_collections.view', 'admin.product_collections.create', 'admin.product_collections.update', 'admin.product_collections.activate', 'admin.product_collections.delete',
            'admin.coupons.view', 'admin.coupons.create', 'admin.coupons.update', 'admin.coupons.activate', 'admin.coupons.deactivate',
            'admin.bundles.view', 'admin.bundles.create', 'admin.bundles.update', 'admin.bundles.activate', 'admin.bundles.deactivate',
            'admin.loyalty.view', 'admin.loyalty.adjust', 'admin.loyalty.statement',
            'admin.customer_wallets.view', 'admin.customer_wallets.adjust', 'admin.customer_wallets.refund', 'admin.customer_wallets.reverse', 'admin.customer_wallets.statement',
            'admin.reports.sales.view', 'admin.reports.purchasing.view', 'admin.reports.inventory.view', 'admin.reports.returns.view',
            'admin.reports.delivery.view', 'admin.reports.driver_cod.view', 'admin.reports.finance.view', 'admin.reports.treasury.view',
            'admin.reports.customers.view', 'admin.reports.marketing.view', 'admin.reports.loyalty.view', 'admin.reports.customer_wallets.view',
            'admin.reports.export',
            'admin.search.view', 'admin.search.manage',
            'admin.sourcing.view', 'admin.sourcing.manage',
            'admin.notifications.view', 'admin.notifications.view_failures', 'admin.notifications.send_test', 'admin.notifications.retry', 'admin.notifications.manage',
            'admin.security.sessions.view', 'admin.security.sessions.manage',
            'admin.support_chat.view', 'admin.support_chat.view_all', 'admin.support_chat.reply', 'admin.support_chat.assign', 'admin.support_chat.close', 'admin.support_chat.reopen',
        ];

        $displayNames = [
            'admin.support_chat.view' => 'عرض محادثات دعم العملاء',
            'admin.support_chat.view_all' => 'عرض كل محادثات الدعم',
            'admin.support_chat.reply' => 'الرد على محادثات الدعم',
            'admin.support_chat.assign' => 'إسناد محادثات الدعم',
            'admin.support_chat.close' => 'إغلاق محادثات الدعم',
            'admin.support_chat.reopen' => 'إعادة فتح محادثات الدعم',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['display_name' => $displayNames[$permission] ?? $permission]
            );
        }

        foreach ([
            'super_admin' => 'Super Admin', 'admin' => 'Admin', 'operations_manager' => 'Operations Manager',
            'purchasing' => 'Purchasing', 'warehouse_manager' => 'Warehouse Manager', 'cashier' => 'Cashier',
            'accountant' => 'Accountant', 'customer_support' => 'Customer Support', 'delivery_supervisor' => 'Delivery Supervisor',
        ] as $name => $displayName) {
            Role::firstOrCreate(['name' => $name], ['display_name' => $displayName]);
        }

        Role::query()->where('name', 'super_admin')->firstOrFail()->syncPermissions(Permission::all());
        Role::query()->where('name', 'admin')->firstOrFail()->syncPermissions(Permission::query()->whereIn('name', $permissions)->get());

        $purchasingRole = Role::firstWhere('name', 'purchasing');
        if ($purchasingRole) {
            $purchasingPerms = Permission::where('name', 'like', 'admin.suppliers.%')
                ->orWhere('name', 'like', 'admin.purchases.%')
                ->orWhereIn('name', ['admin.dashboard.view', 'admin.products.view', 'admin.units.view', 'admin.currencies.view', 'admin.warehouses.view', 'admin.reports.purchasing.view'])
                ->get();
            $purchasingRole->syncPermissions($purchasingPerms);
        }

        $accountantRole = Role::firstWhere('name', 'accountant');
        if ($accountantRole) {
            $accountantPerms = Permission::whereIn('name', [
                'admin.dashboard.view',
                'admin.suppliers.view',
                'admin.suppliers.statement.view',
                'admin.purchases.view',
                'admin.currencies.view',
                'admin.exchange_rates.view',
                'admin.accounts.view',
                'admin.accounts.create',
                'admin.accounts.update',
                'admin.accounts.toggle',
                'admin.accounting_periods.view',
                'admin.accounting_periods.create',
                'admin.accounting_periods.close',
                'admin.journal_entries.view',
                'admin.journal_entries.create',
                'admin.journal_entries.update',
                'admin.journal_entries.post',
                'admin.journal_entries.reverse',
                'admin.trial_balance.view',
                'admin.general_ledger.view',
                'admin.cashboxes.view',
                'admin.cashboxes.create',
                'admin.cashboxes.update',
                'admin.cashboxes.toggle',
                'admin.bank_accounts.view',
                'admin.bank_accounts.create',
                'admin.bank_accounts.update',
                'admin.bank_accounts.toggle',
                'admin.electronic_wallets.view',
                'admin.electronic_wallets.create',
                'admin.electronic_wallets.update',
                'admin.electronic_wallets.toggle',
                'admin.treasury_transactions.view',
                'admin.treasury_transactions.create',
                'admin.treasury_transactions.update',
                'admin.treasury_transactions.post',
                'admin.treasury_transactions.reverse',
                'admin.treasury_transfers.create',
                'admin.treasury_reports.ledger',
                'admin.treasury_reports.reconciliation',
                'admin.driver_cod_settlements.view',
                'admin.driver_cod_settlements.create',
                'admin.driver_cod_settlements.post',
                'admin.driver_cod_settlements.reverse',
                'admin.driver_cod_statements.view',
                'admin.driver_cod_reconciliation.view',
                'admin.loyalty.view',
                'admin.customer_wallets.view',
                'admin.customer_wallets.adjust',
                'admin.customer_wallets.refund',
                'admin.customer_wallets.reverse',
                'admin.customer_wallets.statement',
                'admin.reports.finance.view',
                'admin.reports.treasury.view',
                'admin.reports.purchasing.view',
                'admin.reports.driver_cod.view',
                'admin.reports.loyalty.view',
                'admin.reports.customer_wallets.view',
                'admin.reports.export',
            ])->get();
            $accountantRole->syncPermissions($accountantPerms);
        }

        $warehouseRole = Role::firstWhere('name', 'warehouse_manager');
        if ($warehouseRole) {
            $warehousePerms = Permission::where('name', 'like', 'admin.inventory.%')
                ->orWhere('name', 'like', 'admin.purchase_receipts.%')
                ->orWhere('name', 'like', 'admin.stock_transfers.%')
                ->orWhere('name', 'like', 'admin.stock_adjustments.%')
                ->orWhere('name', 'like', 'admin.stock_movements.%')
                ->orWhere('name', 'like', 'admin.sales_returns.%')
                ->orWhere('name', 'like', 'admin.deliveries.return_to_warehouse')
                ->orWhereIn('name', ['admin.dashboard.view', 'admin.products.view', 'admin.variants.view', 'admin.units.view', 'admin.warehouses.view', 'admin.purchases.view', 'admin.orders.view', 'admin.deliveries.view', 'admin.reports.inventory.view'])
                ->get();
            $warehouseRole->syncPermissions($warehousePerms);
        }

        $supportRole = Role::firstWhere('name', 'customer_support');
        if ($supportRole) {
            $supportPerms = Permission::where('name', 'like', 'admin.customers.%')
                ->orWhere('name', 'like', 'admin.customer_addresses.%')
                ->orWhere('name', 'like', 'admin.customer_tiers.%')
                ->orWhere('name', 'like', 'admin.loyalty.%')
                ->orWhere('name', 'like', 'admin.customer_wallets.%')
                ->orWhere('name', 'like', 'admin.wishlists.%')
                ->orWhere('name', 'like', 'admin.reviews.%')
                ->orWhereIn('name', [
                    'admin.dashboard.view',
                    'admin.orders.view',
                    'admin.sales_returns.view',
                    'admin.deliveries.view',
                    'admin.reports.customers.view',
                    'admin.reports.loyalty.view',
                    'admin.reports.customer_wallets.view',
                    'admin.support_chat.view',
                    'admin.support_chat.reply',
                    'admin.support_chat.assign',
                    'admin.support_chat.close',
                    'admin.support_chat.reopen',
                ])
                ->get();
            $supportRole->syncPermissions($supportPerms);
        }

        $operationsRole = Role::firstWhere('name', 'operations_manager');
        if ($operationsRole) {
            $operationsPerms = Permission::where('name', 'like', 'admin.orders.%')
                ->orWhere('name', 'like', 'admin.sales_returns.%')
                ->orWhere('name', 'like', 'admin.customers.%')
                ->orWhere('name', 'like', 'admin.customer_tiers.%')
                ->orWhere('name', 'like', 'admin.inventory.%')
                ->orWhere('name', 'like', 'admin.drivers.%')
                ->orWhere('name', 'like', 'admin.deliveries.%')
                ->orWhere('name', 'like', 'admin.driver_cod_%')
                ->orWhere('name', 'like', 'admin.sourcing.%')
                ->orWhere('name', 'like', 'admin.notifications.%')
                ->orWhere('name', 'like', 'admin.wishlists.%')
                ->orWhere('name', 'like', 'admin.reviews.%')
                ->orWhereIn('name', ['admin.dashboard.view', 'admin.products.view', 'admin.warehouses.view', 'admin.reports.sales.view', 'admin.reports.returns.view', 'admin.reports.delivery.view', 'admin.reports.driver_cod.view', 'admin.reports.customers.view'])
                ->get();
            $operationsRole->syncPermissions($operationsRole->id ? $operationsPerms : []);
        }

        $deliverySupervisorRole = Role::firstWhere('name', 'delivery_supervisor');
        if ($deliverySupervisorRole) {
            $deliveryPerms = Permission::where('name', 'like', 'admin.drivers.%')
                ->orWhere('name', 'like', 'admin.deliveries.%')
                ->orWhere('name', 'like', 'admin.driver_cod_%')
                ->orWhereIn('name', ['admin.dashboard.view', 'admin.orders.view', 'admin.reports.delivery.view', 'admin.reports.driver_cod.view'])
                ->get();
            $deliverySupervisorRole->syncPermissions($deliveryPerms);
        }

        $cashierRole = Role::firstWhere('name', 'cashier');
        if ($cashierRole) {
            $cashierPerms = Permission::whereIn('name', [
                'admin.dashboard.view',
                'admin.orders.view',
                'admin.orders.create',
                'admin.orders.update',
                'admin.orders.confirm',
                'admin.products.view',
                'admin.inventory.view',
                'admin.cashboxes.view',
                'admin.treasury_transactions.view',
                'admin.treasury_transactions.create',
            ])->get();
            $cashierRole->syncPermissions($cashierPerms);
        }

        // Every admin role can manage their own browser sessions.
        $sessionPermNames = [
            'admin.security.sessions.view',
            'admin.security.sessions.manage',
        ];
        $sessionPerms = Permission::query()->whereIn('name', $sessionPermNames)->get();
        foreach (Role::query()->get() as $role) {
            $missing = $sessionPerms->reject(
                static fn (Permission $permission): bool => $role->hasPermission($permission->name)
            );
            if ($missing->isNotEmpty()) {
                $role->givePermissions($missing);
            }
        }
    }
}
