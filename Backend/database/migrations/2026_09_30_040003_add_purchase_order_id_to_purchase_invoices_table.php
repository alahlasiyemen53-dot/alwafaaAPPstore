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
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('source_order_id')
                ->constrained('purchase_orders')
                ->nullOnDelete();

            $table->unique('purchase_order_id', 'purchase_invoices_purchase_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->dropUnique('purchase_invoices_purchase_order_unique');
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};
