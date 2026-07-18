# Changelog

All notable changes to this project will be documented in this file.

---

## [0.2.0] - Sprints 001.5–014

### Added

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
