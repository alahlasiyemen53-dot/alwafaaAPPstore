<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_ledger_entries', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('entry_type', 32);
            $table->string('reference_type', 64);
            $table->unsignedBigInteger('reference_id');
            $table->decimal('amount', 18, 4);
            $table->string('direction', 8);
            $table->decimal('exchange_rate_snapshot', 20, 8)->default(1.00000000);
            $table->decimal('base_currency_amount', 18, 4)->default(0.0000);
            $table->string('description')->nullable();
            $table->timestamp('posted_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['reference_type', 'reference_id', 'entry_type'], 'supplier_ledger_reference_unique');
            $table->index(['supplier_id', 'currency_id', 'posted_at'], 'supplier_currency_posted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ledger_entries');
    }
};
