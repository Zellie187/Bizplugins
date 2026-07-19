# DTO Standards

## Every DTO is a `final readonly class`

Every value object under `includes/DTO/` follows the identical shape: `final readonly class`, a promoted-property constructor, no setters, no behaviour beyond the occasional pure query method. This is non-negotiable in this codebase — there is no DTO with a mutable property.

```php
final readonly class Transition
{
    public function __construct(
        public string $uuid,
        public string $workflowUuid,
        public ?WorkflowStatus $from,
        public WorkflowStatus $to,
        public string $action,
        public int $actorId,
        public string $reason,
        public array $context,
        public DateTimeImmutable $occurredAt,
    ) {}
}
```

## Commands vs. plain DTOs

Two sub-categories exist, distinguished by the marker `BizHub\Workflow\Contracts\CommandInterface`:

- **Commands** (`CreateWorkflowCommand`, `TransitionWorkflowCommand`, `RollbackWorkflowCommand`) — represent a caller's *intent* to perform an operation on `WorkflowEngineInterface`. Each `implements CommandInterface`, a marker interface with no methods, whose entire purpose is to make "this object is a request into the engine" visible in type signatures.
- **Plain data DTOs** (`TransitionRule`, `Transition`, `WorkflowSummary`) — represent facts *about* a workflow (a declared rule, a recorded transition, a summary projection), not a request to change anything. These do not implement `CommandInterface`.

## Behaviour on a DTO is limited to pure queries

`TransitionRule::allowsFrom(WorkflowStatus $status): bool` is the only non-trivial method on any DTO in this codebase, and it is a pure predicate over the object's own already-set properties — it does not mutate state, call out to another service, or have side effects. This is the ceiling for what belongs on a DTO: if a candidate method would need a collaborator injected, it belongs on a Service instead.

## Array shapes are always documented

Every DTO constructor parameter or property that is an `array` carries a PHPStan-style docblock describing its shape, e.g. `@param array<string,mixed> $metadata` on `CreateWorkflowCommand`, `@param array<int,WorkflowStatus> $from` on `TransitionRule`. This is required to keep PHPStan level 6 clean and is treated as part of the DTO's public contract, not optional documentation.

## Nullability is explicit and meaningful

`Transition::$from` is typed `?WorkflowStatus` specifically because the very first transition recorded for any instance (action `"create"`, conceptually) has no prior status — `null` here is a real, meaningful state (see `WorkflowRepository::history()`'s handling of a `null` `from_status` column), not a lazily-typed default.

## Request DTOs follow the same discipline, with one addition

`Http/Requests/*` classes (`StartCompanyRegistrationRequest`, `CompanyRegistrationActionRequest`) are also `final readonly class`, but add a private constructor plus a static `fromRestRequest(WP_REST_Request $request): self` factory that performs shape validation and throws `ValidationException` on failure — the only place in the DTO layer where a factory method is expected to throw.
