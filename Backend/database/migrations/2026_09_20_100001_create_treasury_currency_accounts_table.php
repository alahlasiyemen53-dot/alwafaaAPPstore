<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_currency_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('accountable_type', 64);
            $table->unsignedBigInteger('accountable_id');
            $table->foreignId('currency_id')->constrained('currencies')->onDelete('restrict');
            $table->foreignId('gl_account_id')->unique()->constrained('accounts')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['accountable_type', 'accountable_id', 'currency_id'], 'treasury_curr_acc_unique');
            $table->index(['accountable_type', 'accountable_id', 'is_active'], 'treasury_curr_acc_active_idx');
            $table->index(['currency_id', 'is_active'], 'treasury_curr_acc_curr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_currency_accounts');
    }
};
