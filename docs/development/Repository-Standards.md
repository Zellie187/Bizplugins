# Repository Standards

## The pattern, as actually implemented

`BizHub\Workflow\Repositories\WorkflowRepository` is the sole implementation of `BizHub\Workflow\Contracts\WorkflowRepositoryInterface`, and the sole class in this plugin permitted to depend on BizHub's `DatabaseInterface`. No other class — not `WorkflowManager`, not any Controller, not any Service — ever imports `DatabaseInterface` or references a table name directly.

```php
final class WorkflowRepository implements WorkflowRepositoryInterface
{
    private const INSTANCES_TABLE = 'bizhub_workflow_instances';
    private const TRANSITIONS_TABLE = 'bizhub_workflow_transitions';

    public function __construct(
        private readonly DatabaseInterface $database
    ) {}
}
```

## Interface-first design

`WorkflowRepositoryInterface` is defined in `includes/Contracts/` and declares six methods: `find()`, `findForSubject()`, `summaries()`, `save()`, `recordTransition()`, `history()`. `WorkflowManager` (and every other consumer) depends on this interface, never the concrete `WorkflowRepository`. The container binding aliasing this interface to the concrete class lives in `includes/Container/definitions.php`:

```php
WorkflowRepositoryInterface::class => DI\autowire(WorkflowRepository::class),
```

This is what makes `tests/Integration/Repositories/WorkflowRepositoryTest.php` possible without a real database: the test constructs the real `WorkflowRepository` directly against `tests/Mocks/InMemoryDatabase`, a fake `DatabaseInterface` — the interface boundary is the seam the test exploits.

## Hydration is the repository's job, not the entity's

`WorkflowInstance` has no knowledge of arrays, JSON, or database rows. Converting between a `WorkflowInstance`/`Transition` and its row representation is entirely `WorkflowRepository`'s private `hydrate()`/`dehydrate()` methods — enum values are parsed with `WorkflowStatus::from()`, JSON columns (`metadata`, `context`) are encoded/decoded with `JSON_UNESCAPED_SLASHES`, and dates are parsed into `DateTimeImmutable` via a small `toDate()` helper that treats `null`/`''` as "no value."

## Save vs. recordTransition are separate operations

`save()` persists only the instance's current-state snapshot (an upsert keyed on UUID existence via `$database->exists()`); `recordTransition()` is a separate, append-only insert into the transitions table. `WorkflowManager` always calls both, once per transition, in that order — the repository does not implicitly maintain the audit trail as a side effect of `save()`. This keeps "what is the current state" and "what is the full history" as two deliberately distinct, independently queryable concerns.

## Read methods return typed value objects, never raw rows

`find()`/`findForSubject()` return `WorkflowInstance`/`array<WorkflowInstance>`; `summaries()` returns `array<WorkflowSummary>` (a lightweight projection deliberately distinct from the full entity, for list views); `history()` returns `array<Transition>`. No method on `WorkflowRepositoryInterface` returns an untyped array of raw database columns.

## Applying this pattern to a new repository

Any new persistence need (e.g. a future workflow type needing its own lookup table) should follow the same shape: an interface in `Contracts/`, a `final class` implementation in `Repositories/` depending only on `DatabaseInterface`, private hydrate/dehydrate helpers, and a container binding in `Container/definitions.php`.
