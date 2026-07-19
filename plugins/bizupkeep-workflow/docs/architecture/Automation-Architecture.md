# Automation Architecture

**Status: not implemented.** This document describes intended future capability, not existing code.

## What exists today

There is no scheduled-automation code anywhere in this plugin: no cron hooks, no time-based transition triggers, no "auto-archive after N days" logic, and no rule engine beyond the synchronous, request-driven `WorkflowStateMachine` described in `State-Machine.md`. Every state transition in this codebase today happens because a human (or an authenticated API caller acting on a human's behalf) explicitly invoked `WorkflowEngineInterface::transition()`. Nothing currently moves a workflow forward on its own.

## Relationship to the Queue System

Automation, once built, would almost certainly be implemented on top of the queue/job infrastructure described in `Queue-System.md` (itself also not yet implemented) — e.g. a scheduled job that periodically checks for workflows sitting in `AwaitingPayment` past some threshold and transitions them, or nudges a notification. Automation and queueing are two different concerns (queueing is the mechanism for deferred/scheduled execution; automation is the business logic deciding *what* to run and *when*), but the former is a near-certain prerequisite for the latter in this architecture.

## What automation would need to do, when built

- **A trigger model** — time-based (e.g. "N days after entering a status"), event-based (reacting to one of the five events in `Event-System.md` via a dedicated `Listener`, the same pattern `WorkflowNotificationListener` already establishes), or both.
- **A rule definition per workflow type** — following the same per-workflow-type extensibility pattern already used for `WorkflowDefinitionInterface` and `TransitionGuardInterface`: an `AutomationRuleInterface` (or similar) that a concrete workflow type's Service Provider registers with an `AutomationEngine`, mirroring exactly how `CompanyRegistrationServiceProvider::boot()` registers its definition/guard with `WorkflowManager` today.
- **Safety rails** — automated transitions must still go through `WorkflowStateMachine::apply()` and any registered `TransitionGuardInterface`, so an automation rule can never bypass the same structural/business-rule checks a human-initiated transition is subject to. There is no "back door" transition path in this engine, and automation must not introduce one.
- **Auditability** — an automated transition should be indistinguishable, in the audit trail, from a manual one in every field except perhaps `actor_id` (which would need a defined convention for "the system" as an actor — not yet decided).

Until an `AutomationEngine` (or equivalent) is designed and implemented, do not describe any workflow behaviour in this plugin as "automatic," "scheduled," or "self-progressing."
