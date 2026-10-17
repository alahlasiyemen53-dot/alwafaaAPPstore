<?php

declare(strict_types=1);

namespace App\Support;

final class PermissionLabel
{
    private const TERMS = [
        // Groups / resources
        'dashboard' => 'لوحة التحكم',
        'users' => 'المستخدمون',
        'roles' => 'الأدوار',
        'permissions' => 'الصلاحيات',
        'products' => 'المنتجات',
        'categories' => 'التصنيفات',
        'brands' => 'العلامات التجارية',
        'orders' => 'الطلبات',
        'customers' => 'العملاء',
        'customer_addresses' => 'عناوين العملاء',
        'customer_payment_methods' => 'طرق دفع العملاء',
        'customer_tiers' => 'شرائح العملاء',
        'customer_wallets' => 'محافظ العملاء',
        'suppliers' => 'الموردون',
        'warehouses' => 'المستودعات',
        'inventory' => 'المخزون',
        'purchases' => 'المشتريات',
        'purchasing' => 'المشتريات',
        'purchase_receipts' => 'إيصالات الشراء',
        'currencies' => 'العملات',
        'exchange_rates' => 'أسعار الصرف',
        'settings' => 'الإعدادات',
        'reports' => 'التقارير',
        'home_sections' => 'أقسام الصفحة الرئيسية',
        'product_collections' => 'مجموعات المنتجات',
        'home_banners' => 'بنرات الصفحة الرئيسية',
        'security' => 'الأمان',
        'sessions' => 'الجلسات',
        'attributes' => 'الخصائص',
        'variants' => 'المتغيرات',
        'variant_prices' => 'أسعار المتغيرات',
        'bundles' => 'الباقات',
        'units' => 'الوحدات',
        'coupons' => 'كوبونات الخصم',
        'promotions' => 'العروض',
        'marketing' => 'التسويق',
        'notifications' => 'الإشعارات',
        'reviews' => 'التقييمات',
        'wishlists' => 'قوائم الأمنيات',
        'loyalty' => 'الولاء',
        'drivers' => 'السائقون',
        'deliveries' => 'التوصيل',
        'delivery' => 'التوصيل',
        'driver_cod' => 'تحصيل السائق نقداً',
        'driver_cod_reconciliation' => 'تسوية تحصيل السائق',
        'driver_cod_settlements' => 'تصفية تحصيل السائق',
        'driver_cod_statements' => 'كشوف تحصيل السائق',
        'locations' => 'المواقع',
        'stock_adjustments' => 'تعديلات المخزون',
        'stock_movements' => 'حركات المخزون',
        'stock_transfers' => 'تحويلات المخزون',
        'returns' => 'المرتجعات',
        'sales' => 'المبيعات',
        'sales_returns' => 'مرتجعات المبيعات',
        'sourcing' => 'التوريد',
        'support_chat' => 'محادثات الدعم المباشر',
        'otp' => 'رمز التحقق',
        'accounts' => 'الحسابات',
        'accounting_periods' => 'الفترات المحاسبية',
        'bank_accounts' => 'الحسابات البنكية',
        'cashboxes' => 'الصناديق',
        'electronic_wallets' => 'المحافظ الإلكترونية',
        'finance' => 'المالية',
        'general_ledger' => 'دفتر الأستاذ العام',
        'journal_entries' => 'القيود اليومية',
        'ledger' => 'دفتر الأستاذ',
        'treasury' => 'الخزينة',
        'treasury_reports' => 'تقارير الخزينة',
        'treasury_transactions' => 'حركات الخزينة',
        'treasury_transfers' => 'تحويلات الخزينة',
        'trial_balance' => 'ميزان المراجعة',
        'reconciliation' => 'التسوية',

        // Actions
        'view' => 'عرض',
        'view_all' => 'عرض الكل',
        'view_failures' => 'عرض الإخفاقات',
        'create' => 'إنشاء',
        'update' => 'تعديل',
        'delete' => 'حذف',
        'activate' => 'تفعيل',
        'deactivate' => 'تعطيل',
        'assign' => 'تعيين',
        'upload' => 'رفع',
        'media' => 'الوسائط',
        'post' => 'ترحيل',
        'cancel' => 'إلغاء',
        'confirm' => 'تأكيد',
        'export' => 'تصدير',
        'profile' => 'الملف الشخصي',
        'manage' => 'إدارة',
        'statement' => 'كشف الحساب',
        'toggle' => 'تفعيل/تعطيل',
        'adjust' => 'تعديل رصيد',
        'change' => 'تغيير',
        'close' => 'إغلاق',
        'reopen' => 'إعادة فتح',
        'reply' => 'رد',
        'refund' => 'استرجاع',
        'retry' => 'إعادة محاولة',
        'reverse' => 'عكس قيد',
        'moderate' => 'إشراف',
        'search' => 'بحث',
        'send_test' => 'إرسال تجريبي',
        'set_default' => 'تعيين كافتراضي',
        'verify_phone' => 'توثيق الهاتف',
        'password' => 'كلمة المرور',
        'mark_delivered' => 'تعليميم كمُسلَّم',
        'mark_failed' => 'تعليميم كفاشل',
        'return_to_warehouse' => 'إعادة للمستودع',
    ];

    public static function for(string $permission): string
    {
        return collect(explode('.', $permission))
            ->reject(fn (string $part) => $part === 'admin')
            ->map(fn (string $part) => self::TERMS[$part] ?? self::humanize($part))
            ->implode(' — ');
    }

    public static function group(string $groupKey): string
    {
        return self::TERMS[$groupKey] ?? self::humanize($groupKey);
    }

    private static function humanize(string $part): string
    {
        return str_replace('_', ' ', $part);
    }
}
