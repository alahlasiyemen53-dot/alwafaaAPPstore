<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashboxes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->foreignId('currency_id')->constrained('currencies')->onDelete('restrict');
            $table->foreignId('gl_account_id')->unique()->constrained('accounts')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'currency_id']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('iban')->nullable();
            $table->foreignId('currency_id')->constrained('currencies')->onDelete('restrict');
            $table->foreignId('gl_account_id')->unique()->constrained('accounts')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'currency_id']);
        });

        Schema::create('electronic_wallet_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('provider_name');
            $table->string('account_label');
            $table->string('account_identifier');
            $table->foreignId('currency_id')->constrained('currencies')->onDelete('restrict');
            $table->foreignId('gl_account_id')->unique()->constrained('accounts')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'currency_id']);
        });

        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->string('transaction_type', 32); // cash_receipt, cash_payment, bank_deposit, bank_withdrawal, wallet_receipt, wallet_payment, transfer, opening_balance, adjustment
            $table->string('status', 32)->default('draft'); // draft, posted, reversed

            $table->string('account_type', 64);
            $table->unsignedBigInteger('account_id');

            $table->string('destination_type', 64)->nullable();
            $table->unsignedBigInteger('destination_id')->nullable();

            $table->foreignId('currency_id')->constrained('currencies')->onDelete('restrict');
            $table->decimal('amount', 16, 4);
            $table->decimal('exchange_rate_snapshot', 16, 8)->default(1);
            $table->decimal('base_currency_amount', 16, 4);

            $table->foreignId('offset_account_id')->nullable()->constrained('accounts')->onDelete('restrict');

            $table->string('counterparty_type', 64)->nullable();
            $table->unsignedBigInteger('counterparty_id')->nullable();

            $table->string('reference_number')->nullable();
            $table->text('description');

            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->onDelete('restrict');

            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_type', 'account_id', 'status']);
            $table->index(['destination_type', 'destination_id']);
            $table->index(['transaction_type', 'status']);
            $table->index(['status', 'posted_at']);
            $table->index(['counterparty_type', 'counterparty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_transactions');
        Schema::dropIfExists('electronic_wallet_accounts');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('cashboxes');
    }
};
