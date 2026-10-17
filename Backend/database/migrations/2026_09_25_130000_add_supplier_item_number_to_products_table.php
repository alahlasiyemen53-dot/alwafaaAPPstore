<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('supplier_item_number', 100)->nullable()->after('supplier_id');
            $table->index('supplier_item_number', 'products_supplier_item_number_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_supplier_item_number_index');
            $table->dropColumn('supplier_item_number');
        });
    }
};
