# Sprint 21.5 — FCM Notification Infrastructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `subagent-driven-development` (recommended) or an equivalent task-by-task execution workflow. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a provider-isolated, queue-first FCM push-notification foundation for existing customer order transitions, plus secure Arabic RTL admin operations, without creating customer REST endpoints or Flutter work.

**Architecture:** `Customer` remains the only initial push recipient because it is a distinct model from both admin `User` and `Driver`. The request path writes only one idempotent logical notification inside the authoritative order/delivery transaction and dispatches a reference-only job `afterCommit`; it then returns the business response without resolving devices or contacting FCM. The queue worker later resolves eligible devices, creates per-device deliveries/attempts, calls an application-owned sender contract, and records safe provider results. `FirebaseCloudMessagingSender` is the sole Firebase SDK boundary.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL/InnoDB in production, database queue, Laratrust, Blade/Velzon RTL, Firebase HTTP v1 through `kreait/firebase-php`, PHPUnit.

---

## Pre-implementation decisions and scope

- Initial recipients: active `App\Models\Customer` only. Do not add a notification relation to `User` or `Driver` in this sprint; the polymorphic schema keeps that future extension possible.
- Initial transactional types: `ORDER_CONFIRMED`, `ORDER_ASSIGNED_TO_DRIVER`, `ORDER_DELIVERED`, and `ORDER_CANCELLED`. Do not create an `OUT_FOR_DELIVERY` state because the existing state machine has no such transition. Do not notify `DELIVERY_FAILED` until its customer wording is explicitly approved.
- Add the `MARKETING` category enum/policy boundary, but do not implement campaign, segmentation, preference, topic, email, SMS, WhatsApp, in-app, customer API, or Flutter code.
- ADR decision: do **not** add a second transactional-outbox subsystem. The existing actions already own the database transactions; insert the durable logical notification within that transaction and use `afterCommit()` to enqueue a reference-only job. The unique idempotency key makes a retried transition safe, and an operations command will requeue persisted `pending` records if a process dies in the narrow post-commit dispatch window.
- Request-latency invariant: neither controllers nor actions may resolve `PushDevice`, create per-device delivery attempts, resolve `PushNotificationSender`, connect to Firebase, retry Firebase, or wait for provider work. Those activities belong solely to `SendPushNotificationJob`; the request performs only local notification persistence and post-commit enqueueing.
- Token storage: encrypt `push_devices.token` with Laravel encrypted casting and store `token_hash = hash('sha256', $token)` for indexed lookup/deduplication. Never log, serialize, display, or query by the raw token.
- All normal tests use a fake `PushNotificationSender`; no CI test may make a Firebase call. Add a separately documented staging smoke test for a controlled test device.

## File structure

| Path | Responsibility |
| --- | --- |
| `app/Enums/Notification*.php`, `PushDevice*.php`, `Provider*.php` | Closed vocabularies for type, category, status, channel, device status/platform, and mapped provider outcomes. |
| `app/Models/BusinessNotification.php`, `PushDevice.php`, `NotificationDelivery.php`, `NotificationDeliveryAttempt.php` | Durable records and safe Eloquent relationships. |
| `app/Contracts/Notifications/PushNotificationSender.php` | Provider-neutral application boundary. |
| `app/Data/Notifications/*` | Typed message, send-result, and test-send input DTOs. |
| `app/Services/Notifications/*` | Message construction, logical-notification persistence/dispatch, worker-only recipient/device eligibility, retry and masking policies. |
| `app/Infrastructure/Notifications/FirebaseCloudMessagingSender.php` | Only class allowed to import Firebase/Kreait classes. |
| `app/Jobs/SendPushNotificationJob.php` | Async, worker-only device resolution and per-logical-notification delivery orchestration with bounded retry. |
| `app/Actions/*Order*`, `app/Actions/*Delivery*` | Call the notification scheduler inside their existing transactions; never call Firebase. |
| `app/Http/Controllers/AdminNotificationWebController.php`, `app/Http/Requests/Admin/*Notification*.php` | Authorized list/detail/test/retry/health admin workflow. |
| `resources/views/admin/notifications/*`, `resources/views/admin/partials/sidebar.blade.php`, `routes/web.php` | Arabic RTL operational UI and routes. |
| `database/migrations/*notifications*.php`, `database/seeders/AuthorizationSeeder.php` | New InnoDB tables, indexed constraints and `admin.notifications.*` permissions. |
| `config/services.php`, `.env.example`, `routes/console.php`, `docs/adr/*`, `docs/deployment-runbook.md` | Safe Firebase configuration, recovery command, decision record, and cPanel deployment runbook. |

