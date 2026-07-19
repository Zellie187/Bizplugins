# Event System

BizUpKeep Workflow never builds its own event bus. Every event it raises is dispatched on BizHub's single shared `BizHub\Framework\Events\EventDispatcher`, injected into `WorkflowManager`. This is the Observer pattern referenced throughout BH-WORKFLOW-SPEC-001: workflow state changes are the subject, and any code anywhere in the platform (including other plugins) can subscribe as an observer without the workflow engine needing to know about it.

## The five events

Every event class lives in `BizHub\Workflow\Events\` and extends BizHub's base `Event` class. All are raised from `WorkflowManager`:

| Event | Raised when | Payload |
|---|---|---|
| `WorkflowCreated` | Immediately after a new instance is persisted, in `create()` | `public readonly WorkflowInstance $workflow` |
| `WorkflowTransitioned` | After any successful `transition()` | `public readonly WorkflowInstance $workflow`, `public readonly Transition $transition` |
| `WorkflowCompleted` | When a transition's target status is exactly `WorkflowStatus::Completed` | `public readonly WorkflowInstance $workflow` |
| `WorkflowCancelled` | When a transition's target status is terminal but not successful (i.e. `Cancelled` or `Rejected`) | `public readonly WorkflowInstance $workflow`, `public readonly string $reason` |
| `WorkflowRolledBack` | After a successful `rollback()` | `public readonly WorkflowInstance $workflow`, `public readonly string $reason` |

`config/events.php` is a reference-only registry documenting these five classes for integrators — it performs no wiring itself; listener registration happens in each event's owning Service Provider.

## Dispatch order within a single transition

A single call to `WorkflowManager::transition()` can raise up to two events in sequence: `WorkflowTransitioned` always fires first, followed conditionally by either `WorkflowCompleted` (target is `Completed`) or `WorkflowCancelled` (target is terminal and unsuccessful). `WorkflowCompleted` deliberately does not fire again when a completed workflow later moves to `Archived` — `Completed` is the one-time "success" moment; `Archived` is subsequent housekeeping.

## The one built-in listener

`BizHub\Workflow\Notifications\WorkflowNotificationListener` is the only listener this plugin registers on its own events, subscribed to `WorkflowTransitioned` by `WorkflowServiceProvider::boot()`:

```php
$this->events->listen(WorkflowTransitioned::class, $this->notificationListener);
```

See `Notification-Architecture.md` for what it does. Any other module — inside this plugin, inside BizUpKeep Core, or inside a future plugin — can subscribe to any of these five events the same way, via the shared `EventDispatcher` obtained from BizHub's container.

## Testing implication

Because dispatch goes through the real `EventDispatcher`, `tests/Workflow/CompanyRegistrationWorkflowTest.php` verifies event behaviour by registering plain closures as listeners and asserting on which event classes were captured during a full workflow lifecycle — no event-system mocking is required.
