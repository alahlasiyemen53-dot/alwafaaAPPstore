<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->restrictOnDelete();

            $table->decimal('quantity', 14, 4);
            $table->string('status', 30)->default('active')->index();

            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->index(['warehouse_id', 'product_variant_id', 'status'], 'inv_res_wh_var_status_idx');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
