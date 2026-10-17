# ADR: Durable notifications with after-commit dispatch

**Status:** Accepted — 2026-09-17

## Context

Order transitions must notify customers without making the request wait for
device lookup, Firebase, or provider retries. A committed transition must also
have a durable record that operations can recover if a worker is unavailable.

## Decision

Each authoritative order transaction creates one idempotent logical
`BusinessNotification` record. Once the transaction commits, it queues a
reference-only `SendPushNotificationJob` with `afterCommit()`. The worker,
not the request path, resolves eligible devices, creates delivery attempts,
and contacts Firebase through the provider boundary.

We will not add a separate transactional-outbox subsystem in this sprint. The
order actions already own the relevant transaction, and the durable logical
record plus after-commit job gives a small, recoverable dispatch gap without
duplicating producer and relay infrastructure. The
`notifications:replay-pending` command requeues bounded stale pending or
queued records after validation, covering a process failure in that gap.

## Consequences

- Business responses remain independent of Firebase availability and latency.
- The unique idempotency key prevents a retried transition from creating a
  second logical notification; delivery retries stay attached to that record.
- Operations must run the notifications queue worker and recovery command as
  documented in the deployment runbook.
- Reconsider a full transactional outbox when there are multiple independent
  notification producers or when production evidence demonstrates material
  loss in the narrow post-commit dispatch window.
