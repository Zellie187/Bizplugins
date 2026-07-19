# Webhooks

**Status: not implemented.** There is no `routes/webhooks.php` file, no webhook receiver endpoint, and no outbound webhook-dispatch code anywhere in this plugin today. This document describes what such a file/mechanism would be for, should it be built.

## What "webhooks" would mean in this context

Two distinct directions are worth separating, since "webhooks" can mean either:

1. **Inbound webhooks** — a `routes/webhooks.php` receiving HTTP callbacks *into* this plugin from an external system, e.g. a payment gateway confirming a Company Registration's payment (`confirm_payment` action) asynchronously rather than via the authenticated `POST /company-registrations/{uuid}/actions` endpoint a logged-in staff member calls today. This is the more likely near-term need, since `CompanyRegistrationGuard::guardConfirmPayment()` already requires a `payment_reference` in context — a natural source for that reference is a payment provider's own webhook.
2. **Outbound webhooks** — this plugin notifying an external system whenever one of the five workflow events (`WorkflowCreated`, `WorkflowTransitioned`, `WorkflowCompleted`, `WorkflowCancelled`, `WorkflowRolledBack` — see `docs/architecture/Event-System.md`) fires, e.g. so an external CRM stays in sync with a company's registration status.

## What would need to be built

- **Inbound**: a new, unauthenticated-by-necessity REST route (webhook callers are not WordPress-logged-in users) secured by a shared-secret signature verification step instead of `is_user_logged_in()`/`AuthorizationServiceInterface`, translating the external payload into the same `TransitionWorkflowCommand` shape `CompanyRegistrationController::performAction()` already builds, so it goes through the identical `WorkflowStateMachine`/`TransitionGuardInterface` validation path — no special-cased "webhook" transition logic bypassing the engine.
- **Outbound**: a listener (following the exact pattern `WorkflowNotificationListener` already establishes) subscribed to one or more of the five events, translating an event into an outbound HTTP POST to a configured URL, with its own retry/failure-logging story — most likely built on top of the queue system described (and also not yet implemented) in `docs/architecture/Queue-System.md`, since outbound HTTP calls should not block the request that triggered the transition.
- **Configuration** — a `config/webhooks.php` (does not currently exist) analogous to `config/notifications.php`, mapping workflow types/actions to outbound webhook targets, or an admin UI for site owners to register their own endpoints.

## Do not describe any current behaviour as a webhook

Nothing in this plugin currently sends an outbound HTTP request as a side effect of a workflow event, and no route in `routes/api.php` accepts unauthenticated inbound calls. If a payment gateway or other external system needs to drive a transition today, it must do so through the same authenticated `actions` endpoint any other authorized client uses.
