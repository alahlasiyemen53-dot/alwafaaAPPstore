<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('fulfillment_mode_snapshot', 32)->default('stocked')->after('sku_snapshot');
            $table->foreignId('sourcing_supplier_id_snapshot')->nullable()->after('fulfillment_mode_snapshot')->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name_snapshot', 191)->nullable()->after('sourcing_supplier_id_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['sourcing_supplier_id_snapshot']);
            $table->dropColumn(['fulfillment_mode_snapshot', 'sourcing_supplier_id_snapshot', 'supplier_name_snapshot']);
        });
    }
};
