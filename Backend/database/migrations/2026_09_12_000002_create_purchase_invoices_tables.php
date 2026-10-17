<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('invoice_number', 64)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('supplier_invoice_number', 128)->nullable();
            $table->date('invoice_date')->index();
            $table->string('status', 16)->default('draft')->index();
            $table->decimal('exchange_rate', 20, 8)->default(1.00000000);
            $table->decimal('subtotal', 18, 4)->default(0.0000);
            $table->decimal('discount_amount', 18, 4)->default(0.0000);
            $table->decimal('other_costs', 18, 4)->default(0.0000);
            $table->decimal('total', 18, 4)->default(0.0000);
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable()->index();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['invoice_date', 'status']);
        });

        Schema::create('purchase_invoice_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->decimal('base_quantity', 14, 4)->default(0.0000);
            $table->decimal('free_quantity', 14, 4)->default(0.0000);
            $table->decimal('unit_cost', 18, 4)->default(0.0000);
            $table->decimal('line_subtotal', 18, 4)->default(0.0000);
            $table->decimal('discount_amount', 18, 4)->default(0.0000);
            $table->decimal('line_total', 18, 4)->default(0.0000);
            $table->timestamps();

            $table->index(['purchase_invoice_id', 'product_variant_id'], 'pi_items_invoice_variant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');
    }
};
