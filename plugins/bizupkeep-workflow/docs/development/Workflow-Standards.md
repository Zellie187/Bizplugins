# Workflow Standards

This document is the checklist a new concrete workflow type must satisfy, derived from how `CompanyRegistrationDefinition`/`CompanyRegistrationGuard`/`CompanyRegistrationService` are actually built, and from BH-WORKFLOW-SPEC-001 section 6/7's requirements. `docs/workflows/` applies this checklist to the 15 not-yet-built workflow types as design specifications.

## The five pieces every workflow type needs

1. **A `WorkflowDefinitionInterface` implementation** under `includes/Workflows/<Name>/<Name>Definition.php`: a `TYPE` constant, `ACTION_*` constants for every action, `initialStatus()`, and `transitionRules(): array<string,TransitionRule>` — the complete, static lifecycle graph, keyed by action name.
2. **An optional `TransitionGuardInterface` implementation** (`<Name>Guard.php`) for any action whose preconditions the state machine cannot express structurally — a `match ($action)` dispatching to one private method per guarded action, each throwing `PreconditionFailedException` with a clear, safe-to-display message on failure.
3. **A Service class** (`<Name>Service.php`) — the only caller of `WorkflowEngineInterface` for this workflow type, owning an `ALLOWED_ACTIONS` allowlist, a workflow-type guard (`assertIs<Name>()`) preventing cross-type UUID confusion, and any cross-module business rule (e.g. verifying a related entity exists) that is not the engine's concern.
4. **An HTTP layer** — a `Controller` under `includes/Http/Controllers/` and `Request` classes under `includes/Http/Requests/`, following the thin-controller pattern in `docs/development/Coding-Standards.md`, plus routes registered in `routes/api.php`.
5. **A `ServiceProvider`** (`<Name>ServiceProvider.php`) whose `boot()` calls `WorkflowManager::registerDefinition($definition, $guard)`, listed alongside the others in `bizupkeep-workflow.php`'s `bizhub/register_providers` callback.

## Every workflow type's lifecycle must define (BH-WORKFLOW-SPEC-001 §6)

Input, Validation, Preconditions, Business Rules, State Changes, Events Raised, Notifications, Rollback Behaviour, Completion Criteria, and Audit Logging. `docs/workflows/Company-Registration.md` documents all ten for the one implemented workflow; every specified-but-unbuilt workflow doc in `docs/workflows/` follows this same ten-point structure so it is implementation-ready.

## No arbitrary transitions, ever

A workflow definition's `transitionRules()` is the *only* source of truth for what state changes are legal. There must be no code path — in a Guard, a Service, or anywhere else — that mutates a `WorkflowInstance`'s status other than `WorkflowManager::transition()`/`rollback()` going through `WorkflowStateMachine::apply()`. This is what BH-WORKFLOW-SPEC-001 section 7 means by "no arbitrary state transitions should be allowed," and it is enforced structurally, not by code review discipline alone.

## Rollback is mandatory to consider, not mandatory to allow

Every workflow type must have a documented rollback story, even if that story is "cancel is the only way back once payment is confirmed" — see how `Company-Registration.md` documents rollback as reverting exactly one step (to the prior transition's `from` status), unavailable once terminal. A workflow definition is not required to allow rollback from every status, but the *decision* must be explicit and documented, not left implicit.

## Terminal states and completion criteria

Every workflow type needs at least one successful terminal-or-near-terminal status (`isSuccessful()`) and should define its unsuccessful terminal paths (cancellation, rejection) explicitly in its transition rules, following the same `cancellableFrom` array pattern `CompanyRegistrationDefinition` uses to declare "cancel is legal from any of these statuses" in one place.
