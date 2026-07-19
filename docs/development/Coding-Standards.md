# Coding Standards

## Enforcement tools

Three tools gate every change, all verified clean/passing in this codebase:

- **PHP_CodeSniffer** (`phpcs.xml`) — `PSR12`, plus `WordPress.WP.I18n`, `WordPress.DB.PreparedSQL`, `WordPress.Security.NonceVerification`, `WordPress.Security.ValidatedSanitizedInput`, `WordPress.Security.EscapeOutput` (the last scoped to `includes/Admin/*` and `includes/ClientPortal/*`), and `PHPCompatibility` pinned to `testVersion: 8.2-`. Scoped to `includes` only; `vendor/*` excluded.
- **PHPStan** (`phpstan.neon`) — level 6, `paths: [includes]`, with `inferPrivatePropertyTypeFromConstructor: true` and the `szepeviktor/phpstan-wordpress` extension for WordPress core stubs.
- **PHPUnit** (`phpunit.xml`) — bootstrap `tests/bootstrap.php`, single testsuite `BizUpKeepWorkflow` covering the whole `tests/` directory.

Run them with `vendor/bin/phpcs`, `vendor/bin/phpstan analyse`, `vendor/bin/phpunit` respectively (see `docs/deployment/CI-CD.md`).

## Language-level conventions

- `declare(strict_types=1);` immediately after the opening `<?php` tag, before the namespace, in every source file.
- Constructor property promotion with visibility and `readonly` modifiers used throughout (e.g. `WorkflowManager`'s constructor: `private readonly WorkflowRepositoryInterface $repository, ...`).
- Native enums for closed sets of values — `WorkflowStatus: string` rather than class constants or magic strings, with behaviour (`isTerminal()`, `isSuccessful()`, `label()`) attached to the enum itself via `match` expressions.
- `match` over `switch` for exhaustive, expression-style branching (`WorkflowStatus::isTerminal()`, `CompanyRegistrationGuard::guard()`).
- Named constructors over conditional constructor logic — `WorkflowInstance::start()` vs. `WorkflowInstance::hydrate()` rather than one constructor with an "is this new or existing" flag.
- Nullsafe operator (`?->`) and null-coalescing (`??`) used for optional collaborators (e.g. `$this->guards[$workflowType] ?? null` in `WorkflowManager::transition()`).

## Documentation

Every class carries a docblock explaining its role in the architecture and, where relevant, *why* it is built the way it is (not just what it does) — see `WorkflowManager`'s class docblock explaining its Facade role, or `Container/definitions.php`'s comment explaining the `WorkflowManager`/`WorkflowEngineInterface` aliasing pattern (see `Service-Standards.md`). New code should follow this same standard: docblocks earn their place by explaining *why*, not by restating the method signature.

## PHPStan-driven type discipline

Because the codebase runs at level 6, every array parameter/return type is documented with a generics-style `@param array<string,mixed> $context` / `@return array<int,Transition>` annotation, every method has an explicit return type, and `mixed` is used deliberately rather than being left implicit. `WorkflowRepository`'s `dehydrate()`/`hydrate()` methods are a good template for documenting array shapes precisely.

## No dead code, no placeholders

Nothing in `includes/` contains a `TODO`, a stub method, or commented-out code left in place "for later" — the one narrow exception is `ContainerFactory::create()` in BizHub itself, which documents (in a comment) a deliberately-deferred production optimisation (container compilation) rather than half-implementing it.
