# Development Standards

This plugin is built against **BH-WORKFLOW-SPEC-001**, the governing development specification for BizUpKeep Workflow. This document summarizes how that spec is realized in the actual codebase; the more focused documents in this directory (`Coding-Standards.md`, `Testing-Standards.md`, etc.) go deeper on each area.

## Baseline

- **PHP 8.2+**, enforced both by `composer.json`'s `"php": "^8.2"` constraint and by PHPCompatibility's `testVersion: 8.2-` rule in `phpcs.xml`.
- **`declare(strict_types=1);`** at the top of every PHP file in `includes/`.
- **PSR-4 autoloading**, `BizHub\Workflow\` → `includes/`, `BizHub\Workflow\Tests\` → `tests/` (see `composer.json`).
- **PSR-12 formatting**, enforced via `<rule ref="PSR12"/>` in `phpcs.xml`, layered with WordPress-specific security sniffs (`WordPress.WP.I18n`, `WordPress.DB.PreparedSQL`, `WordPress.Security.NonceVerification`, `WordPress.Security.ValidatedSanitizedInput`, and `WordPress.Security.EscapeOutput` scoped to `includes/Admin/*`).
- **PHPStan level 6**, configured in `phpstan.neon` against `includes/` only (tests are not statically analysed), with the WordPress stub extension (`szepeviktor/phpstan-wordpress`) loaded and two narrowly-scoped `ignoreErrors` entries for the `BIZUPKEEP_WORKFLOW_*` runtime constants and the `bizhub()` global function, both of which are defined outside any autoload map and therefore invisible to static analysis by construction.
- **32 passing PHPUnit tests**, clean PHPStan level 6, clean PHPCS — this is the actual, currently-verified state of this codebase, not an aspiration.

## Design rules actually followed

- **Final classes unless extension is the explicit design intent.** Every concrete class in this codebase (`WorkflowManager`, `WorkflowRepository`, `WorkflowStateMachine`, every DTO, every exception's leaf subclasses) is `final`. The one deliberate exception is `WorkflowException` itself, declared as a plain (non-final) `class` specifically so every other exception in the hierarchy can extend it.
- **Readonly properties for immutable data.** Every DTO/Command/value object (`TransitionRule`, `Transition`, `WorkflowSummary`, `CreateWorkflowCommand`, `TransitionWorkflowCommand`, `RollbackWorkflowCommand`) is a `final readonly class`.
- **No God classes.** `WorkflowManager` is intentionally the busiest class in the engine, but its five public methods each do one thing, and every concern it does *not* own (persistence, structural transition rules, per-workflow-type business rules) is delegated to a collaborator (`WorkflowRepositoryInterface`, `WorkflowStateMachine`, `TransitionGuardInterface`).
- **No static helpers or singleton business objects.** The only classes using a private constructor + static instance pattern are infrastructure/bootstrap concerns that must run before the DI container exists (`BizHub\Workflow\Bootstrap\Plugin`, `DependencyGuard`, `Constants`) — never engine or domain logic, which is always constructor-injected and resolved from BizHub's container.
- **No globals.** State lives in constructor-injected collaborators or in `WorkflowInstance`'s own private properties, never in superglobals or static class properties holding business data.

## Design patterns in active use

Repository (`WorkflowRepository`), Service/Facade (`WorkflowManager`, `CompanyRegistrationService`), Dependency Injection (BizHub's shared PHP-DI container, see `docs/architecture/Integration-Architecture.md`), Command (`CreateWorkflowCommand` et al.), State (`WorkflowDefinitionInterface` + `WorkflowStateMachine`), Strategy (`TransitionGuardInterface`), Observer (`EventDispatcher` + the five workflow events), and DTO (every `readonly` value object in `includes/DTO/`).
