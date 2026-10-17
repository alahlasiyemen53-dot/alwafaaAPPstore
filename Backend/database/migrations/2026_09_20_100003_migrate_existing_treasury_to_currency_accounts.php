<?php

use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill Cashboxes
        $cashboxes = DB::table('cashboxes')->whereNotNull('currency_id')->whereNotNull('gl_account_id')->get();
        foreach ($cashboxes as $cashbox) {
            $exists = DB::table('treasury_currency_accounts')
                ->where('accountable_type', Cashbox::class)
                ->where('accountable_id', $cashbox->id)
                ->where('currency_id', $cashbox->currency_id)
                ->exists();

            if (! $exists) {
                DB::table('treasury_currency_accounts')->insert([
                    'accountable_type' => Cashbox::class,
                    'accountable_id' => $cashbox->id,
                    'currency_id' => $cashbox->currency_id,
                    'gl_account_id' => $cashbox->gl_account_id,
                    'is_active' => $cashbox->is_active,
                    'notes' => $cashbox->notes,
                    'created_by' => $cashbox->created_by,
                    'updated_by' => $cashbox->updated_by,
                    'created_at' => $cashbox->created_at ?? now(),
                    'updated_at' => $cashbox->updated_at ?? now(),
                ]);
            }
        }

        // 2. Backfill Bank Accounts
        $bankAccounts = DB::table('bank_accounts')->whereNotNull('currency_id')->whereNotNull('gl_account_id')->get();
        foreach ($bankAccounts as $bank) {
            $exists = DB::table('treasury_currency_accounts')
                ->where('accountable_type', BankAccount::class)
                ->where('accountable_id', $bank->id)
                ->where('currency_id', $bank->currency_id)
                ->exists();

            if (! $exists) {
                DB::table('treasury_currency_accounts')->insert([
                    'accountable_type' => BankAccount::class,
                    'accountable_id' => $bank->id,
                    'currency_id' => $bank->currency_id,
                    'gl_account_id' => $bank->gl_account_id,
                    'is_active' => $bank->is_active,
                    'notes' => $bank->notes,
                    'created_by' => $bank->created_by,
                    'updated_by' => $bank->updated_by,
                    'created_at' => $bank->created_at ?? now(),
                    'updated_at' => $bank->updated_at ?? now(),
                ]);
            }
        }

        // 3. Backfill Electronic Wallets
        $wallets = DB::table('electronic_wallet_accounts')->whereNotNull('currency_id')->whereNotNull('gl_account_id')->get();
        foreach ($wallets as $wallet) {
            $exists = DB::table('treasury_currency_accounts')
                ->where('accountable_type', ElectronicWalletAccount::class)
                ->where('accountable_id', $wallet->id)
                ->where('currency_id', $wallet->currency_id)
                ->exists();

            if (! $exists) {
                DB::table('treasury_currency_accounts')->insert([
                    'accountable_type' => ElectronicWalletAccount::class,
                    'accountable_id' => $wallet->id,
                    'currency_id' => $wallet->currency_id,
                    'gl_account_id' => $wallet->gl_account_id,
                    'is_active' => $wallet->is_active,
                    'notes' => $wallet->notes,
                    'created_by' => $wallet->created_by,
                    'updated_by' => $wallet->updated_by,
                    'created_at' => $wallet->created_at ?? now(),
                    'updated_at' => $wallet->updated_at ?? now(),
                ]);
            }
        }

        // 4. Map Treasury Transactions
        $transactions = DB::table('treasury_transactions')->whereNull('treasury_currency_account_id')->get();
        foreach ($transactions as $trx) {
            $accountClass = match ($trx->account_type) {
                'cashbox', Cashbox::class => Cashbox::class,
                'bank_account', BankAccount::class => BankAccount::class,
                'electronic_wallet', ElectronicWalletAccount::class => ElectronicWalletAccount::class,
                default => $trx->account_type,
            };

            $currAcc = DB::table('treasury_currency_accounts')
                ->where('accountable_type', $accountClass)
                ->where('accountable_id', $trx->account_id)
                ->where('currency_id', $trx->currency_id)
                ->first();

            $destCurrAccId = null;
            if ($trx->destination_type && $trx->destination_id) {
                $destClass = match ($trx->destination_type) {
                    'cashbox', Cashbox::class => Cashbox::class,
                    'bank_account', BankAccount::class => BankAccount::class,
                    'electronic_wallet', ElectronicWalletAccount::class => ElectronicWalletAccount::class,
                    default => $trx->destination_type,
                };

                $destCurrAcc = DB::table('treasury_currency_accounts')
                    ->where('accountable_type', $destClass)
                    ->where('accountable_id', $trx->destination_id)
                    ->first();

                if ($destCurrAcc) {
                    $destCurrAccId = $destCurrAcc->id;
                }
            }

            if ($currAcc) {
                DB::table('treasury_transactions')
                    ->where('id', $trx->id)
                    ->update([
                        'treasury_currency_account_id' => $currAcc->id,
                        'destination_currency_account_id' => $destCurrAccId,
                    ]);
            }
        }

        // 5. Map Driver COD Settlements
        $settlements = DB::table('driver_cod_settlements')->whereNull('treasury_currency_account_id')->get();
        foreach ($settlements as $settlement) {
            $targetClass = match ($settlement->treasury_account_type) {
                'cashbox', Cashbox::class => Cashbox::class,
                'bank_account', BankAccount::class => BankAccount::class,
                'electronic_wallet', ElectronicWalletAccount::class => ElectronicWalletAccount::class,
                default => $settlement->treasury_account_type,
            };

            $currAcc = DB::table('treasury_currency_accounts')
                ->where('accountable_type', $targetClass)
                ->where('accountable_id', $settlement->treasury_account_id)
                ->where('currency_id', $settlement->currency_id)
                ->first();

            if ($currAcc) {
                DB::table('driver_cod_settlements')
                    ->where('id', $settlement->id)
                    ->update([
                        'treasury_currency_account_id' => $currAcc->id,
                    ]);
            }
        }
    }

    public function down(): void
    {
        // No-op rollback to preserve accounting traceability safely
    }
};
