<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_sourcing_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->string('status', 32)->default('pending');

            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('failed_at')->nullable();
            $table->foreignId('failed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('failure_reason')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('order_item_id', 'order_sourcing_order_item_unique');
            $table->index(['status', 'created_at'], 'order_sourcing_status_created_idx');
            $table->index(['supplier_id', 'status'], 'order_sourcing_supplier_status_idx');
            $table->index(['assigned_driver_id', 'status'], 'order_sourcing_driver_status_idx');
            $table->index('order_id', 'order_sourcing_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sourcing_requirements');
    }
};
