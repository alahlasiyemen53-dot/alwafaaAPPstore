<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payment_methods', function (Blueprint $table): void {
            $table->foreignId('electronic_wallet_account_id')
                ->nullable()
                ->after('is_active')
                ->constrained('electronic_wallet_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_payment_methods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('electronic_wallet_account_id');
        });
    }
};
