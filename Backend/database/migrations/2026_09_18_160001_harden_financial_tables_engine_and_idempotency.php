<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 19 hardening: financial/treasury/COD/marketing/loyalty/wallet tables rely
     * heavily on lockForUpdate() for concurrency safety, which is a no-op on non-transactional
     * storage engines. Force InnoDB explicitly instead of depending on the server default.
     *
     * NOTE: an earlier draft of this migration also added a unique index on
     * journal_entries(source_type, source_id, source_event) as a defense-in-depth idempotency
     * guard. Running the full regression suite against it proved that assumption WRONG:
     * repeatable admin actions (e.g. ADMIN_MANUAL_CREDIT/ADMIN_MANUAL_DEBIT on the same wallet
     * account) legitimately create multiple journal entries sharing that exact tuple, one per
     * real transaction. That guard was removed; the existing app-level check-then-create
     * pattern inside a locked transaction (see PostJournalEntryAction/BuildJournalFrom*Action)
     * remains the correct idempotency mechanism for the one-shot source events where it applies.
     */
    private const TABLES_REQUIRING_INNODB = [
        'accounts',
        'accounting_periods',
        'journal_entries',
        'journal_entry_lines',
        'accounting_mappings',
        'cashboxes',
        'bank_accounts',
        'electronic_wallet_accounts',
        'treasury_transactions',
        'driver_cod_settlements',
        'coupons',
        'coupon_usages',
        'customer_loyalty_accounts',
        'customer_loyalty_entries',
        'customer_wallet_accounts',
        'customer_wallet_entries',
    ];

    public function up(): void
    {
        // The ENGINE clause is MySQL/MariaDB-specific. The default automated test suite runs on
        // in-memory SQLite (phpunit.xml), which has no storage-engine concept, so this migration
        // is a no-op there.
        $isMySql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

        if (! $isMySql) {
            return;
        }

        foreach (self::TABLES_REQUIRING_INNODB as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: downgrading a live table away from InnoDB is never a
        // safe/meaningful rollback action for transactional business tables.
    }
};
