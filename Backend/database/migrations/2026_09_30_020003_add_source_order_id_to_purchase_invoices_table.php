<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->foreignId('source_order_id')->nullable()->after('notes')->constrained('orders')->nullOnDelete();
            $table->unique(['source_order_id', 'supplier_id'], 'purchase_invoices_source_order_supplier_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->dropUnique('purchase_invoices_source_order_supplier_unique');
            $table->dropConstrainedForeignId('source_order_id');
        });
    }
};
