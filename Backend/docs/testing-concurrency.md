# Real MySQL Concurrency Testing

## Why this exists

The default automated test suite (`phpunit.xml`) runs against an in-memory
SQLite connection for speed and isolation. The existing "concurrency" tests
(`OrderInventoryConcurrencyTest`, `CouponUsageConcurrencyTest`,
`TreasuryNegativeBalanceConcurrencyTest`, `LoyaltyAndWalletConcurrencyTest`,
`DriverCodOverSettlementProtectionTest`) run entirely **sequentially** on a
single connection. They correctly verify the business-logic outcome of a
race (the second operation is rejected/adjusted as expected), but they do
**not** exercise real multi-connection row locking — SQLite in-memory has no
comparable concurrent-transaction isolation to MySQL/InnoDB.

`tests/Feature/RealMysqlRowLockingTest.php` closes that gap by opening two
genuinely independent PDO connections to a real MySQL server and proving
`SELECT ... FOR UPDATE` blocks a second connection until the first commits —
the exact mechanism every `lockForUpdate()` call in this codebase (order
confirmation, journal posting, treasury posting, COD settlement, loyalty/
wallet mutation, accounting period closing) depends on.

## Running it

The test is skipped by default (no reachable MySQL required for the normal
suite). To run it against a real MySQL server:

```bash
cd Backend
MYSQL_CONCURRENCY_TEST_DATABASE=Lamma_DB php artisan test --filter=RealMysqlRowLockingTest
```

Requirements:

- `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` in the process
  environment/`.env` must point to a real, reachable MySQL server (the same
  values used for local development — phpunit.xml only overrides
  `DB_CONNECTION`/`DB_DATABASE` for the default sqlite suite, so these are
  still read from `.env`).
- `MYSQL_CONCURRENCY_TEST_DATABASE` — the database name to run the probe
  against. Use a real, reachable database; the test creates and drops its
  own throwaway table (`sprint19_lock_probe`) and never touches business
  tables, but it does require CREATE/DROP TABLE privileges.

The test fails fast (2-second `innodb_lock_wait_timeout`) rather than
hanging if locking is somehow not working.

## What this does not cover

This test proves the underlying locking primitive works. It does not
replace a full multi-process load test of a specific business workflow
(e.g. two real concurrent HTTP requests confirming the same order). Such a
scenario is better exercised manually against a staging environment in
Sprint 20 (UAT & cPanel Staging), for example by firing two `curl` requests
in parallel against the order-confirmation endpoint and confirming only one
succeeds — a true multi-process PHPUnit setup (forking workers, coordinating
timing) was judged disproportionate engineering effort for this Sprint
relative to what the existing sequential tests plus this locking-primitive
test already prove.
