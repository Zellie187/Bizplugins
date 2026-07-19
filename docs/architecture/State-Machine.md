# State Machine

## `WorkflowStatus`: the ten lifecycle states

`BizHub\Workflow\Enums\WorkflowStatus` is a backed `string` enum defining every status a workflow instance can occupy, shared across all workflow types:

| Case | Value | Terminal | Successful |
|---|---|---|---|
| `Created` | `created` | no | no |
| `PendingDocuments` | `pending_documents` | no | no |
| `DocumentsVerified` | `documents_verified` | no | no |
| `AwaitingPayment` | `awaiting_payment` | no | no |
| `Processing` | `processing` | no | no |
| `QualityReview` | `quality_review` | no | no |
| `Completed` | `completed` | no | yes |
| `Archived` | `archived` | yes | yes |
| `Cancelled` | `cancelled` | yes | no |
| `Rejected` | `rejected` | yes | no |

`Created` through `Archived` form the happy-path lifecycle; `Cancelled` and `Rejected` are terminal exception states reachable (per workflow definition) from any non-terminal status.

Two semantic methods drive engine behaviour:

- **`isTerminal(): bool`** — true only for `Archived`, `Cancelled`, `Rejected`. Once an instance reaches a terminal status, `WorkflowStateMachine::apply()` refuses every further action, and `WorkflowManager::rollback()` refuses to roll it back.
- **`isSuccessful(): bool`** — true only for `Completed` and `Archived`. Notably, `Completed` is **not** terminal (a completed workflow can still move to `Archived`), but it *is* successful. `WorkflowInstance::applyTransition()` uses this to stamp `completedAt` the first time a workflow reaches a successful status, without letting the later `Archived` transition push that timestamp forward.

A `label()` method returns a translated, human-readable string per status, used by REST responses and the admin screen.

## `WorkflowStateMachine`: the sole transition authority

`BizHub\Workflow\States\WorkflowStateMachine` is a stateless service with two methods:

- **`apply(WorkflowDefinitionInterface $definition, WorkflowStatus $current, string $action): WorkflowStatus`** — resolves and returns the target status for `$action` from `$current`, per `$definition`'s `transitionRules()`. Throws `InvalidTransitionException` in three cases: the current status is already terminal; the action is not declared by the definition at all; or the action is declared but not permitted from the current status.
- **`allowedActions(WorkflowDefinitionInterface $definition, WorkflowStatus $current): array`** — returns the action names permitted from the current status (empty once terminal), intended to drive which action buttons a UI shows.

`WorkflowManager` never inspects a workflow definition's rules directly — it always goes through `WorkflowStateMachine`, which is what guarantees every workflow type is enforced identically and that "no arbitrary state transitions are allowed" (BH-WORKFLOW-SPEC-001 section 7) is a structural property of the engine rather than a convention individual workflow types must remember to follow.

## `TransitionRule`: the declarative unit

Each `BizHub\Workflow\DTO\TransitionRule` (a `readonly` value object) declares one named action, the set of source statuses it may run from, and the single target status it moves to:

```php
new TransitionRule('verify_documents', [WorkflowStatus::PendingDocuments], WorkflowStatus::DocumentsVerified);
```

A `WorkflowDefinitionInterface` implementation's `transitionRules(): array` (keyed by action name) is the complete, static description of a workflow type's lifecycle graph — see `Company-Registration.md` for the concrete example, and `Workflow-State-Diagrams.md` for its mermaid diagram.
