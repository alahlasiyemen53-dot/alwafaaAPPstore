# Backend Foundation Architecture

## Scope

Sprint 0 establishes conventions only. No catalog, order, inventory, finance, delivery, or customer business module is implemented here.

## DDD viability

DDD is justified because the platform has complex, auditable, fast-changing commerce, inventory, delivery, and finance rules. Sprint 0 uses pragmatic tactical boundaries; it does not introduce CQRS, event sourcing, or speculative domain classes.

## Strategic boundaries

Initial bounded contexts are Catalog, Purchasing, Inventory, Sales, Delivery, Finance, Marketing, Customers, Authentication, Reporting, and Settings. The first core business boundary to implement after Sprint 0 is Authentication and Administration.

## Dependency direction

HTTP/controllers depend on application use cases. Application code may depend on domain-facing contracts. Infrastructure supplies implementations. Domain code must not depend on HTTP, Blade, or cPanel details.

## Conventions

- Controllers receive input, authorize, call a use case, and return a Resource/response.
- Actions orchestrate one use case.
- Services hold reusable cohesive business logic.
- Repositories handle focused persistence/query concerns only; no generic CRUD base repository.
- DTOs contain typed, explicit use-case input; never raw Requests.
- Form Requests validate external input; API Resources control output.
- Multi-table stock, order, and finance operations use database transactions and locking where required.

## Audit decision

Sprint 0 records the audit requirements and boundary but does not add a generic audit package or schema. Each future bounded context will record actor, action, entity, reason, and state changes at its own business boundary; this avoids a large opaque audit framework before concrete events are known.
