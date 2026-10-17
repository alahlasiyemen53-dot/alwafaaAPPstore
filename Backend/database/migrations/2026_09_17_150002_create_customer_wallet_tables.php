<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallet_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->string('status', 30)->default('active'); // active, suspended
            $table->timestamps();

            $table->unique(['customer_id', 'currency_id']);
        });

        Schema::create('customer_wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_wallet_account_id')->constrained('customer_wallet_accounts')->cascadeOnDelete();
            $table->string('entry_number', 50)->unique();
            $table->string('type', 30); // refund_credit, manual_credit, manual_debit, reversal
            $table->decimal('amount', 18, 4);
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->string('source_event', 100);
            $table->text('description')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('customer_wallet_entries')->nullOnDelete();
            $table->timestamp('posted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_wallet_account_id', 'type'], 'cust_wallet_acc_type_idx');
            $table->index(['source_type', 'source_id', 'source_event'], 'wallet_source_index');
            $table->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_entries');
        Schema::dropIfExists('customer_wallet_accounts');
    }
};
