<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('receipt_number', 64)->unique();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('received_at')->nullable()->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_invoice_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_item_id')->constrained('purchase_invoice_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('received_base_quantity', 14, 4);
            $table->decimal('unit_cost_snapshot', 18, 4)->default(0.0000);
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate_snapshot', 20, 8)->default(1.00000000);
            $table->decimal('base_currency_cost', 18, 4)->default(0.0000);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_receipt_id', 'product_variant_id'], 'pr_items_receipt_variant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};
