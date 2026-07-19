# Exception Standards

## One root, per BH-WORKFLOW-SPEC-001 section 11

`BizHub\Workflow\Exceptions\WorkflowException extends Exception` is the single root every exception this plugin can throw derives from — deliberately, so calling code (chiefly `CompanyRegistrationController::handle()`) can catch one type and safely translate it into a response, rather than needing to know every specific exception class in advance:

```php
class WorkflowException extends Exception
{
}
```

It is the one non-`final` class in the exception hierarchy (and one of very few non-final classes anywhere in this codebase), specifically so its five subclasses can extend it.

## The five leaf exceptions

| Exception | Thrown when | Notes |
|---|---|---|
| `ValidationException` | Input fails shape validation before reaching the engine | Carries an `array<string,string> $errors` field-name → message map, exposed via `errors()` |
| `InvalidTransitionException` | A requested action is not declared by a workflow's definition, or is not permitted from the current status, or the workflow is already terminal | Thrown exclusively by `WorkflowStateMachine::apply()` and `WorkflowManager::rollback()`/`definitionFor()` |
| `PreconditionFailedException` | A transition is structurally valid but a business-rule precondition is unmet | Thrown exclusively from inside a `TransitionGuardInterface` implementation, e.g. `CompanyRegistrationGuard` |
| `WorkflowNotFoundException` | A workflow instance cannot be found by UUID, or is found but is the wrong workflow type for the endpoint | Has a named constructor, `forUuid(string $uuid): self` |
| `AuthorizationException` | Reserved for "current user is not permitted" scenarios | Not currently thrown anywhere in the codebase — authorization failures are today handled by returning a `403 WP_Error` directly from the Controller rather than throwing; this class exists for a future path where authorization failures need to propagate through non-Controller code |

## Named constructors over ad-hoc `new`

`WorkflowNotFoundException::forUuid()` is the pattern: a static factory that builds a consistent, well-formatted message from its inputs, rather than every call site writing its own `sprintf()`. New exceptions with a common "reason" shape should follow this same convention.

## Controller-side translation, never leaked detail

`CompanyRegistrationController::handle()` is the single place a caught `Throwable` becomes an HTTP response, using a `match (true)` block mapping known exception types to specific statuses (422/404/409) and everything else to a generically-worded `500` — logging the real exception class and message server-side first via `unexpected()`. No exception message from an *unknown* exception type is ever returned to the API caller; only the five purpose-built exceptions above have their `getMessage()` reflected back, and only because those messages are already written to be safe, user-facing text (see the exception construction sites in `CompanyRegistrationGuard`, `WorkflowStateMachine`, etc.).

## Guidance for new exceptions

Any new exception must extend `WorkflowException` (directly or via one of its five subclasses if the semantics match), must never contain sensitive data in its message, and must be added to `CompanyRegistrationController::handle()`'s (or the relevant controller's) mapping if it needs a specific, non-500 HTTP status.
