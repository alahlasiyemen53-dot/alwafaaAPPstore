<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();

            // Historical product snapshots
            $table->string('product_name_snapshot');
            $table->string('variant_name_snapshot')->nullable();
            $table->string('sku_snapshot');

            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_name_snapshot')->nullable();

            // Quantities
            $table->decimal('quantity', 14, 4);
            $table->decimal('base_quantity', 14, 4);

            // Transaction currency pricing
            $table->decimal('unit_price', 14, 4);
            $table->decimal('line_subtotal', 14, 4);
            $table->decimal('discount_amount', 14, 4)->default(0.0000);
            $table->decimal('line_total', 14, 4);

            // Base currency equivalents snapshot
            $table->decimal('base_currency_unit_price', 14, 4);
            $table->decimal('base_currency_line_total', 14, 4);

            $table->timestamps();

            $table->index('order_id');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
