<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->string('reason_code', 50);
            $table->text('notes')->nullable();

            // Historical currency and pricing snapshots
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate_snapshot', 18, 8)->default(1.0);
            $table->decimal('subtotal', 16, 4)->default(0);
            $table->decimal('total', 16, 4)->default(0);
            $table->decimal('base_currency_total', 16, 4)->default(0);

            // Audit
            $table->timestamp('posted_at')->nullable()->index();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();

            // Product & Variant Snapshot
            $table->string('product_name_snapshot', 255);
            $table->string('variant_name_snapshot', 255)->nullable();
            $table->string('sku_snapshot', 100);

            // Unit & Quantities
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_name_snapshot', 100)->nullable();
            $table->decimal('return_quantity', 16, 4);
            $table->decimal('return_base_quantity', 16, 4);

            // Historical pricing snapshot
            $table->decimal('unit_price_snapshot', 16, 4);
            $table->decimal('line_total', 16, 4);
            $table->decimal('base_currency_unit_price_snapshot', 16, 4);
            $table->decimal('base_currency_line_total', 16, 4);

            $table->string('reason_code', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sales_return_id', 'product_variant_id']);
            $table->index(['order_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
