# Notification Workflow

**Status: specified, not yet implemented.** This document is deliberately narrower in scope than the others in this directory: it does **not** propose a new stateful `WorkflowDefinitionInterface` type (a notification does not meaningfully have a multi-step lifecycle the way a Company Registration does). Instead it specifies how notification *delivery tracking* could be modeled, if a richer story than `docs/architecture/Notification-Architecture.md`'s current fire-and-forget `WorkflowNotificationListener` is ever needed.

## Why this is not a `WorkflowDefinitionInterface` candidate

Every other document in this directory describes a genuine multi-step business process with meaningful intermediate states. A single notification's lifecycle (queued → sent → delivered/failed) is real but short-lived and high-volume in a way that does not obviously benefit from the full workflow engine's machinery (durable transition audit rows per state change, guard preconditions, rollback) — it more plausibly belongs to BizHub's own `NotificationQueue`/`NotificationChannel` infrastructure directly, tracking delivery status as plain queue/notification metadata rather than as `WorkflowInstance` rows.

## What would need to be decided before building anything here

1. **Does BizHub's own `NotificationQueue` already track delivery state well enough** (queued/sent/failed, retry count) that this plugin needs nothing further? This is the first question to answer — building a redundant tracking layer on top of an already-adequate one should be avoided.
2. **If richer tracking is needed** (e.g. "show me every notification ever sent about this specific workflow instance, with delivery confirmation"), the more consistent design — given this plugin's existing architecture — would be a read-side reporting view joining `bizhub_workflow_transitions` (which already has an implicit notification trigger, since `WorkflowNotificationListener` reacts to `WorkflowTransitioned`) against whatever BizHub's `NotificationQueue` already records, rather than a third table or a new workflow type.

## Current state, for clarity

Today, notification delivery is entirely fire-and-forget from this plugin's perspective: `WorkflowNotificationListener::handle()` enqueues a `Notification` and returns; this plugin has no visibility into whether that notification was subsequently sent, delivered, opened, or failed. See `docs/architecture/Notification-Architecture.md` for exactly what does exist.
