# Changelog

All notable changes to this project will be documented in this file.

---

## [0.2.1] - Release packaging & activation fix

### Added

- Release packaging pipeline: `bin/build-zip.sh` builds a WordPress-installable
  ZIP from the current commit with production-only Composer dependencies
  bundled in (`composer install --no-dev --optimize-autoloader`), since
  `vendor/` is not committed to the repository
- `.gitattributes` `export-ignore` rules excluding dev-only files (tests,
  docs, CI config, linting/static-analysis config) from `git archive` output
- A "Release Build" GitHub Actions workflow that runs the build script and
  publishes the resulting ZIP as a GitHub Release asset whenever a `vX.Y.Z`
  tag is pushed
- `ContainerBootTest`, an integration test that resolves every module
  provider through the real DI container instead of hand-constructed test
  doubles, so a missing container binding fails CI instead of reaching
  production

### Fixed

- Fatal error on activation: `AuthorizationServiceInterface` had no DI
  container binding, because `includes/Security` was the only module that
  never got a `definitions.php` (every other module has one). Any request
  path touching document security (e.g. loading `wp-admin/plugins.php`
  itself, since `DocumentServiceProvider` is eagerly resolved at boot)
  crashed with `DI\Definition\Exception\InvalidDefinition: ... the class is
  not instantiable`. Added `includes/Security/definitions.php` binding it to
  `AuthorizationService`

---

## [0.2.0] - Sprints 001.5–014

### Added

**Install**
- Database migration system (`Schema`, `Migrator`) covering all 13
  tables the repositories require, applied idempotently via `dbDelta()`
- `register_activation_hook()`/`register_deactivation_hook()` wired up
  in `bizhub.php` (previously never registered, so `Activator` and
  `Deactivator` were dead code)
- Activation now also registers BizHub's WordPress roles
  (`RoleInstaller`), which was likewise never invoked before
- A boot-time version check (`InstallServiceProvider`) that re-runs
  migrations after a plain "Update Now" in wp-admin, since WordPress
  does not fire the activation hook on in-place plugin updates
- Conservative `uninstall.php`: BizHub's tables and options are only
  deleted if the user opts in via a new "Delete all BizHub data on
  uninstall" setting, so upgrading/reinstalling never silently loses
  business data

**Framework**
- Database abstraction (`DatabaseInterface`, `WordPressDatabase` driver,
  fluent query builder, transactions) with automatic table prefixing
- Event dispatcher, job queue with a `Worker` and database-backed
  `Queue`, WP-Cron scheduler wrapper
- Logging (`Logger`/`LogManager` with file and database handlers),
  caching (transient- and object-cache-backed), validation
  (`Validator`/`Rules`)
- Support utilities: `Arr`, `Str`, `Uuid`, `Date`, `Collection`
- Container auto-discovery of every module's `definitions.php`

**Security**
- Authentication (`AuthManager`, session/login management, extended
  "remember me" cookie lifetime)
- Middleware (`Authenticate`, `Authorize`, `VerifyNonce`, `RateLimit`)
- Encryption (`Encryptor` using AES-256-GCM, `Hasher` for passwords)
- Audit logging

**Business modules**
- Companies: directors, shareholders, registered address, company
  history, full CRUD service layer
- Client Portal: client accounts, profiles, in-app notification inbox
- Applications: multi-step workflow (draft → submit → review →
  approve/reject/cancel), comments
- Documents: polymorphic ownership, version history, storage, and
  capability-based access control
- Dashboard: company/application/task/notification/recent-document
  widgets assembled per client
- Notifications: multi-channel dispatch (email, SMS placeholder,
  in-app) via a queued delivery pipeline
- Reporting: company/application/revenue/user-activity reports, CSV
  and print-ready HTML export

**Integrations**
- WooCommerce: product-to-application-type mapping, order listener,
  checkout field handler, customer synchronization
- Forminator: form-to-application-type mapping, submission validation,
  application generation

**REST API** (`bizhub/v1`)
- Company, Application, Document, and Profile endpoints, backed by
  transport-agnostic module controllers

**Administration**
- Settings, Tools, System Status, Logs, and Permissions admin pages

**Testing**
- PHPUnit test suite (Unit + Integration) with an in-memory database
  fixture
- PHPStan (level 6) with WordPress and WooCommerce stubs
- PHPCS with a PSR-12 base and scoped WordPress security sniffs

### Fixed

- Restored a broken autoload chain where the DI container's provider
  registry class had been renamed without updating its filename or
  the class using it
- Fixed a PSR-4 namespace/path mismatch that left the authorization
  service provider unresolvable
- Fixed `Company`/`Director` entities not tracking their mutual
  association, which left `DirectorRepository` unable to persist which
  company a director belonged to
- Fixed two `DatabaseException` subclasses whose constructors were
  incompatible with the base class's `fromThrowable()` factory
- Fixed `ApplicationCreator` calling `get_product_id()` on the base
  `WC_Order_Item` type, which doesn't have that method
- Fixed `phpstan.neon` pointing at a nonexistent `plugins/` directory,
  which meant static analysis was silently running on zero files
- Fixed two MySQL reserved words (`order`, `read`) used as bare,
  unquoted column names, which would have caused SQL syntax errors on
  a real database; also made `WordPressDatabase` quote every column
  identifier it builds, closing off the whole category of bug
- Fixed the plugin never actually creating its database tables or
  registering its WordPress roles - `register_activation_hook()` was
  never called, and `Activator`/`Installer`/`RoleInstaller` were all
  empty stubs, so the plugin could activate but nothing that touched
  the database would have worked

### Technical

- PHP 8.2+ support, WordPress 6.7+ compatibility
- Namespaces realigned to the master specification's canonical layout
  (flat business modules, `Security` instead of `Platform`)

---

## [0.1.0] - Sprint 001 - Milestone 1

### Added

- WordPress plugin bootstrap (`bizhub.php`)
- Framework bootstrap process
- Framework constants registration
- Application singleton
- Framework kernel
- Base framework exception
- Environment validation
- Initial framework boot lifecycle

### Technical

- PHP 8.2+ support
- WordPress 6.8+ compatibility
- PSR-4 autoloading
- Strict typing enabled
- Initial framework lifecycle established
