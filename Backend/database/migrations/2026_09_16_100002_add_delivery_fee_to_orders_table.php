<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('delivery_fee', 14, 4)->default(0.0000)->after('discount_amount');
            $table->decimal('base_currency_delivery_fee', 14, 4)->default(0.0000)->after('base_currency_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['delivery_fee', 'base_currency_delivery_fee']);
        });
    }
};