### Task 1: Establish the ADR, dependency and secure configuration

**Files:**
- Create: `docs/adr/2026-09-17-notification-dispatch.md`
- Modify: `composer.json`, `.env.example`, `config/services.php`, `docs/deployment-runbook.md`
- Test: `tests/Unit/Notifications/FirebaseConfigurationTest.php`

- [ ] **Step 1: Write configuration tests first.** Assert that disabled FCM returns a safe configuration status, while enabled FCM without project ID or credential path throws a project-owned `NotificationConfigurationException`; the assertion text must not contain a credential path or secret.
- [ ] **Step 2: Add `kreait/firebase-php` with Composer, lock it, and run `composer audit`.** Use its HTTP v1/server SDK integration only; do not add a legacy server-key package.
- [ ] **Step 3: Add the `services.fcm` config keys.** Use `enabled`, `project_id`, `credentials_path`, `connect_timeout`, `request_timeout`, and `retry_backoff_seconds`; read them exclusively from `FCM_*` environment keys. `credentials_path` must be a server-private path and be absent from public storage and views.
- [ ] **Step 4: Record the architecture decision.** ADR must state the after-commit durable-record decision, why a full outbox is unnecessary now, the replay/recovery command, and the future trigger for reconsidering an outbox (multiple independent producers or demonstrable post-commit loss).
- [ ] **Step 5: Document cPanel deployment.** Specify an every-minute cron using `php artisan schedule:run`, a scheduled bounded `queue:work database --queue=notifications --stop-when-empty --tries=3 --max-time=50`, `queue:failed`, `queue:retry`, and the replay command. Require a non-production Firebase project for staging and `APP_DEBUG=false` in production.
- [ ] **Step 6: Run `php artisan test --filter=FirebaseConfigurationTest` and commit** with message `feat: configure FCM notification provider`.

### Task 2: Add the notification persistence schema and enum vocabulary

**Files:**
- Create: `database/migrations/2026_09_22_100001_create_notification_infrastructure_tables.php`
- Create: `app/Enums/NotificationCategory.php`, `NotificationChannel.php`, `NotificationStatus.php`, `NotificationType.php`, `PushDeviceStatus.php`, `PushDevicePlatform.php`, `ProviderFailureCategory.php`
- Create: `app/Models/BusinessNotification.php`, `PushDevice.php`, `NotificationDelivery.php`, `NotificationDeliveryAttempt.php`
- Modify: `app/Models/Customer.php`
- Test: `tests/Feature/Notifications/NotificationSchemaTest.php`

- [ ] **Step 1: Write failing schema tests** for an active device uniqueness collision, duplicate logical notification idempotency collision, cascade-safe relationships, enum casts, and encrypted token serialization.
- [ ] **Step 2: Create the four InnoDB tables in a new migration.**
  - `push_devices`: nullable morph owner (`owner_type`, `owner_id`), `provider`, encrypted `token`, 64-char `token_hash`, nullable `device_identifier`, platform, locale, app version, status, registration/seen/invalidation timestamps, and safe `invalidation_reason`.
  - `business_notifications`: nullable morph recipient, type/category/channel/status, title/body, JSON `payload`, `payload_version`, safe `business_reference`, 64-char unique `idempotency_key`, timestamps for queued/sent/failed/cancelled, and safe failure category/summary.
  - `notification_deliveries`: notification/device FK, channel/provider/status, retryable flag, final provider reference, safe failure fields, and sent/failed timestamps; created only by the queue worker after it resolves an eligible device; unique `(business_notification_id, push_device_id)`.
  - `notification_delivery_attempts`: delivery FK, attempt number, status, mapped failure category, safe provider reference/summary, and `attempted_at`; unique `(notification_delivery_id, attempt_number)`.
