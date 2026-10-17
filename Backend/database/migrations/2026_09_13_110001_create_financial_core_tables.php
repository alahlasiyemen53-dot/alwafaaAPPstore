<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->string('type', 32); // asset, liability, equity, revenue, expense
            $table->string('normal_balance', 16); // debit, credit
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('parent_id');
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status', 32)->default('open'); // open, closed
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at', 'status']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->onDelete('restrict');
            $table->date('entry_date');
            $table->string('status', 32)->default('draft'); // draft, posted, reversed
            $table->text('description');

            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event')->nullable();

            $table->foreignId('currency_id')->constrained('currencies')->onDelete('restrict');
            $table->decimal('exchange_rate_snapshot', 16, 8)->default(1);
            $table->decimal('total_debit', 16, 4)->default(0);
            $table->decimal('total_credit', 16, 4)->default(0);

            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'entry_date']);
            $table->index(['source_type', 'source_id', 'source_event']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');
            $table->string('description')->nullable();
            $table->decimal('debit', 16, 4)->default(0);
            $table->decimal('credit', 16, 4)->default(0);

            $table->foreignId('transaction_currency_id')->constrained('currencies')->onDelete('restrict');
            $table->decimal('transaction_amount', 16, 4)->default(0);
            $table->decimal('exchange_rate_snapshot', 16, 8)->default(1);
            $table->decimal('base_currency_amount', 16, 4)->default(0);

            $table->string('party_type')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->unsignedInteger('line_order')->default(1);
            $table->timestamps();

            $table->index(['journal_entry_id', 'line_order']);
            $table->index(['account_id', 'created_at']);
            $table->index(['party_type', 'party_id']);
        });

        Schema::create('accounting_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('name_ar');
            $table->foreignId('debit_account_id')->constrained('accounts')->onDelete('restrict');
            $table->foreignId('credit_account_id')->constrained('accounts')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_mappings');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounts');
    }
};
