<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shipping_method_code', 64)->nullable()->after('gift_message_snapshot');
            $table->string('shipping_method_name_ar')->nullable()->after('shipping_method_code');
            $table->string('shipping_method_name_en')->nullable()->after('shipping_method_name_ar');
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
