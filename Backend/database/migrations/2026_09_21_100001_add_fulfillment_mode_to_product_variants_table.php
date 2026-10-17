<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('fulfillment_mode', 32)->default('stocked')->after('status');
            $table->foreignId('supplier_id')->nullable()->after('fulfillment_mode')->constrained('suppliers')->nullOnDelete();

            $table->index(['fulfillment_mode', 'status'], 'product_variants_fulfillment_status_idx');
            $table->index('supplier_id', 'product_variants_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex('product_variants_fulfillment_status_idx');
            $table->dropIndex('product_variants_supplier_idx');
            $table->dropColumn(['fulfillment_mode', 'supplier_id']);
        });
    }
};