- [ ] **Step 3: Add query-driven indexes.** Include token hash lookup, owner/provider/status, notification `(status, created_at)`, `(type, category, channel, created_at)`, safe business reference, delivery `(status, created_at)`, and attempt `(notification_delivery_id, attempted_at)`. Do not use a partial unique index—MySQL does not support it; invalidating a token retains the row while a unique provider/token hash prevents shared active ownership.
- [ ] **Step 4: Implement models with guarded persistence.** `PushDevice::$casts` must use Laravel `encrypted` for `token`; none of the models expose a raw token in `$fillable`, `toArray`, resource, audit metadata, or accessors. Add only `Customer::pushDevices()` and `Customer::businessNotifications()` at this stage.
- [ ] **Step 5: Run the schema test on SQLite for feedback and on the configured MySQL test database before acceptance.** Run `php artisan test --filter=NotificationSchemaTest`, then `DB_CONNECTION=mysql DB_DATABASE=lamma_test php artisan test --filter=NotificationSchemaTest` after provisioning the isolated test database.
- [ ] **Step 6: Commit** with `feat: persist notification devices and delivery lifecycle`.

### Task 3: Implement neutral DTOs, payload policy, and device lifecycle service

**Files:**
- Create: `app/Data/Notifications/PushMessageData.php`, `PushSendResultData.php`, `RegisterPushDeviceData.php`, `TestNotificationData.php`
- Create: `app/Services/Notifications/NotificationMessageFactory.php`, `NotificationPayloadPolicy.php`, `PushDeviceLifecycleService.php`, `PushDeviceEligibilityService.php`, `TokenMasker.php`
- Test: `tests/Unit/Notifications/NotificationMessageFactoryTest.php`, `NotificationPayloadPolicyTest.php`, `PushDeviceLifecycleServiceTest.php`, `TokenMaskerTest.php`

- [ ] **Step 1: Add failing unit cases for every initial type.** Each must produce Arabic customer-safe title/body, `payload_version=1`, `type`, `order_reference`, and current safe status only. Reject an unknown key, nested admin payload, an invalid category/type pairing, a title above 120 characters, or body above 500 characters.
- [ ] **Step 2: Define the type registry in `NotificationType`.** Map the four types to `Transactional`, `PushFcm`, exact safe payload keys, and centralized Arabic builders. `ORDER_ASSIGNED_TO_DRIVER` must say that the order is on its way without exposing the driver’s internal data; do not send finance, sourcing, supplier, or raw numeric identifiers.
- [ ] **Step 3: Implement lifecycle handling transactionally.** Hash before lookup, lock the matching device row, allow an existing token to move to the authenticated/approved owner only through the explicit service, reactivate only after safe verification, and invalidate/provider-reject without deleting historical deliveries. API endpoints remain absent.
- [ ] **Step 4: Implement eligibility and masking.** Eligible means `active`, FCM provider, non-null token, and an active customer owner. Mask only a small prefix/suffix, e.g. `abcd…wxyz`; null/short values must never reveal more than the masking policy permits.
- [ ] **Step 5: Run the four unit files and commit** with `feat: add safe notification message and device policies`.

### Task 4: Implement the sender contract, Firebase adapter, and provider result mapping

**Files:**
- Create: `app/Contracts/Notifications/PushNotificationSender.php`
- Create: `app/Infrastructure/Notifications/FirebaseCloudMessagingSender.php`, `NotificationConfigurationException.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Notifications/FirebaseCloudMessagingSenderTest.php`

- [ ] **Step 1: Write contract tests against a fake sender.** Test mapping of success, unregistered token, invalid payload, authentication/configuration, timeout/unavailable, and unexpected provider failure into project-owned `PushSendResultData` categories.
- [ ] **Step 2: Define the neutral contract.** It accepts `PushMessageData` plus a decrypted device token only at the infrastructure call boundary and returns `PushSendResultData`; neither public callers nor jobs receive a Kreait response object.
- [ ] **Step 3: Implement the adapter as the only Firebase import location.** Build one minimal Android/iOS-neutral message from the allowlisted DTO, use configured bounded connect/request timeouts, and map provider exceptions centrally. It must fail clearly when enabled credentials are unavailable and must never log message text, token, credentials, or raw exception response.
- [ ] **Step 4: Bind the contract in the container.** Bind the FCM adapter in production and a deterministic fake only through test setup; no controller/action may resolve Firebase directly.
- [ ] **Step 5: Run adapter tests and `composer analyse`; commit** with `feat: isolate Firebase HTTP v1 delivery adapter`.

