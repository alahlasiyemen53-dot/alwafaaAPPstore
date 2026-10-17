<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->string('status', 30)->default('active'); // active, suspended
            $table->timestamps();
        });

        Schema::create('customer_loyalty_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_loyalty_account_id')->constrained('customer_loyalty_accounts', indexName: 'loyalty_entry_acc_fk')->cascadeOnDelete();
            $table->string('entry_number', 50)->unique();
            $table->string('type', 30); // earn, redeem, clawback, adjustment_credit, adjustment_debit, reversal
            $table->unsignedInteger('points');
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->string('source_event', 100);
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('customer_loyalty_entries', indexName: 'loyalty_entry_rev_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_loyalty_account_id', 'type'], 'loyalty_acc_type_idx');
            $table->index(['source_type', 'source_id', 'source_event'], 'loyalty_source_index');
        });

        Schema::create('customer_loyalty_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_loyalty_account_id')->constrained('customer_loyalty_accounts', indexName: 'loyalty_res_acc_fk')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->string('status', 30)->default('active'); // active, consumed, released
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['customer_loyalty_account_id', 'status'], 'loyalty_res_acc_status_idx');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_reservations');
        Schema::dropIfExists('customer_loyalty_entries');
        Schema::dropIfExists('customer_loyalty_accounts');
    }
};
