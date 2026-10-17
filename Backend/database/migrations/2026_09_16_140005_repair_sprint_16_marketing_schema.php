<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['usage_limit', 'per_customer_limit']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->string('scope')->default('all')->after('discount_value');
        });

        Schema::create('coupon_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->string('target_type'); // product, variant, category, brand
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['coupon_id', 'target_type', 'target_id'], 'coupon_target_unique');
            $table->index(['target_type', 'target_id'], 'coupon_target_lookup_idx');
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->unique(['order_id', 'coupon_id'], 'coupon_usages_order_coupon_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_name_snapshot')->nullable()->after('coupon_code_snapshot');
            $table->string('coupon_discount_type_snapshot')->nullable()->after('coupon_name_snapshot');
            $table->decimal('coupon_discount_value_base_snapshot', 12, 4)->nullable()->after('coupon_discount_type_snapshot');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('promotion_type_snapshot')->nullable()->after('promotion_name_snapshot');
            $table->decimal('promotion_value_base_snapshot', 12, 4)->nullable()->after('promotion_type_snapshot');
            $table->string('bundle_code_snapshot')->nullable()->after('bundle_name_snapshot');
            $table->string('bundle_pricing_type_snapshot')->nullable()->after('bundle_code_snapshot');
            $table->decimal('bundle_value_base_snapshot', 12, 4)->nullable()->after('bundle_pricing_type_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'promotion_type_snapshot',
                'promotion_value_base_snapshot',
                'bundle_code_snapshot',
                'bundle_pricing_type_snapshot',
                'bundle_value_base_snapshot',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'coupon_name_snapshot',
                'coupon_discount_type_snapshot',
                'coupon_discount_value_base_snapshot',
            ]);
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropUnique('coupon_usages_order_coupon_unique');
        });

        Schema::dropIfExists('coupon_targets');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('scope');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
        });
    }
};
