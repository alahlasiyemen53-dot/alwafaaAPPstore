<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared-GL model: multiple currency rows on the same parent may share one
 * gl_account_id. MySQL forbids dropping the unique index while the FK still
 * depends on it, so drop FK → drop unique → add plain index → restore FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_currency_accounts', function (Blueprint $table): void {
            $table->dropForeign(['gl_account_id']);
        });

        Schema::table('treasury_currency_accounts', function (Blueprint $table): void {
            $table->dropUnique(['gl_account_id']);
        });

        Schema::table('treasury_currency_accounts', function (Blueprint $table): void {
            $table->index('gl_account_id', 'treasury_curr_acc_gl_idx');
            $table->foreign('gl_account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_currency_accounts', function (Blueprint $table): void {
            $table->dropForeign(['gl_account_id']);
        });

        Schema::table('treasury_currency_accounts', function (Blueprint $table): void {
            $table->dropIndex('treasury_curr_acc_gl_idx');
        });

        Schema::table('treasury_currency_accounts', function (Blueprint $table): void {
            $table->unique('gl_account_id');
            $table->foreign('gl_account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('restrict');
        });
    }
};