### Task 5: Add request-latency-safe scheduling, worker delivery, and safe retry behavior

**Files:**
- Create: `app/Services/Notifications/NotificationScheduler.php`, `NotificationDeliveryService.php`, `NotificationRetryPolicy.php`
- Create: `app/Jobs/SendPushNotificationJob.php`
- Create: `app/Console/Commands/ReplayPendingNotificationsCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Notifications/NotificationQueueLifecycleTest.php`, `RequestLatencyIsolationTest.php`, `MultipleDeviceDeliveryTest.php`, `InvalidTokenDeliveryTest.php`, `TransientFailureRetryTest.php`

- [ ] **Step 1: Write feature tests with `Queue::fake()` and a sender fake.** Cover one logical record for a repeated idempotency key, no dispatch before a rolled-back transaction, no `PushDevice` query or sender resolution while scheduling, two eligible devices/two worker-created deliveries, an inactive device skipped, permanent invalidation of one device only, and transient retry with no duplicate notification.
- [ ] **Step 2: Add the mandatory slow-provider latency-isolation test.** Configure the fake sender to block/sleep only if called. Execute an approved business HTTP/action path with `Queue::fake()`, assert the success response/action completes without calling that fake, assert a single `SendPushNotificationJob` was queued after commit, then explicitly run the queued job and assert the slow fake is called only in that worker execution.
- [ ] **Step 3: Implement `NotificationScheduler::scheduleOrderNotification`.** It creates or returns only the logical record by idempotency key inside the caller’s transaction, marks it `queued`, and invokes `SendPushNotificationJob::dispatch($id)->onQueue('notifications')->afterCommit()`. Its method must not load `pushDevices`, create `notification_deliveries`, resolve the sender contract, call FCM, or retry provider work.
- [ ] **Step 4: Implement worker-only device resolution and per-delivery job handling.** `SendPushNotificationJob` reloads the logical notification by ID, resolves eligible devices after the request is over, creates each idempotent delivery, locks it, appends an attempt, sends with the contract, and updates only that delivery. Mark a permanent token rejection invalid with safe reason; for transient outcomes throw/release with the exact bounded backoff `[60, 300, 900]`; never retry configuration, payload, or permanent token failures.
- [ ] **Step 5: Aggregate the logical notification safely.** `sent` means at least one provider-accepted device delivery; `failed` only when no pending/retryable delivery remains. It must not mean read/open/clicked.
- [ ] **Step 6: Add the replay command.** It must select only bounded, stale `pending`/`queued` records with retryable work, dispatch reference-only jobs by ID after validation, be idempotent, and output counts without recipient/token data.
- [ ] **Step 7: Run the five feature files and commit** with `feat: queue latency-isolated FCM notification delivery`.

### Task 6: Connect only approved committed order transitions

**Files:**
- Modify: `app/Actions/ConfirmOrderAction.php`, `CancelOrderAction.php`, `AssignOrderToDriverAction.php`, `MarkDeliveryDeliveredAction.php`
- Test: `tests/Feature/Notifications/OrderNotificationIntegrationTest.php`

- [ ] **Step 1: Write tests around actual actions.** Confirm, assign to driver, deliver, and cancel must create exactly one matching notification per order transition. A forced exception before commit must leave no notification. A sender failure must leave the committed order/delivery state unchanged.
- [ ] **Step 2: Inject `NotificationScheduler` into the four action constructors.** Invoke it after the authoritative status update but before the transaction closure returns; because the scheduler dispatches after commit, the action returns normally without resolving devices, instantiating the sender, or performing an FCM network call.
- [ ] **Step 3: Use stable keys.** Format `order:{order_id}:{notification_type}:v1`; never use a request UUID or timestamp. The existing idempotent early returns must not schedule another record.
- [ ] **Step 4: Do not change `MarkDeliveryFailedAction` or any sourcing/promotion/wallet action.** This explicitly enforces the approved event scope and customer-safe sourcing boundary.
- [ ] **Step 5: Run the integration file plus existing order/delivery regression tests; commit** with `feat: notify customers after committed order transitions`.

