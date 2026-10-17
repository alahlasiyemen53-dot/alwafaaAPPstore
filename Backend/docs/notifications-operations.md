# Notification Operations (Sprint 21.5 — FCM Notification Infrastructure)

Extends `docs/deployment-runbook.md` and `docs/adr/2026-09-17-notification-dispatch.md`
with the day-to-day operational reference for the notification subsystem.

## What this subsystem is

`BusinessNotification` is the durable, provider-neutral logical record for a
customer-facing push. `NotificationDelivery` / `NotificationDeliveryAttempt`
are per-device delivery state, created only by `SendPushNotificationJob`
(never by the HTTP request path). `FirebaseCloudMessagingSender` is the only
class allowed to talk to Firebase; business Actions call
`NotificationScheduler` only.

MySQL remains the source of business truth at all times. A Firebase outage
never rolls back or blocks an Order/Delivery transition — it only delays or
fails the notification record, which is retried or surfaced as `FAILED`.

## Configuration health

`services.fcm.*` (`FCM_ENABLED`, `FCM_PROJECT_ID`, `FCM_CREDENTIALS_PATH`,
timeouts, retry backoff) is read once from environment. The Admin
Notification Log page displays only booleans (`enabled`, `has_credentials`,
`project_id present`, `queue_connection`) — never the credential path, the
project ID's value, or any secret. Disabling `FCM_ENABLED` stops outbound
Firebase calls without disabling Orders, Deliveries, or any other business
operation; queued notification jobs will simply fail with a configuration
error classified as non-retryable until re-enabled.

## Queue and cron (cPanel)

See `docs/deployment-runbook.md` step 10 for the exact cron lines. In short:

```
* * * * * php artisan schedule:run
* * * * * php artisan queue:work redis --queue=notifications,search --sleep=3 --tries=3 --max-time=55
```

Local development (keep the terminal open):

```
php artisan queue:work redis --queue=notifications,search --tries=3
```

Do **not** use `queue:work database` — the database queue driver is removed.
Recovery commands:

- `php artisan queue:failed` — list failed notification jobs (no token/body
  content is stored in the failed-job payload beyond the notification ID).
- `php artisan queue:retry <id>` — retry one failed job.
- `php artisan notifications:replay-pending --limit=100` — requeues
  `PENDING`/`QUEUED` notifications whose `updated_at` is more than 5 minutes
  old (i.e. a worker died in the narrow post-commit dispatch window). Safe to
  run repeatedly: it only dispatches jobs by ID, and delivery/idempotency
  logic inside `SendPushNotificationJob` prevents duplicate logical
  notifications or duplicate deliveries per device.

## Retention policy

No destructive automated cleanup is introduced by this Sprint. Current
posture, pending explicit Owner-approved retention durations:

- `business_notifications`, `notification_deliveries`,
  `notification_delivery_attempts`: retained indefinitely for audit/
  diagnostics. Rows are never hard-deleted by the application; a `Failed`
  or `Cancelled` notification stays queryable in the Admin log.
- Invalidated `push_devices` rows are kept (status = `invalidated`, with
  `invalidation_reason` and `invalidated_at`) rather than deleted, so
  historical deliveries keep a valid foreign key and diagnostics stay
  intact.
- If/when a retention duration is approved, add a new scheduled command
  that only prunes rows older than the approved window — do not repurpose
  an existing command for this, and never delete `Queued`/`Pending` rows
  regardless of age (use the replay command instead).

## What "Sent" does and does not mean

`SENT` means the FCM HTTP v1 endpoint accepted the message for a given
device. It is **not** read/opened/clicked. The Admin UI never claims
otherwise; those states are out of scope until a future authenticated
client/API event exists.

## Known issues / explicit exclusions

- No `/api/v1` customer endpoints and no Flutter integration exist yet —
  device registration in this Sprint is seeded/managed only through
  authorized internal tooling for the Admin test-send flow, not through a
  public API.
- A full marketing campaign/segmentation engine, FCM topic messaging, and
  customer consent/preferences are intentionally not implemented (see
  Sections 34–35 of `.ai/Sprints/sprint_21_5.md`).
- **Real-FCM staging smoke test**: this environment has no reachable
  Firebase project or physical/emulated test device, so a live end-to-end
  push against real Firebase infrastructure has not been executed from this
  session. All automated tests (71 notification tests, including the
  provider-contract, latency-isolation, and security-regression suites) use
  a fake `PushNotificationSender` and never call live Firebase, per Section
  65/68's CI requirement. Before this Sprint is marked `PASSED` in the
  Definition of Done sense, the Owner (or an operator with real staging
  Firebase credentials and a registered test device) must run the
  Section 96 production/staging smoke test procedure below and record the
  result in this file.

## Staging smoke test procedure (manual — requires real credentials)

1. Configure a **non-production** Firebase project in `.env`
   (`FCM_ENABLED=true`, staging `FCM_PROJECT_ID`, a server-private
   `FCM_CREDENTIALS_PATH` outside the web root).
2. Register one authorized internal test device (a real or emulator app
   instance holding a valid FCM registration token) against a seeded test
   `Customer` through an authorized internal mechanism — not a public API.
3. As an authorized Admin, open **الإشعارات → إرسال إشعار تجريبي**, select
   that device, and submit a bounded test title/body.
4. Confirm: the HTTP response returns immediately (does not wait on
   Firebase); `php artisan queue:work redis --queue=notifications,search` (or
   the cron-driven worker) picks up the job; the test device receives the
   push; the Admin log shows the notification transition to `SENT`; no
   token or credential value appears anywhere in the response, logs, or
   audit trail.
5. Record the date, Firebase project (staging), and result below.

### Results log

| Date | Environment | Result | Operator |
| --- | --- | --- | --- |
| _pending_ | staging | Not yet executed — requires real Firebase project/credentials and a registered test device not available in this development session. | — |

## Automated verification already performed in this session

```
php artisan test --filter=Notification      # 76 passed (see test files below)
./vendor/bin/pint --test                     # passed, project-wide
```

Covered test files:

- `tests/Unit/Notifications/FirebaseConfigurationTest.php`
- `tests/Unit/Notifications/NotificationMessageFactoryTest.php`
- `tests/Unit/Notifications/NotificationPayloadPolicyTest.php`
- `tests/Unit/Notifications/PushDeviceLifecycleServiceTest.php`
- `tests/Unit/Notifications/PushNotificationSenderFakeTest.php`
- `tests/Unit/Notifications/TokenMaskerTest.php`
- `tests/Unit/Notifications/FirebaseCloudMessagingSenderTest.php`
- `tests/Feature/Notifications/NotificationSchemaTest.php`
- `tests/Feature/Notifications/NotificationQueueLifecycleTest.php`
- `tests/Feature/Notifications/OrderNotificationIntegrationTest.php`
- `tests/Feature/Notifications/RequestLatencyIsolationTest.php`
- `tests/Feature/Notifications/AdminNotificationAuthorizationTest.php`
- `tests/Feature/Notifications/NotificationSecurityRegressionTest.php`

Not executed in this session (require real MySQL or a live Firebase
project, neither reachable here): the MySQL-specific schema/unique-index
re-run called for in Task 10 Step 3, and the real-FCM staging smoke test
above.
