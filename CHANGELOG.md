# Changelog

All notable changes to the BizUpKeep Core plugin will be documented in this file.
The format follows **Keep a Changelog** and the project adheres to **Semantic Versioning (SemVer)**.

---

## [1.1.0] - 2026-07-19

### Changed
- Rewritten as a Composer/PSR-4 plugin (`BizUpKeep\Core\`) instead of the original manually-`require`d procedural boilerplate, matching BizHub's and BizUpKeep Workflow's architecture.
- Core now declares a real runtime dependency on BizHub (`Requires Plugins: bizhub`) and no longer boots in isolation: it registers into BizHub's shared DI container via the `bizhub/container_definitions` and `bizhub/register_providers` extension points instead of building nothing at all.
- Added `BizUpKeep\Core\Bootstrap\DependencyGuard`, mirroring BizUpKeep Workflow's, so Core self-deactivates with an admin notice if BizHub is missing or incompatible instead of silently doing nothing.
- Added PHPUnit, PHPStan (level 6), and PHPCS (PSR-12 + WordPress security sniffs) tooling, a `bin/build-zip.sh` release script, and GitHub Actions CI/release workflows, bringing dev tooling to parity with the sibling repos.

### Developer Notes
Existing behaviour (activation-time upload directory provisioning, translation loading, frontend/admin asset loading, the `bizupkeep_core_*` action hooks) is unchanged — this release is an architectural rewrite, not a functional one. `bizupkeep_core()` now returns `BizUpKeep\Core\Bootstrap\Plugin|null` instead of an always-non-null `BizUpKeep\Core\Core` singleton, matching `bizhub()`'s null-before-boot contract.

---

## [1.0.0] - 2026-07-11

### Added
- Initial plugin bootstrap.
- WordPress Coding Standards compliant architecture.
- PHP 8.2+ support.
- Singleton application bootstrap.
- Plugin activation handler.
- Plugin deactivation handler.
- Safe uninstall routine.
- Translation loading.
- Frontend asset loader.
- Admin asset loader.
- Plugin constants.
- Commercial SaaS foundation.
- Extensible WordPress action hooks.
- WooCommerce integration readiness.
- Forminator integration readiness.
- REST API ready architecture.
- Upload directory creation during activation.
- Semantic versioning strategy.

### Security
- Direct access protection on all executable files.
- Persistent user data preserved during uninstall.
- Plugin configuration removed safely on uninstall.

### Developer Notes
This release establishes the foundation for the BizUpKeep ecosystem. Future modules should integrate with the core using WordPress actions, filters, and service classes rather than modifying core files directly.

---

## Versioning

| Version | Status | Release Date |
|---------|--------|--------------|
| 1.1.0 | Composer/PSR-4 rewrite | 2026-07-19 |
| 1.0.0 | Initial Release | 2026-07-11 |
