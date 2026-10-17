# Sprint 1 — Admin Authentication, Users, Roles & Permissions

Sprint 1 uses `santigarcor/laratrust` as the single active role/permission system. The Spatie Permission package was removed; its Sprint 0 migration is retained as a no-op historical record so existing installations are not asked to drop authorization tables.

The admin API uses Sanctum bearer tokens. Login accepts one `identifier` value matching email or phone, and returns a token only after the account is active and the password verifies. Logout revokes the account's current API tokens. Password changes verify the current password and revoke existing tokens.

Initial permissions are seeded in `AuthorizationSeeder`. Roles are seeded with `super_admin`, `admin`, `operations_manager`, `purchasing`, `warehouse_manager`, `cashier`, `accountant`, `customer_support`, and `delivery_supervisor`. Only `super_admin` may assign or modify the protected `super_admin` role.

User and role changes use explicit validation, resource output, transactions for role synchronization, and audit records. Deactivation is preferred over deletion. The final active super administrator cannot be deactivated or demoted.

The first administrator is created interactively with `php artisan app:create-super-admin` after running the migrations and seeders. No default production credentials are generated.
