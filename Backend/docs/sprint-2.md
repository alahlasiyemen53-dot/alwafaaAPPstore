# Sprint 2 — Master Data

Implemented under /api/v1/admin and the direct Blade admin panel:

- currencies: CRUD, activation, and exactly one active default currency.
- exchange-rates: historical create/update with positive decimal rates and distinct currency pairs.
- units: CRUD and activation.
- governorates, cities, districts: CRUD with validated parent hierarchy.
- brands: CRUD and activation with unique slugs.
- categories: hierarchical CRUD, cycle prevention, ordered pagination, and child deactivation protection.
- warehouses: CRUD and activation with governorate/city/district consistency checks.
- settings: allow-listed application_name, default_locale, default_currency_id, support_phone, and support_email.

All list endpoints paginate, use allow-listed search fields, and eager-load returned relationships. Changes are written to audit_logs. Sprint 2 permissions are seeded through Laratrust; only super_admin and the existing approved admin role receive the full master-data set.

Reference seed data includes YER/SAR/USD, piece/ml units, Yemen governorates, and basic settings. Cities and districts are intentionally not invented without an approved reliable source.

Blade screens are available under /admin/master-data/{module}. They use Laravel sessions, CSRF, Laratrust middleware, Form Requests, Eloquent, pagination, and escaped output; they do not call the API.
