# BizHub Core Plugin

The BizHub Core Plugin provides the foundational services and business
modules for the BizHub Platform — an enterprise business management
platform built as a WordPress plugin.

---

# Version

0.2.0

---

# Status

Sprints 001–014 implemented (Framework, Security, Companies,
ClientPortal, Applications, Documents, WooCommerce/Forminator
integrations, Dashboard, Notifications, REST API, Administration,
Reporting, Testing) and Sprint 017 (release scaffolding). Sprints
015–016 (Assets, Localization) remain.

---

# Purpose

The plugin provides the core infrastructure required by all BizHub
service modules, plus the business modules themselves.

Core responsibilities include:

- Bootstrap initialization
- Dependency injection
- Database abstraction
- Configuration management
- Event dispatching
- Job queue processing
- Logging
- Caching
- Scheduling (WP-Cron)
- Validation
- Security services (authentication, authorization, encryption, audit)
- Module loading

Business modules include company management, the client portal,
applications and their workflow, document management, dashboards,
notifications, reporting, a REST API, and an admin interface, along
with WooCommerce and Forminator integrations.

---

# Minimum Requirements

- PHP 8.2+
- WordPress 6.7+
- MySQL 8+ (or MariaDB equivalent)
- Composer
- WooCommerce and/or Forminator (optional — their integrations no-op
  when the corresponding plugin isn't active)

---

# Repository Layout

```text
bin/                 # Release build script
includes/
├── Admin/            # Admin menu and settings pages
├── Api/               # REST API (bizhub/v1)
├── Applications/      # Application entities, workflow, services
├── ClientPortal/      # Client accounts, profiles, notifications
├── Companies/         # Company, director, and shareholder management
├── Dashboard/         # Client portal dashboard widgets
├── Documents/         # Document storage, versioning, access control
├── Framework/         # Bootstrap, container, database, events, etc.
├── Integrations/      # WooCommerce and Forminator integrations
├── Notifications/     # Multi-channel notification dispatch
├── Reporting/         # Reports and CSV/HTML export
├── Security/          # Auth, authorization, encryption, audit
└── Shared/            # Cross-module reusable components

assets/
docs/
languages/
templates/
tests/
├── Unit/
├── Integration/
└── Mocks/
vendor/
bizhub.php
uninstall.php
```

---

# Development

```bash
composer install

# Run the test suite
vendor/bin/phpunit

# Run static analysis
vendor/bin/phpstan analyse --memory-limit=1G

# Check coding standards
vendor/bin/phpcs --standard=phpcs.xml
```

---

# Building a Release ZIP

`bizhub.php` requires `vendor/autoload.php`, but `vendor/` is not committed
to this repository. A WordPress-installable ZIP with production-only
dependencies bundled in can be built with:

```bash
bin/build-zip.sh [version]
```

This archives the current commit (excluding dev-only files per
`.gitattributes`), runs `composer install --no-dev --optimize-autoloader`
inside it, and produces `build/bizhub-<version>.zip` with `bizhub/` as its
top-level folder — ready to extract into `wp-content/plugins/`.

Pushing a git tag matching `vX.Y.Z` also triggers the "Release Build"
GitHub Actions workflow, which builds the same ZIP and publishes it as a
GitHub Release asset.

---

# Coding Standards

- PSR-4 Autoloading
- PSR-12 formatting
- WordPress-specific security sniffs (escaping, nonce verification,
  prepared statements) via PHPCS
- Semantic Versioning

---

# Next Milestone

Sprint 015 – Assets (CSS/JS build pipeline) and Sprint 016 –
Localization (`.pot`/`.po`/`.mo` files).