### Task 7: Add authorization, queries, admin validation, and operations controller

**Files:**
- Create: `app/Http/Controllers/AdminNotificationWebController.php`
- Create: `app/Http/Requests/Admin/SendTestNotificationRequest.php`, `RetryNotificationRequest.php`, `NotificationLogFilterRequest.php`
- Create: `app/Queries/Notifications/NotificationLogQuery.php`
- Modify: `database/seeders/AuthorizationSeeder.php`, `routes/web.php`
- Test: `tests/Feature/Notifications/AdminNotificationAuthorizationTest.php`, `AdminNotificationValidationTest.php`

- [ ] **Step 1: Add failing feature tests.** Verify distinct permissions for view, failure view, test send, retry, and manage; unauthorized requests return 403; date range errors attach to the Arabic field; an inactive/foreign device cannot be selected; duplicate test submit is blocked; tokens are absent from HTML.
- [ ] **Step 2: Add existing-style permission names.** Use `admin.notifications.view`, `admin.notifications.view_failures`, `admin.notifications.send_test`, `admin.notifications.retry`, and `admin.notifications.manage`; assign view to the support/operations roles only where justified, and privileged mutation rights only to admin/super-admin unless the owner approves wider roles.
- [ ] **Step 3: Implement bounded filter query.** Permit only enum values, safe reference search, inclusive dates, and `paginate(25)`. Eager-load recipient and device counts/last delivery summaries so the log has no N+1 query loop.
- [ ] **Step 4: Implement test send through the scheduler.** Accept an existing eligible device ID plus bounded title/body; construct a dedicated `TEST_NOTIFICATION` message with only its defined keys, rate-limit per admin user, audit `admin.notification.test_sent`, and enqueue using the same job/contract.
- [ ] **Step 5: Implement manual retry.** Re-check authorization, relevance, device eligibility, and retryable category; dispatch existing delivery work only, never clone the logical notification. Audit `admin.notification.retry_requested` and reject permanent invalid-token/configuration failures.
- [ ] **Step 6: Add safe configuration health endpoint/page data.** Report only enabled/configured/queue-connection booleans—never paths, project secrets, values, or live sends.
- [ ] **Step 7: Run the two feature files and commit** with `feat: add authorized notification operations`.

### Task 8: Build Arabic RTL Velzon admin operations UI

**Files:**
- Create: `resources/views/admin/notifications/index.blade.php`, `show.blade.php`, `_filters.blade.php`, `_test-send-form.blade.php`
- Modify: `resources/views/admin/partials/sidebar.blade.php`, `resources/views/admin/dashboard.blade.php`
- Test: `tests/Feature/Notifications/AdminNotificationViewTest.php`

- [ ] **Step 1: Write view assertions.** Ensure the page is authorization-gated, all status labels are Arabic and do not say read/received, the token/masked diagnostics contain no raw token, and escaped title/body renders safely.
- [ ] **Step 2: Create the paginated log.** Include filters for type/category/channel/status/date range/reference, cards for queued/sent/failed/invalid-device counts, empty/error/success states, and masked device diagnostics only under failure-view permission.
- [ ] **Step 3: Create detail and failure sections.** Show safe reference, provider, attempt number/count, mapped failure category, attempted time, and retry eligibility. Do not show provider raw response, stack trace, credential JSON, access token, or full token.
- [ ] **Step 4: Add test/retry UI controls.** Use CSRF, current inline field errors, submit-disable behavior, server-side rate-limit messages, and confirmation text for retry. The test recipient control is a server-rendered list of eligible known devices—not a free-form token/recipient input.
- [ ] **Step 5: Add the sidebar/dashboard entry under an existing appropriate operations area**, guarded by `admin.notifications.view`.
- [ ] **Step 6: Run the view test, format Blade/PHP as project convention requires, and commit** with `feat: add Arabic notification operations UI`.

### Task 9: Complete observability, retention policy, and operational verification

**Files:**
- Modify: `app/Services/Notifications/NotificationDeliveryService.php`, `docs/deployment-runbook.md`
- Create: `docs/notifications-operations.md`
- Test: `tests/Feature/Notifications/NotificationSecurityRegressionTest.php`

