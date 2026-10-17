<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('discount_type'); // percentage, fixed_amount
            $table->decimal('discount_value', 12, 4);
            $table->decimal('minimum_order_amount', 12, 4)->nullable();
            $table->decimal('maximum_discount_amount', 12, 4)->nullable();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('is_compatible_with_promotions')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('status')->default('active')->index(); // active, released
            $table->decimal('discount_amount_snapshot', 12, 4);
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->dateTime('used_at');
            $table->dateTime('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamps();

            $table->index(['coupon_id', 'status'], 'coupon_status_idx');
            $table->index(['coupon_id', 'customer_id', 'status'], 'coupon_cust_status_idx');
            $table->index(['order_id'], 'coupon_usage_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
    }
};
