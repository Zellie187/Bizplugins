# Contributing to BizUpKeep Workflow

## Setup

This plugin's `composer.json` depends on the sibling `Bizhub_plugin` repository via a local path repository (`../Bizhub_plugin`). Check out both repositories as siblings, then from this plugin's root:

```
composer install
```

## Required checks before opening a pull request

All three of the following must pass — they are what this codebase is verified against, and are the same commands a future CI workflow should run (see `docs/deployment/CI-CD.md`):

```
vendor/bin/phpcs
vendor/bin/phpstan analyse
vendor/bin/phpunit
```

At the time of writing: 32 tests pass, PHPStan reports no errors at level 6, and PHPCS reports no violations. A pull request that regresses any of these is not ready to merge.

## Coding standards

Follow `docs/development/Coding-Standards.md` and `docs/development/Development-Standards.md` — in short: PHP 8.2+, `declare(strict_types=1);` in every file, PSR-4 autoloading (`BizHub\Workflow\` → `includes/`), PSR-12 formatting, final classes unless extension is the explicit intent, readonly properties for immutable data, no God classes, no static helpers or singleton business objects, no globals. New code should read like the existing codebase — match `WorkflowManager`, `WorkflowRepository`, and `CompanyRegistrationService` in style and structure, not just in outcome.

## Adding a new workflow type

Follow `docs/development/Workflow-Standards.md`'s five-piece checklist (`Definition`, optional `Guard`, `Service`, `Controller`+`Request`, `ServiceProvider`) and use `docs/workflows/Company-Registration.md` as the concrete reference implementation. Fifteen other workflow types already have implementation-ready specifications in `docs/workflows/` — check there before designing a new one from scratch.

## Tests are not optional

Every new class needs test coverage at the appropriate tier (`docs/testing/Unit-Tests.md`, `Integration-Tests.md`, `Workflow-Tests.md`) — a new workflow type's definition/guard/service should get unit tests mirroring `CompanyRegistrationGuardTest`/`CompanyRegistrationServiceTest`, and its full lifecycle should get an end-to-end test mirroring `CompanyRegistrationWorkflowTest`. There is no real MySQL available in this test environment — use `tests/Mocks/InMemoryDatabase` for anything touching `WorkflowRepositoryInterface`, exactly as the existing integration tests do.

## Documentation must stay honest

Per `docs/development/Documentation-Standards.md`, never describe a specified-but-unbuilt feature as implemented, and never leave a shipped feature undocumented. If your change moves something from "specified" to "implemented" (e.g. building one of the 15 other workflow types), update its `docs/workflows/*.md` entry in the same pull request — do not leave documentation drift for someone else to notice later.

## No placeholders in pull requests

No `TODO` comments, no stub methods, no commented-out code, and no placeholder documentation content (`lorem ipsum`, "coming soon" without a tracked reason). If something is genuinely not ready, it does not belong in the pull request yet.

## Commit and PR hygiene

Keep pull requests focused on one change (one new workflow type, one bug fix, one documentation update) rather than bundling unrelated changes together, so `phpcs`/`phpstan`/`phpunit` failures are easy to attribute to the actual cause.
