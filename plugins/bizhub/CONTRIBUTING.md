# Contributing to BizHub

## Getting Started

```bash
git clone <repository-url>
cd bizhub-plugin
composer install
```

This installs both runtime dependencies (php-di) and development
tooling (PHPUnit, PHPStan, PHPCS with WordPress/WooCommerce stubs).

## Before Opening a Pull Request

Run the full quality gate locally:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpcs --standard=phpcs.xml
```

All three must pass. `vendor/bin/phpcbf --standard=phpcs.xml` will
auto-fix most formatting issues, but review its changes before
committing.

## Architecture

BizHub follows a modular, layered architecture:

- **Framework** (`includes/Framework/`) — reusable platform services
  (DI container, database abstraction, events, logging, caching,
  queue, scheduler, validation). Framework code must not depend on any
  business module.
- **Business modules** (`includes/Companies/`, `includes/Applications/`,
  etc.) — each follows the same internal shape: `Entities/`, `DTO/`,
  `Contracts/`, `Repositories/`, `Services/`, `Providers/`,
  `definitions.php`. Repositories are the only place a module talks to
  the database; business rules live in services, not repositories or
  controllers.
- **Security** (`includes/Security/`) — authentication, authorization,
  encryption, and audit logging, shared across all modules. Business
  modules should never call WordPress capability or `$wpdb` functions
  directly — always go through `AuthorizationServiceInterface` /
  `DatabaseInterface`.

New modules should be registered with the DI container via their own
`definitions.php` (auto-discovered by `ContainerFactory`) and wired
into the boot lifecycle via a `ServiceProvider` registered in
`Framework/Bootstrap/Application.php`.

## Coding Standards

- PHP 8.2+, `declare(strict_types=1)` in every file.
- PSR-4 autoloading — file path must match namespace exactly.
- PSR-12 formatting (enforced by PHPCS).
- Prefer composition and interfaces over inheritance; only introduce
  an interface when there are two or more real implementations, or a
  module boundary genuinely needs to depend on an abstraction rather
  than a concrete class.
- No comments explaining *what* code does — code should be
  self-explanatory through naming. Comments are for non-obvious *why*.

## Testing

- `tests/Unit/` — pure logic, no I/O (entities, value objects, string
  helpers, validators).
- `tests/Integration/` — services wired together against
  `tests/Mocks/InMemoryDatabase.php`, exercising real repository/service
  interaction without a live database.
- New business logic should include at least one integration test
  covering its main success path and its main failure path (not-found,
  validation failure, etc.).

## Commit Messages

Focus on *why* a change was made, not a restatement of the diff.
Keep commits scoped to one logical change.
