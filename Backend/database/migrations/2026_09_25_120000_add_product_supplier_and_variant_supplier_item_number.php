<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('category_id')->constrained('suppliers')->nullOnDelete();
            $table->index('supplier_id', 'products_supplier_id_index');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('supplier_item_number', 100)->nullable()->after('supplier_id');
            $table->index('supplier_item_number', 'product_variants_supplier_item_number_index');
        });

        if (Schema::hasColumn('product_variants', 'supplier_id')) {
            $sharedSupplierByProduct = DB::table('product_variants')
                ->select('product_id', DB::raw('MIN(supplier_id) as supplier_id'))
                ->whereNotNull('supplier_id')
                ->groupBy('product_id')
                ->havingRaw('COUNT(DISTINCT supplier_id) = 1')
                ->get();

            foreach ($sharedSupplierByProduct as $row) {
                DB::table('products')
                    ->where('id', $row->product_id)
                    ->whereNull('supplier_id')
                    ->update(['supplier_id' => $row->supplier_id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex('product_variants_supplier_item_number_index');
            $table->dropColumn('supplier_item_number');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex('products_supplier_id_index');
            $table->dropColumn('supplier_id');
        });
    }
};