- [ ] **Step 1: Add security regression tests.** Test token absence from logs/audit/view response, safe payload-key rejection, XSS escaping in test content, CSRF protection, SQL-safe filter behavior, unauthorized retry/send test, and cross-owner device rejection.
- [ ] **Step 2: Add structured safe logs.** Restrict fields to notification ID, type, provider, attempt number, masked recipient reference, and mapped error category. Review each log/audit call to exclude token, title/body, payload, credentials, headers, raw FCM response, and stack trace in UI.
- [ ] **Step 3: Document retention without destructive automation.** Configure/describe retained logical records, attempts, invalid devices, and failure summaries; do not add a deletion task until the owner approves durations. State that completed FCM send is not proof of read/open/click.
- [ ] **Step 4: Run the security regression test and commit** with `docs: document notification operations and retention`.

### Task 10: Execute acceptance checks and staging smoke test

**Files:**
- Modify: `docs/notifications-operations.md`
- Test: all notification tests and existing affected regression tests

- [ ] **Step 1: Run focused unit and feature suites.**
  ```powershell
  php artisan test --filter=Notification
  php artisan test --filter='Order.*|Delivery.*'
  composer analyse
  composer audit
  ```
  Expected: all pass; no live Firebase request.
- [ ] **Step 2: Execute acceptance scenario K in the automated suite.** With a slow/timeout sender fake, assert that the approved business operation completes and returns success while the fake has zero calls, that exactly one reference-only notification job is queued after commit, and that only invoking the worker job can call the fake and record/retry its failure. A response/action that waits for the fake is a release-blocking failure.
- [ ] **Step 3: Run migration and notification schema tests against an isolated MySQL database.** Confirm unique/index semantics, encrypted casting, transactions, and queue records match MySQL behavior rather than relying only on the current SQLite `phpunit.xml` baseline.
- [ ] **Step 4: Perform staging-only smoke verification.** Use an authorized admin, a controlled registered test device, staging credentials, database queue worker/cron, and the test-send UI. Verify one received test notification, safe dashboard/log data, and no production customer/device receives it.
- [ ] **Step 5: Execute the cPanel recovery drill.** Stop the worker, create a queued test notification, run the documented cron/replay command, and verify exactly-once logical notification semantics with delivery attempts—not a duplicate logical record.
- [ ] **Step 6: Record results and owner acceptance evidence** in `docs/notifications-operations.md`; commit with `test: verify Sprint 21.5 notification acceptance`.

## Self-review

### Spec coverage

- Provider isolation, HTTP v1, credentials, timeout, response mapping: Tasks 1 and 4.
- Immediate customer response, no synchronous device resolution/FCM, and slow-provider latency isolation: Tasks 5, 6, and 10.
- Device lifecycle, encryption/hash deduplication, multiple platforms/devices, invalidation: Tasks 2 and 3.
- Queue-first, after-commit, idempotency, retry, failed-job recovery, cPanel operations: Tasks 1, 5, 6, and 10.
- Categories, payload/version/allowlist, localization/deep-link readiness, no fabricated receipt status: Task 3.
- Approved order events and Sprint 21.4 sourcing/privacy boundaries: Task 6.
- Velzon RTL admin log/test/retry, permissions, validation, audit/rate limiting: Tasks 7 and 8.
- Security, observability, retention, N+1 and acceptance testing: Tasks 7–10.
- Explicit exclusions: no customer API route, Flutter code, marketing campaign engine, client preference flow, FCM topics, or generic multi-provider implementation.

### Consistency checks

- The model name `BusinessNotification` is used consistently to avoid colliding with Laravel’s `Notification` namespace/table assumptions.
- The existing order transitions map exactly to `ConfirmOrderAction`, `AssignOrderToDriverAction`, `MarkDeliveryDeliveredAction`, and `CancelOrderAction`.
- Every scheduled event uses a stable order/type/version key; queue retries operate on deliveries, never recreate logical notifications.
- The request path creates only a logical notification and queues its ID after commit; device resolution, delivery creation, FCM calls, and retries run only in the queue worker.
- No plan step instructs the developer to modify existing migrations, add a raw customer token field, send in a transaction, call Firebase from the request/controller/business path, or expose credentials/tokens.

Plan complete and saved to `Backend/docs/superpowers/plans/2026-09-17-sprint-21-5-fcm-notification-infrastructure.md`.
