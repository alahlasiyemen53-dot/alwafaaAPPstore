<?php

namespace Tests\Feature;

use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Sprint 19: the default test suite runs on an in-memory SQLite connection
 * (phpunit.xml), so every "concurrency" test elsewhere in this suite
 * (OrderInventoryConcurrencyTest, CouponUsageConcurrencyTest, ...) actually
 * runs sequentially on a single connection — it proves the business-logic
 * ordering is correct, but never proves that lockForUpdate() truly blocks a
 * second, independent connection the way it must on production MySQL/InnoDB.
 *
 * This test opens two REAL, independent PDO connections directly (bypassing
 * Laravel's DB manager, whose 'mysql' connection config is intentionally
 * repointed to ':memory:' by phpunit.xml for the rest of the suite) to a
 * real MySQL server, and proves SELECT ... FOR UPDATE genuinely blocks a
 * concurrent transaction until the first commits. It operates on a
 * throwaway probe table it creates and drops itself — it never touches
 * business data.
 *
 * Requires MYSQL_CONCURRENCY_TEST_DATABASE to be set (see
 * docs/testing-concurrency.md); skipped automatically otherwise so the
 * default suite never depends on a reachable real MySQL server.
 */
final class RealMysqlRowLockingTest extends TestCase
{
    private const PROBE_TABLE = 'sprint19_lock_probe';

    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();

        $database = env('MYSQL_CONCURRENCY_TEST_DATABASE');

        if (! $database) {
            $this->markTestSkipped(
                'Set MYSQL_CONCURRENCY_TEST_DATABASE (see docs/testing-concurrency.md) to run this against a real MySQL server.'
            );
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), $database);

        try {
            $this->pdo = new PDO($dsn, env('DB_USERNAME', 'root'), env('DB_PASSWORD', ''));
        } catch (PDOException $exception) {
            $this->markTestSkipped('Could not reach the real MySQL server: '.$exception->getMessage());
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('DROP TABLE IF EXISTS '.self::PROBE_TABLE);
        $this->pdo->exec('CREATE TABLE '.self::PROBE_TABLE.' (id INT PRIMARY KEY, value INT NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec('INSERT INTO '.self::PROBE_TABLE.' (id, value) VALUES (1, 0)');
    }

    protected function tearDown(): void
    {
        $this->pdo?->exec('DROP TABLE IF EXISTS '.self::PROBE_TABLE);
        $this->pdo = null;

        parent::tearDown();
    }

    public function test_lock_for_update_blocks_a_second_real_connection_until_commit(): void
    {
        $database = env('MYSQL_CONCURRENCY_TEST_DATABASE');
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), $database);

        $firstPdo = $this->pdo;
        $secondPdo = new PDO($dsn, env('DB_USERNAME', 'root'), env('DB_PASSWORD', ''));
        $secondPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Fail fast instead of hanging the test suite if locking is somehow broken.
        $secondPdo->exec('SET SESSION innodb_lock_wait_timeout = 2');

        $firstPdo->beginTransaction();
        $firstPdo->query('SELECT * FROM '.self::PROBE_TABLE.' WHERE id = 1 FOR UPDATE');

        try {
            $secondPdo->beginTransaction();
            $blocked = false;

            try {
                $secondPdo->query('SELECT * FROM '.self::PROBE_TABLE.' WHERE id = 1 FOR UPDATE');
            } catch (PDOException $exception) {
                $blocked = str_contains(strtolower($exception->getMessage()), 'lock wait timeout');
            }

            $secondPdo->rollBack();

            $this->assertTrue(
                $blocked,
                'A second real MySQL connection was NOT blocked by lockForUpdate() on a row already '.
                'locked by an open transaction. This means concurrency-critical Actions relying on '.
                'lockForUpdate() (order confirmation, journal posting, treasury posting, COD '.
                'settlement, loyalty/wallet mutation) would not actually prevent a race in production.'
            );
        } finally {
            $firstPdo->rollBack();
        }
    }
}
