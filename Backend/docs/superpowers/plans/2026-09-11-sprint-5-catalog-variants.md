# Sprint 5 Catalog Variants Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `ProductVariant` the sellable catalog unit with reusable attributes, normalized values, one default variant per configured product, and manual prices per active currency.

**Architecture:** Extend the existing Sprint 4 Laravel action/DTO/query pattern. Database constraints backstop SKU, barcode, assignment, and per-currency price uniqueness; transactional actions lock a product's variants while changing the default or comparing combinations. Existing Velzon Blade components and Laratrust middleware remain the presentation and authorization foundation.

**Tech Stack:** Laravel, Eloquent, MySQL/InnoDB, Laratrust, Blade, Velzon RTL, PHPUnit, Pint, Larastan, Vite.

---

### Task 1: Schema, models, enums, and invariant tests

**Files:**
- Create: `database/migrations/2026_09_11_000000_create_product_variant_tables.php`
- Create: `app/Enums/ProductVariantStatus.php`
- Create: `app/Models/Attribute.php`
- Create: `app/Models/AttributeValue.php`
- Create: `app/Models/ProductVariant.php`
- Create: `app/Models/VariantPrice.php`
- Modify: `app/Models/Product.php`
- Modify: `app/Models/Currency.php`
- Test: `tests/Feature/ProductVariantSchemaTest.php`
- Test: `tests/Unit/ProductVariantRulesTest.php`

- [ ] Write failing tests proving unique SKU, nullable-unique barcode, one value per attribute, value/attribute consistency, one price per currency, restricted foreign keys, decimal money, and no stock/media columns.
- [ ] Add the five InnoDB tables with explicit indexes and foreign keys. Use `DECIMAL(18,4)` for `price` and `compare_at_price`; omit `cost_price` and `weight` because the approved context does not require them now.
- [ ] Add typed casts and only the relationships used by Sprint 5.
- [ ] Implement the minimal variant status enum and pure compare-at validation rule.
- [ ] Run `php artisan test --filter='ProductVariantSchema|ProductVariantRules'` and require PASS.

### Task 2: Attribute and value management

**Files:**
- Create: `app/Data/AttributeData.php`
- Create: `app/Data/AttributeValueData.php`
- Create: `app/Actions/CreateAttributeAction.php`
- Create: `app/Actions/UpdateAttributeAction.php`
- Create: `app/Actions/SetAttributeStatusAction.php`
- Create: `app/Actions/CreateAttributeValueAction.php`
- Create: `app/Actions/UpdateAttributeValueAction.php`
- Create: `app/Actions/SetAttributeValueStatusAction.php`
- Create: `app/Queries/AdminAttributeQuery.php`
- Create: `app/Http/Requests/Admin/*Attribute*Request.php`
- Create: `app/Http/Controllers/AdminAttributeWebController.php`
- Create: `resources/views/admin/attributes/index.blade.php`
- Create: `resources/views/admin/attributes/form.blade.php`
- Create: `resources/views/admin/attributes/values.blade.php`
- Modify: `database/seeders/AuthorizationSeeder.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/partials/sidebar.blade.php`
- Test: `tests/Feature/AdminAttributeWebTest.php`

- [ ] Write failing behavior tests for authorized CRUD/lifecycle, unauthorized 403, active filters, pagination, XSS escaping, unique codes, and values scoped to their parent attribute.
- [ ] Implement validated DTO/action flows with audit records inside transactions; do not delete inactive historical values.
- [ ] Implement bounded queries using `withCount('values')`, allow-listed sort, and server pagination.
- [ ] Add exact `admin.attributes.*` and `admin.attribute_values.*` permissions and middleware-protected routes.
- [ ] Build Arabic RTL Velzon list/forms/value management with permission-aware actions and existing feedback/confirmation behavior.
- [ ] Run `php artisan test tests/Feature/AdminAttributeWebTest.php` and require PASS.

### Task 3: Variant creation, assignments, default switch, and prices

**Files:**
- Create: `app/Data/ProductVariantData.php`
- Create: `app/Data/VariantPriceData.php`
- Create: `app/Actions/CreateProductVariantAction.php`
- Create: `app/Actions/UpdateProductVariantAction.php`
- Create: `app/Actions/SetProductVariantStatusAction.php`
- Create: `app/Actions/SetDefaultProductVariantAction.php`
- Create: `app/Queries/AdminProductVariantQuery.php`
- Create: `app/Http/Requests/Admin/*ProductVariant*Request.php`
- Create: `app/Http/Controllers/AdminProductVariantWebController.php`
- Create: `resources/views/admin/products/variants/index.blade.php`
- Create: `resources/views/admin/products/variants/form.blade.php`
- Modify: `resources/views/admin/products/show.blade.php`
- Modify: `database/seeders/AuthorizationSeeder.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ProductVariantActionsTest.php`
- Test: `tests/Feature/AdminProductVariantWebTest.php`

- [ ] Write failing tests for atomic create/update, wrong-attribute values, repeated attribute values, duplicate combinations, unique SKU/barcode, price rules, invalid/inactive currencies, rollback, cross-product routes, unauthorized mutations, default switching, and simple-product default variant creation.
- [ ] Map validated nested input into typed DTOs; normalize SKU/barcode consistently and never trust client prices outside the validated set.
- [ ] In one transaction lock the product's variant rows, validate assignments, reject a duplicate sorted value-ID combination, persist assignments/prices, and atomically unset/set the default.
- [ ] Enforce exactly one default once setup starts: the first variant becomes default; deleting variants is not added; deactivating the only/default variant is rejected unless another active default is selected atomically.
- [ ] Render active currencies from `currencies`, format by `decimal_places`, and store explicit prices only; do not use exchange rates.
- [ ] Add nested, scoped product/variant routes with Laratrust middleware and explicit cross-product rejection.
- [ ] Build Velzon RTL variant list/form/details integration without stock, images, or Sprint 6 placeholders.
- [ ] Run the two focused variant test files and require PASS.

### Task 4: Regression, quality gates, and Sprint report

**Files:**
- Modify: `.ai/Sprints/revised_agile_scrum_roadmap.md`
- Create: `docs/sprint-5.md`
- Modify: `README.md` only if its implemented-scope summary requires synchronization.

- [ ] Run `php artisan migrate` against configured MySQL and inspect the created columns/indexes/constraints through Laravel schema APIs when CLI tooling is unavailable.
- [ ] Run `php artisan route:list --except-vendor`, `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --no-progress`, and `npm run build`.
- [ ] Review Product→Variants→Values and Prices→Currency queries for N+1 and bounded loading.
- [ ] Apply clean-code-guard, test-guard, and docs-guard; fix every blocking finding and rerun affected checks.
- [ ] Mark Sprint 5 PASSED in the roadmap and report only when every mandatory Definition of Done item is evidenced; otherwise record NOT COMPLETE with exact blockers.

## Self-review

- Spec coverage: schema, invariants, attributes/values, variants, pricing, permissions, Velzon RTL, security, concurrency, tests, regression, and reporting are mapped above.
- Scope exclusions: no media, inventory, purchasing, cart/order, customer API, Flutter, automatic FX pricing, cost price, or weight.
- Placeholder scan: no TBD/TODO implementation steps remain.
- Type consistency: all HTTP input enters dedicated requests, then typed DTOs; actions own transactions and audit; query objects own bounded reads.
