<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('currency_id')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code_snapshot')->nullable()->after('coupon_id');
            $table->decimal('promotion_discount_total', 12, 4)->default(0.0000)->after('discount_amount');
            $table->decimal('coupon_discount_total', 12, 4)->default(0.0000)->after('promotion_discount_total');
            $table->decimal('bundle_discount_total', 12, 4)->default(0.0000)->after('coupon_discount_total');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('unit_name_snapshot')->constrained('promotions')->nullOnDelete();
            $table->string('promotion_name_snapshot')->nullable()->after('promotion_id');
            $table->foreignId('bundle_id')->nullable()->after('promotion_name_snapshot')->constrained('bundles')->nullOnDelete();
            $table->string('bundle_name_snapshot')->nullable()->after('bundle_id');
            $table->decimal('original_unit_price', 12, 4)->nullable()->after('unit_price');
            $table->decimal('item_discount_amount', 12, 4)->default(0.0000)->after('discount_amount');
            $table->decimal('allocated_order_discount', 12, 4)->default(0.0000)->after('item_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropForeign(['bundle_id']);
            $table->dropColumn([
                'promotion_id',
                'promotion_name_snapshot',
                'bundle_id',
                'bundle_name_snapshot',
                'original_unit_price',
                'item_discount_amount',
                'allocated_order_discount',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn([
                'coupon_id',
                'coupon_code_snapshot',
                'promotion_discount_total',
                'coupon_discount_total',
                'bundle_discount_total',
            ]);
        });
    }
};
