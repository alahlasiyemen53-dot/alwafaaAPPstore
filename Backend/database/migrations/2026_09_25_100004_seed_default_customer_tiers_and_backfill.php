<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Create or ensure standard default tier
        $standardTierId = DB::table('customer_tiers')->where('code', 'STANDARD')->value('id');
        if (! $standardTierId) {
            $standardTierId = DB::table('customer_tiers')->insertGetId([
                'code' => 'STANDARD',
                'name_ar' => 'المستوى الأساسي',
                'name_en' => 'Standard',
                'description' => 'المستوى الافتراضي الأساسي لجميع العملاء',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. Create or ensure VIP tier
        $vipExists = DB::table('customer_tiers')->where('code', 'VIP')->exists();
        if (! $vipExists) {
            DB::table('customer_tiers')->insert([
                'code' => 'VIP',
                'name_ar' => 'كبار الشخصيات VIP',
                'name_en' => 'VIP',
                'description' => 'تصنيف كبار العملاء المميزين',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Backfill existing customers without tier
        $customers = DB::table('customers')->whereNull('current_tier_id')->get(['id', 'created_at']);
        foreach ($customers as $customer) {
            DB::table('customers')->where('id', $customer->id)->update([
                'current_tier_id' => $standardTierId,
            ]);

            $hasActiveMembership = DB::table('customer_tier_memberships')
                ->where('customer_id', $customer->id)
                ->whereNull('ends_at')
                ->exists();

            if (! $hasActiveMembership) {
                DB::table('customer_tier_memberships')->insert([
                    'customer_id' => $customer->id,
                    'customer_tier_id' => $standardTierId,
                    'starts_at' => $customer->created_at ?? $now,
                    'ends_at' => null,
                    'assignment_source' => 'SYSTEM_DEFAULT',
                    'assigned_by' => null,
                    'change_reason' => 'ترحيل أولي للمستوى الافتراضي للنظام',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Safe down: do nothing or leave data intact to prevent accidental loss
    }
};
