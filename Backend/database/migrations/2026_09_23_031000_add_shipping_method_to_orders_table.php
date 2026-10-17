<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Do not use after('gift_message_snapshot'): that column is added later
            // by 2026_09_27_100001_add_gift_wrapping_to_orders_table.
            $table->string('shipping_method_code', 64)->nullable();
            $table->string('shipping_method_name_ar')->nullable();
            $table->string('shipping_method_name_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'shipping_method_code',
                'shipping_method_name_ar',
                'shipping_method_name_en',
            ]);
        });
    }
};
