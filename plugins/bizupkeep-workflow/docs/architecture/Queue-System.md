# Queue System

**Status: not implemented.** This document is a forward-looking placeholder, not a description of working code.

## What exists today

`includes/Queues/` exists as an empty directory (containing only a `.gitkeep` placeholder) in this version of the plugin. No job class, no queue driver, no worker process, and no scheduled-task runner have been built. Nothing in `Container/definitions.php`, any `Providers/*ServiceProvider`, or `composer.json` references a queue library. Every operation in this plugin today — creating a workflow, transitioning it, notifying its creator — runs synchronously, in-request, as part of the REST call or admin action that triggered it.

## Why this document exists anyway

BH-WORKFLOW-SPEC-001's architecture calls out a queue/job layer as part of the platform's eventual shape (e.g. for asynchronous notification delivery, batch document processing, or scheduled compliance reminders), so `includes/Queues/` was reserved as a directory ahead of time. This file exists so the gap is documented honestly rather than silently, and so a future implementer has a clear starting point.

## What a queue system would need to do, when built

- **Dispatch** — a `Job`-shaped value object (analogous to this engine's `CommandInterface` DTOs) representing one unit of deferred work, e.g. "send this notification" or "run this scheduled reminder."
- **A driver** — most likely built on WordPress's own `wp_schedule_single_event()`/Action Scheduler-style cron dispatch rather than a bespoke worker process, consistent with how WordPress plugins typically defer work without a separate daemon.
- **Registration into BizHub's shared container** — following the exact same pattern this plugin already uses for its other services: a `QueueServiceProvider` registered via `bizhub/register_providers`, bindings contributed via `bizhub/container_definitions`, never a second, competing infrastructure layer.
- **Failure handling and retries** — dead-letter handling and structured logging of failures, following the same `Logger`-based audit conventions already used by `WorkflowManager` (see `docs/security/Audit.md`).
- **Test coverage** — an in-memory fake queue driver, mirroring the `InMemoryDatabase` pattern already used for `WorkflowRepository` tests (see `docs/testing/Integration-Tests.md`), so job dispatch can be asserted without a real cron/worker environment.

Until this is built, do not describe any part of this plugin as "queued," "asynchronous," or "background-processed" — every current behaviour is synchronous.
