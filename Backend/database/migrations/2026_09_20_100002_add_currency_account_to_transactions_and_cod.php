<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('treasury_transactions', 'treasury_currency_account_id')) {
                $table->foreignId('treasury_currency_account_id')
                    ->nullable()
                    ->after('account_id')
                    ->constrained('treasury_currency_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('treasury_transactions', 'destination_currency_account_id')) {
                $table->foreignId('destination_currency_account_id')
                    ->nullable()
                    ->after('destination_id')
                    ->constrained('treasury_currency_accounts')
                    ->nullOnDelete();
            }
        });

        Schema::table('driver_cod_settlements', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_cod_settlements', 'treasury_currency_account_id')) {
                $table->foreignId('treasury_currency_account_id')
                    ->nullable()
                    ->after('treasury_account_id')
                    ->constrained('treasury_currency_accounts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('treasury_transactions', 'treasury_currency_account_id')) {
                $table->dropForeign(['treasury_currency_account_id']);
                $table->dropColumn('treasury_currency_account_id');
            }
            if (Schema::hasColumn('treasury_transactions', 'destination_currency_account_id')) {
                $table->dropForeign(['destination_currency_account_id']);
                $table->dropColumn('destination_currency_account_id');
            }
        });

        Schema::table('driver_cod_settlements', function (Blueprint $table) {
            if (Schema::hasColumn('driver_cod_settlements', 'treasury_currency_account_id')) {
                $table->dropForeign(['treasury_currency_account_id']);
                $table->dropColumn('treasury_currency_account_id');
            }
        });
    }
};
