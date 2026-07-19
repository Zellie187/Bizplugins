# Notification Architecture

## What is implemented

`BizHub\Workflow\Notifications\WorkflowNotificationListener` is a `Listener` (per BizHub's `BizHub\Framework\Events\Listener` contract) registered against the `WorkflowTransitioned` event by `WorkflowServiceProvider::boot()`. It decides **whether** and **what** to notify; it never sends anything itself.

On every `WorkflowTransitioned` event it:

1. Looks up `config/notifications.php[$workflowType][$action]` — a template keyed first by workflow type, then by action name. If no entry exists for the pair, it silently does nothing (no notification is a valid outcome for most actions).
2. Resolves the recipient as `$workflow->getCreatedBy()`. If that is `<= 0`, it does nothing.
3. Renders the template's `subject` and `body` strings via a simple `strtr()` placeholder substitution: `{workflow_uuid}`, `{action}`, `{reason}`, `{from_status}`, `{to_status}` (the last two rendered via `WorkflowStatus::label()`).
4. Enqueues a `BizHub\Notifications\Notification` onto BizHub's own `NotificationQueue`, requesting both `in_app` and `email` channels.

Delivery — actually sending the email, rendering the in-app notification UI, retrying failures — is entirely BizHub's `NotificationQueue`/`NotificationChannel` infrastructure's responsibility. This plugin does not duplicate that mechanism.

## Company Registration's templates

`config/notifications.php` currently defines templates for seven of Company Registration's nine actions: `request_documents`, `verify_documents`, `request_payment`, `confirm_payment`, `approve`, `cancel`, `reject`. The remaining two actions (`start_quality_review`, `archive`) have no template and therefore raise no notification — an internal housekeeping transition that the workflow's creator does not need to be told about individually. See `docs/workflows/Company-Registration.md` for the exact subject/body text.

## Extending to new workflow types

Adding notifications for a new workflow type is purely a `config/notifications.php` change: add a new top-level key (the new workflow type's `TYPE` constant) mapping action names to `{subject, body}` templates. No code changes to `WorkflowNotificationListener` are required — it is generic across every workflow type by construction.

## What is not implemented

There is currently only one recipient rule (the workflow's creator) and one listener. There is no per-workflow-type customisation of *who* gets notified (e.g. notifying an assigned staff reviewer, or a company's other contacts), no digest/batching logic, and no in-app notification center UI beyond whatever BizHub's `NotificationQueue` already renders generically. A richer recipient-resolution strategy (e.g. a `NotificationRecipientResolver` per workflow type) is a reasonable next step but does not exist yet.
