<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow the same chart-of-accounts GL to back multiple cashboxes / banks / wallets.
 * Operational balances stay separated via treasury_currency_account_id on journal lines.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'cashboxes',
        'bank_accounts',
        'electronic_wallet_accounts',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['gl_account_id']);
            });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['gl_account_id']);
            });

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->index('gl_account_id', $tableName.'_gl_account_id_idx');
                $table->foreign('gl_account_id')
                    ->references('id')
                    ->on('accounts')
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['gl_account_id']);
            });

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_gl_account_id_idx');
            });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('gl_account_id');
                $table->foreign('gl_account_id')
                    ->references('id')
                    ->on('accounts')
                    ->onDelete('restrict');
            });
        }
    }
};
