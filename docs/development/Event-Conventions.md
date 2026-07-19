# Event Conventions

## Naming: past-tense, domain-first

Every event class name is a past-tense statement of a fact that already happened, prefixed with the domain noun: `WorkflowCreated`, `WorkflowTransitioned`, `WorkflowCompleted`, `WorkflowCancelled`, `WorkflowRolledBack`. None are named as commands or intentions (there is no `CreateWorkflow` event) — that distinction belongs entirely to the `*Command` DTOs in `includes/DTO/`, never to an event.

## Structure: `final class extends Event`, `readonly` payload

Every event is `final`, extends BizHub's base `BizHub\Framework\Events\Event`, and exposes its payload as `public readonly` promoted constructor properties:

```php
final class WorkflowTransitioned extends Event
{
    public function __construct(
        public readonly WorkflowInstance $workflow,
        public readonly Transition $transition
    ) {
        parent::__construct();
    }
}
```

Listeners read event data directly off these public properties; there are no getter methods on any event class.

## Payload minimalism

An event carries exactly the objects a listener plausibly needs to react — never raw scalars duplicating what is already on those objects. `WorkflowCancelled`/`WorkflowRolledBack` add a `string $reason` alongside `$workflow` specifically because the reason is not otherwise retrievable from the instance itself at the moment the event fires (it lives on the `Transition`, which these two events do not carry). `WorkflowTransitioned` does carry the `Transition`, since the whole point of that event is "what changed."

## One dispatch site per event, inside `WorkflowManager`

All five events are raised from exactly one place each, all inside `WorkflowManager`, immediately after the relevant repository writes and structured log entry — never from a Controller, a Repository, or a per-workflow-type Service. This keeps "what happened" (event dispatch) tied to the single place state changes are actually committed, so an event can never fire for a change that did not actually persist.

## Registering a listener: via a Service Provider, on boot

Listener registration is not automatic or convention-based discovery — it is explicit, one line per event/listener pair, inside a Service Provider's `boot()` method:

```php
$this->events->listen(WorkflowTransitioned::class, $this->notificationListener);
```

`WorkflowServiceProvider` is the only place this happens today (registering `WorkflowNotificationListener` against `WorkflowTransitioned`). A new listener for any of the five events should be registered the same way, from whichever Service Provider owns that listener.

## `config/events.php` is documentation, not wiring

The reference array in `config/events.php` (event class → one-line description) does not register anything — no code reads it to attach listeners. It exists purely so every event this plugin can raise is discoverable in one file for integrators building on top of this plugin. Keep it in sync whenever an event class is added, renamed, or removed, but never make runtime behaviour depend on its contents.
