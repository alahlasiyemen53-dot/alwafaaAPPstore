<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('movement_type', 32)->index();
            $table->string('direction', 8)->index();
            $table->decimal('quantity', 14, 4);
            $table->string('reference_type', 128);
            $table->unsignedBigInteger('reference_id');
            $table->string('reason_code', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'product_variant_id'], 'sm_warehouse_variant_idx');
            $table->index(['reference_type', 'reference_id'], 'sm_reference_idx');
            $table->index(['occurred_at', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
