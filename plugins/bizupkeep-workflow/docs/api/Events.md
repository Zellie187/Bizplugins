# API-Relevant Events

These are the same five events documented in `docs/architecture/Event-System.md`, presented here from an integrator's perspective — what fires as a *result* of calling the REST API, and what payload a listener elsewhere in the platform receives. All are dispatched on BizHub's shared `EventDispatcher`; there is no separate "API event" concept distinct from the engine's own events.

## `WorkflowCreated`

Fires once, from inside `WorkflowManager::create()`, as a direct result of `POST /company-registrations` succeeding.

```php
final class WorkflowCreated extends Event
{
    public function __construct(public readonly WorkflowInstance $workflow) { parent::__construct(); }
}
```

## `WorkflowTransitioned`

Fires once per successful call to `POST /company-registrations/{uuid}/actions`, always before any of the two conditional events below.

```php
final class WorkflowTransitioned extends Event
{
    public function __construct(
        public readonly WorkflowInstance $workflow,
        public readonly Transition $transition
    ) { parent::__construct(); }
}
```

This is the event `WorkflowNotificationListener` subscribes to (see `docs/architecture/Notification-Architecture.md`) — any new integration wanting to react to "an action happened" should subscribe here too.

## `WorkflowCompleted`

Fires additionally, immediately after `WorkflowTransitioned`, only when the action's target status is exactly `WorkflowStatus::Completed` — for Company Registration, only the `approve` action can trigger this.

## `WorkflowCancelled`

Fires additionally, immediately after `WorkflowTransitioned`, when the action's target status is terminal but unsuccessful — for Company Registration, the `cancel` and `reject` actions.

```php
final class WorkflowCancelled extends Event
{
    public function __construct(
        public readonly WorkflowInstance $workflow,
        public readonly string $reason
    ) { parent::__construct(); }
}
```

## `WorkflowRolledBack`

Fires once, from `WorkflowManager::rollback()`, as a direct result of `POST /company-registrations/{uuid}/rollback` succeeding.

```php
final class WorkflowRolledBack extends Event
{
    public function __construct(
        public readonly WorkflowInstance $workflow,
        public readonly string $reason
    ) { parent::__construct(); }
}
```

## How to subscribe from another module

Obtain BizHub's `EventDispatcher` from the shared container (never construct your own) and call `listen(EventClass::class, $listener)` from your own Service Provider's `boot()`, exactly as `WorkflowServiceProvider` does for `WorkflowNotificationListener`. There is currently no REST-level webhook delivery for these events — see `Webhooks.md`.
