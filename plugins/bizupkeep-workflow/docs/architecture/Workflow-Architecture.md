# Workflow Engine Architecture

The workflow engine is the generic core of this plugin: a reusable state-machine-driven process runner that any concrete workflow type can plug into. It implements the Command, State, Strategy, Repository, DTO and Observer (event dispatch) patterns described in BH-WORKFLOW-SPEC-001.

## The public entry point: `WorkflowEngineInterface`

`BizHub\Workflow\Contracts\WorkflowEngineInterface` is the single API any Controller or Service uses to drive a workflow instance:

```php
interface WorkflowEngineInterface
{
    public function create(CreateWorkflowCommand $command): WorkflowInstance;
    public function transition(TransitionWorkflowCommand $command): WorkflowInstance;
    public function rollback(RollbackWorkflowCommand $command): WorkflowInstance;
    public function find(string $uuid): WorkflowInstance;
    public function historyFor(string $uuid): array;
}
```

Its sole implementation is `BizHub\Workflow\Services\WorkflowManager`.

## `WorkflowManager`: the Service layer / Facade

`WorkflowManager` is where every workflow type's lifecycle is enforced uniformly:

1. **Structural validity** — `create()`/`transition()` consult `WorkflowStateMachine::apply()`, which is the sole authority on whether a requested action is declared by the workflow's `WorkflowDefinitionInterface` and permitted from the instance's current status. This is what guarantees "no arbitrary state transitions."
2. **Business-rule preconditions** — after the state machine confirms structural validity, the workflow type's registered `TransitionGuardInterface` (if any) is invoked to check preconditions the state machine cannot express (e.g. "documents must actually have been reviewed").
3. **Persistence** — the resulting `WorkflowInstance` snapshot and the individual `Transition` audit row are saved via `WorkflowRepositoryInterface`.
4. **Structured audit logging** — one `Logger` entry per operation (`bizupkeep_workflow.created`, `.transitioned`, `.rolled_back`; see `Audit.md`).
5. **Event dispatch** — one or more events are raised on BizHub's shared `EventDispatcher` per operation (see `Event-System.md`).

Concrete workflow types register themselves with the engine via `WorkflowManager::registerDefinition(WorkflowDefinitionInterface $definition, ?TransitionGuardInterface $guard = null)`, called once from that workflow type's own Service Provider `boot()` method (e.g. `CompanyRegistrationServiceProvider`).

## `WorkflowInstance`: the aggregate root

`BizHub\Workflow\Entities\WorkflowInstance` is the only mutable object in the engine, and it is deliberately narrow: `applyTransition(Transition $transition)` is the *only* method that can change its status, `updatedAt`, `completedAt`, or in-memory history. There is no public setter for status. Instances are constructed via two named constructors: `start()` for brand-new workflows and `hydrate()` for reconstructing a previously persisted instance (used only by `WorkflowRepository`).

## Commands and DTOs

Every engine operation takes a single immutable, `readonly` Command object (`CreateWorkflowCommand`, `TransitionWorkflowCommand`, `RollbackWorkflowCommand`), all implementing the marker `CommandInterface`. This keeps `WorkflowEngineInterface`'s signature stable regardless of how many fields an operation needs, and keeps every input to the engine trivially serializable and loggable.

## Rollback

`rollback()` is a first-class engine operation, not a per-workflow-type concern: it moves an instance back to the `from` status of its most recent transition, refusing to do so once the instance has reached a terminal `WorkflowStatus` (see `State-Machine.md`) or if it has no prior transition to return to. It records a synthetic `Transition` with action `"rollback"` and dispatches `WorkflowRolledBack`.

See `State-Machine.md` for the full status/transition model and `Database-Architecture.md` for how instances and transitions are persisted.
