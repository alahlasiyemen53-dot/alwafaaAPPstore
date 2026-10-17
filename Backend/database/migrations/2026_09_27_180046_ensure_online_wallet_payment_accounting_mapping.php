<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AccountingMapping;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensure ONLINE_WALLET_PAYMENT_CAPTURED mapping exists when GL 1170 is present.
 * Fixes environments that have chart accounts but never re-seeded mappings.
 */
return new class extends Migration
{
    public function up(): void
    {
        $clearing = Account::query()->where('code', '1170')->first();
        if ($clearing === null) {
            return;
        }

        $cashPlaceholder = Account::query()->where('code', '1110')->first();

        AccountingMapping::query()->updateOrCreate(
            ['event_key' => 'ONLINE_WALLET_PAYMENT_CAPTURED'],
            [
                'name_ar' => 'قبض محفظة إلكترونية عند دفع طلب العميل',
                'debit_account_id' => $cashPlaceholder?->id ?? $clearing->id,
                'credit_account_id' => $clearing->id,
                'is_active' => true,
                'description' => 'إثبات دخول النقدية إلى محفظة الشركة الإلكترونية (المدين يُستبدل بحساب المحفظة الفعلي عند الترحيل)',
            ]
        );
    }

    public function down(): void
    {
        // Keep mapping — removing it would break live wallet posting.
    }
};
