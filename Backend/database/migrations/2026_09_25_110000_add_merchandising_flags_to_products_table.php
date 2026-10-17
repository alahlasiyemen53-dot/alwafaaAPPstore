<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_best_seller')->default(false)->after('has_variants');
            $table->boolean('is_new')->default(false)->after('is_best_seller');
            $table->boolean('is_exclusive')->default(false)->after('is_new');

            // Composite indexes with status for catalog queries
            $table->index(['status', 'is_best_seller'], 'idx_products_status_best_seller');
            $table->index(['status', 'is_new'], 'idx_products_status_new');
            $table->index(['status', 'is_exclusive'], 'idx_products_status_exclusive');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('idx_products_status_best_seller');
            $table->dropIndex('idx_products_status_new');
            $table->dropIndex('idx_products_status_exclusive');

            $table->dropColumn([
                'is_best_seller',
                'is_new',
                'is_exclusive',
            ]);
        });
    }
};
