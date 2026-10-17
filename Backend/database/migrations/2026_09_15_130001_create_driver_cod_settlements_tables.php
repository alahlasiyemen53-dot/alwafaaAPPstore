<?php

declare(strict_types=1);

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
        Schema::create('driver_cod_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('settlement_number', 50)->unique();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();

            // Destination Treasury Account (Morph: Cashbox, BankAccount, ElectronicWalletAccount)
            $table->string('treasury_account_type', 100)->nullable();
            $table->unsignedBigInteger('treasury_account_id')->nullable();

            $table->decimal('total_expected', 16, 4)->default(0);
            $table->decimal('total_settled', 16, 4)->default(0);
            $table->text('notes')->nullable();

            // Posting Audit
            $table->timestamp('posted_at')->nullable()->index();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            // Reversal Audit
            $table->timestamp('reversed_at')->nullable()->index();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            // GL Linkage
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['currency_id', 'status']);
            $table->index(['treasury_account_type', 'treasury_account_id'], 'dcs_treasury_account_idx');
        });

        Schema::create('driver_cod_settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained('driver_cod_settlements')->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();

            $table->decimal('collected_amount', 16, 4)->default(0);
            $table->decimal('previously_settled_amount', 16, 4)->default(0);
            $table->decimal('settlement_amount', 16, 4)->default(0);
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();

            $table->timestamps();

            $table->index(['settlement_id', 'delivery_id']);
            $table->index('delivery_id');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_cod_settlement_items');
        Schema::dropIfExists('driver_cod_settlements');
    }
};
