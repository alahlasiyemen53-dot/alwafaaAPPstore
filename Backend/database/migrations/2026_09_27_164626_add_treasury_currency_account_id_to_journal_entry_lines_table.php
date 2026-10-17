<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->foreignId('treasury_currency_account_id')
                ->nullable()
                ->after('account_id')
                ->constrained('treasury_currency_accounts')
                ->nullOnDelete();
            $table->index('treasury_currency_account_id', 'jel_treasury_curr_acc_idx');
        });

        $this->backfillFromTreasuryTransactions();
        $this->backfillFromCodSettlements();
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->dropForeign(['treasury_currency_account_id']);
            $table->dropIndex('jel_treasury_curr_acc_idx');
            $table->dropColumn('treasury_currency_account_id');
        });
    }

    private function backfillFromTreasuryTransactions(): void
    {
        $rows = DB::table('treasury_transactions')
            ->whereNotNull('journal_entry_id')
            ->whereNotNull('treasury_currency_account_id')
            ->select([
                'id',
                'journal_entry_id',
                'treasury_currency_account_id',
                'destination_currency_account_id',
                'transaction_type',
            ])
            ->get();

        foreach ($rows as $trx) {
            $sourceTcaId = (int) $trx->treasury_currency_account_id;
            $sourceGlId = DB::table('treasury_currency_accounts')->where('id', $sourceTcaId)->value('gl_account_id');
            if (! $sourceGlId) {
                continue;
            }

            $isTransfer = $trx->transaction_type === 'transfer';
            $isOutgoing = in_array($trx->transaction_type, [
                'cash_payment',
                'bank_withdrawal',
                'wallet_payment',
                'transfer',
            ], true);

            if ($isTransfer && $trx->destination_currency_account_id) {
                $destTcaId = (int) $trx->destination_currency_account_id;
                $destGlId = DB::table('treasury_currency_accounts')->where('id', $destTcaId)->value('gl_account_id');

                // Debit destination
                if ($destGlId) {
                    DB::table('journal_entry_lines')
                        ->where('journal_entry_id', $trx->journal_entry_id)
                        ->where('account_id', $destGlId)
                        ->where('debit', '>', 0)
                        ->whereNull('treasury_currency_account_id')
                        ->update(['treasury_currency_account_id' => $destTcaId]);
                }

                // Credit source
                DB::table('journal_entry_lines')
                    ->where('journal_entry_id', $trx->journal_entry_id)
                    ->where('account_id', $sourceGlId)
                    ->where('credit', '>', 0)
                    ->whereNull('treasury_currency_account_id')
                    ->update(['treasury_currency_account_id' => $sourceTcaId]);

                continue;
            }

            if ($isOutgoing) {
                DB::table('journal_entry_lines')
                    ->where('journal_entry_id', $trx->journal_entry_id)
                    ->where('account_id', $sourceGlId)
                    ->where('credit', '>', 0)
                    ->whereNull('treasury_currency_account_id')
                    ->update(['treasury_currency_account_id' => $sourceTcaId]);
            } else {
                DB::table('journal_entry_lines')
                    ->where('journal_entry_id', $trx->journal_entry_id)
                    ->where('account_id', $sourceGlId)
                    ->where('debit', '>', 0)
                    ->whereNull('treasury_currency_account_id')
                    ->update(['treasury_currency_account_id' => $sourceTcaId]);
            }
        }
    }

    private function backfillFromCodSettlements(): void
    {
        if (! Schema::hasTable('driver_cod_settlements')) {
            return;
        }

        $rows = DB::table('driver_cod_settlements')
            ->whereNotNull('journal_entry_id')
            ->whereNotNull('treasury_currency_account_id')
            ->select(['journal_entry_id', 'treasury_currency_account_id'])
            ->get();

        foreach ($rows as $settlement) {
            $tcaId = (int) $settlement->treasury_currency_account_id;
            $glId = DB::table('treasury_currency_accounts')->where('id', $tcaId)->value('gl_account_id');
            if (! $glId) {
                continue;
            }

            DB::table('journal_entry_lines')
                ->where('journal_entry_id', $settlement->journal_entry_id)
                ->where('account_id', $glId)
                ->where('debit', '>', 0)
                ->whereNull('treasury_currency_account_id')
                ->update(['treasury_currency_account_id' => $tcaId]);
        }
    }
};
