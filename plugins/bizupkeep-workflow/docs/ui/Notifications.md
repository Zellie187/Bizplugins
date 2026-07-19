# Notifications UI

**Status: not implemented (UI side).** The notification-generation *logic* is real and implemented — see `docs/architecture/Notification-Architecture.md` — but this plugin renders no notification UI of its own. `WorkflowNotificationListener` enqueues a `BizHub\Notifications\Notification` onto BizHub's shared `NotificationQueue`; any in-app notification bell/list/email template rendering is entirely BizHub's `BizHub\Notifications` module's responsibility, not this plugin's. This document is a brief for what this plugin's notification *content* should look like wherever BizHub's own notification UI renders it, not a plan to build a competing notification UI.

## What is actually enqueued today

For seven of Company Registration's nine actions (`request_documents`, `verify_documents`, `request_payment`, `confirm_payment`, `approve`, `cancel`, `reject`), a notification is enqueued to the workflow's creator (`$workflow->getCreatedBy()`) on both `in_app` and `email` channels, with subject/body text defined in `config/notifications.php`. `start_quality_review` and `archive` intentionally raise no notification — internal housekeeping transitions the creator does not need alerted about individually.

## Design considerations for wherever this renders

- **Recipient scope today is narrow** — only the workflow's original creator is notified; there is no notion of notifying an assigned reviewer, other company contacts, or staff watching a queue. Any richer recipient story is a `WorkflowNotificationListener` change (see `Notification-Architecture.md`'s "what is not implemented" section), not a UI change.
- **Templates are plain strings with placeholder substitution** (`{workflow_uuid}`, `{action}`, `{reason}`, `{from_status}`, `{to_status}`) — a UI rendering these should not assume any richer structure (no HTML formatting, no per-locale variants) exists in the templates today.
- **Deep-linking** — a notification currently only carries the rendered subject/body text and the recipient ID; it does not carry a structured reference back to the workflow instance's admin/portal URL. A UI wanting to deep-link "view this workflow" from a notification would need `WorkflowNotificationListener` (or the templates themselves) to be extended to include a URL, which does not happen today.

## Open design questions

Whether notification templates should eventually support richer formatting (HTML email bodies) or per-workflow-type customisation beyond the current flat `config/notifications.php` array is undecided.
