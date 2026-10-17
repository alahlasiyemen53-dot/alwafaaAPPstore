# Sprint 4 — Product Catalog Foundation

## Sprint goal result

PASSED. Authorized administrators can list, search, filter, sort, paginate, create, view, edit, activate, and deactivate Product catalog identities from the Arabic RTL Admin Panel. Product remains separate from the future sellable `ProductVariant`; Sprint 4 adds no SKU, barcode, price, media, stock, inventory, purchasing, customer API, or Flutter behavior.

## Implemented user stories and features

- US-4.1: paginated Product browsing with name/slug search; Brand, Category, and status filters; and allow-listed name, status, and creation-date sorting.
- US-4.2: Product creation with explicit validated input, active Brand/Category selection, URL-safe unique slug, initial lifecycle status, and the approved `has_variants` capability flag.
- US-4.3: Product identity editing without allowing the general update endpoint to manipulate lifecycle status.
- US-4.4: explicit activate/deactivate commands with transition validation, confirmation feedback, and audit records.
- US-4.5: a details screen containing only Sprint 4 Product information.

## Database changes and Product schema

Migration `2026_09_10_110000_create_products_table.php` creates an InnoDB `products` table with:

- `id`
- nullable `brand_id`, restricted on Brand deletion
- required `category_id`, restricted on Category deletion
- `name_ar`, nullable `name_en`
- database-unique `slug`
- nullable `description_ar`, `description_en`
- indexed `status`, defaulting to `draft`
- boolean `has_variants`, defaulting to false
- timestamps

The live configured MySQL database applied the migration successfully. Foreign-key restrictions and slug uniqueness also have integration coverage. The migration contains no sellable-unit, pricing, media, or inventory columns.

## Domain rules and application architecture

- `ProductStatus` defines `draft`, `active`, and `inactive`; transitions to draft and repeated transitions are rejected.
- `Product` belongs to Brand and Category. Brand and Category expose inverse Product relations.
- `CreateProductData`, `UpdateProductData`, and `ProductListData` carry typed validated input.
- `CreateProductAction`, `UpdateProductAction`, `ActivateProductAction`, and `DeactivateProductAction` coordinate explicit use cases.
- `ProductRepository` owns persistence. `AdminProductQuery` owns the bounded admin listing and selector queries.
- Create, update, and lifecycle audit writes share the same database transaction as the Product mutation.
- `AdminProductWebController` remains an HTTP coordinator; validation, persistence, queries, transitions, and auditing remain outside the views and controller.

## Laratrust permissions and Admin routes

`AuthorizationSeeder` creates and assigns the existing-convention permissions:

- `admin.products.view`
- `admin.products.create`
- `admin.products.update`
- `admin.products.activate`
- `admin.products.deactivate`

Eight session-authenticated, permission-protected routes are registered under `/admin/products`: index, create, store, show, edit, update, activate, and deactivate. No Product customer/API route was introduced.

## Velzon UI and UX review

Implemented Velzon screens:

- Product list with filters, sorting, pagination, empty/no-result state, status badges, and permission-aware actions.
- Create/edit form with labeled fields, old input, field-level validation, hierarchical Category labels, duplicate-submit prevention from the shared UI behavior, and no future-sprint controls.
- Product details with escaped descriptions, identity metadata, status, timestamps, and permitted lifecycle actions.
- Permission-aware Catalog/Product navigation entry.

Arabic RTL: PASS through the shared `lang="ar" dir="rtl"` Velzon layout and RTL assets. Mixed English/slug content uses explicit direction controls.

Responsive: PASS by review of Bootstrap responsive grids, wrapping action groups, responsive tables, and the existing Sprint 3 narrow-layout rules.

Accessibility: PASS for semantic headings/tables/forms, associated labels, required/error feedback, visible textual status labels, descriptive action labels, keyboard-usable links/buttons, and focused confirmation behavior.

Feedback states: PASS for success, validation, empty/no-result, permission-denied, business-conflict, confirmation, and submission-in-progress behavior. Server failures use the existing safe Velzon error layout.

## Security controls and audit

- Authentication plus Laratrust middleware protects every Product route; direct unauthorized record and lifecycle requests return 403.
- Dedicated Form Requests validate all query and mutation input, active foreign references, enum status values, field lengths, URL-safe slug form, and uniqueness.
- Update deliberately excludes `status`; unknown `stock` and `sku` inputs are not persisted.
- Eloquent bindings prevent SQL injection; sorting is allow-listed again in the query object before `orderBy`.
- Blade escapes Product names and descriptions; no untrusted raw HTML rendering is used.
- Session routes retain Laravel CSRF middleware.
- Audit events record Product creation, update before/after identity values, activation, deactivation, actor, request context, and lifecycle before/after status.

Security result: PASS for authorization, IDOR/BOLA, mass-assignment, invalid foreign IDs, invalid/unrestricted status changes, CSRF preservation, and stored-XSS output coverage.

## Search, N+1, and MySQL query review

- Product list pagination is fixed at 20 records and retains query parameters.
- Brand and Category are eager-loaded with only required list columns. Development/test lazy loading prevention remains enabled, and listing rendering passes under it.
- Selector data is fetched in bounded queries; Category hierarchy labels are assembled from one Category collection rather than per-row parent queries.
- `slug` is unique-indexed; Brand and Category foreign keys are indexed by MySQL/Laravel; `status` has an explicit index.
- Stable secondary ID ordering prevents pagination drift when primary sort values tie.
- Leading-wildcard MySQL `LIKE` search is accepted for the current catalog scale; no search engine or speculative full-text index was added.

N+1 review: PASS. MySQL migration/index/query review: PASS.

## Tests and verification

Sprint 4 coverage:

- Unit: six Product lifecycle transition cases.
- Integration: Product create/update relations and audit, lifecycle audit/state, rejected repeated transition, database unique slug, and restricted Category deletion.
- Feature/UI/security: guest and permission denial, end-to-end authorized workflow, invalid foreign/status/slug input, duplicate slug, search/filter/sort, pagination, escaped output, permission-aware actions, and business-conflict feedback.

Final verification:

- `php artisan migrate`: PASS on configured MySQL; Product migration applied.
- `php artisan route:list --except-vendor`: PASS; 155 routes, including eight Product Admin routes.
- `php artisan test`: PASS — 59 tests, 219 assertions.
- `vendor/bin/pint --test`: PASS.
- `vendor/bin/phpstan analyse --no-progress`: PASS with no errors.
- `npm run build`: PASS.

The production build retains the existing informational Vite warnings for public absolute Velzon font/background URLs; the assets remain runtime-resolved and the build succeeds.

## Sprint review and retrospective

Sprint review result: PASS. Automated acceptance demonstrates the authorized list/create/show/edit/activate/deactivate flow, search/filter/sort/pagination, Arabic RTL rendering, validation feedback, hidden read-only actions, and direct unauthorized rejection.

Worked well: Sprint 3's shared Velzon layout, action confirmations, feedback, pagination, and permission conventions supported the catalog screens without another frontend system.

Friction: local `mysql` CLI is unavailable, and `php artisan db:table` cannot format output because the local PHP CLI lacks `ext-intl`. This does not block the successfully applied MySQL migration, source-level schema/index review, or database constraint tests, but installing `ext-intl` would restore Laravel's formatted schema-inspection command.

Deliberately deferred: every Sprint 5+ concern listed in the Sprint contract, including ProductVariant, SKU/barcode, attributes, prices, and inventory.

Known critical/major Product issues: none.

Definition of Done result: PASS.

Final Sprint Status: **PASSED**.
