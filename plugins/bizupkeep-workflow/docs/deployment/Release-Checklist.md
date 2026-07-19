# Release Checklist

Use this before tagging and packaging any new release of BizUpKeep Workflow.

## Code quality (verified locally; see `CI-CD.md` for why this is not yet automated)

- [ ] `composer install` succeeds against the current `../Bizhub_plugin` path-repository dependency.
- [ ] `vendor/bin/phpcs` reports no violations.
- [ ] `vendor/bin/phpstan analyse` reports no errors at level 6.
- [ ] `vendor/bin/phpunit` — all tests pass (32 at the time of writing; update this number as the suite grows).

## Versioning

- [ ] `BIZUPKEEP_WORKFLOW_VERSION` in `bizupkeep-workflow.php` bumped, and matches the `Version:` header comment above it.
- [ ] If `includes/Install/Schema.php` changed, `Migrator::CURRENT_VERSION` bumped accordingly (see `docs/database/Versioning.md`).
- [ ] `composer.json` version/constraints reviewed if dependencies changed.

## Documentation

- [ ] `CHANGELOG.md` has a new entry for this version, describing what actually shipped (not aspirational content).
- [ ] Any `docs/workflows/*.md` that moved from "specified" to "implemented" in this release has been updated to say so, with real code references replacing "not yet implemented" language (per `docs/development/Documentation-Standards.md`).
- [ ] `ROADMAP.md` updated if this release changes what is shipped versus planned.

## Dependency declarations

- [ ] Plugin header's `Requires Plugins: bizhub, bizupkeep-core` still accurate.
- [ ] `Requires at least:` (WordPress) and `Requires PHP:` header values still accurate against what was actually tested.
- [ ] `DependencyGuard::bizhubLoaded()`/`coreActive()` checks still match how BizHub/BizUpKeep Core signal their own presence (`BIZHUB_PLUGIN_FILE`/`BIZUPKEEP_CORE_VERSION` constants) — verify neither sibling plugin renamed its marker constant.

## Packaging

- [ ] `composer install --no-dev --optimize-autoloader` run immediately before zipping (see `docs/deployment/Installation.md`).
- [ ] `tests/`, `phpunit.xml`, `phpcs.xml`(`.dist`), `phpstan.neon`, `.phpstan/` cache excluded from the production zip.
- [ ] Zip structure verified to activate correctly against a clean WordPress install with BizHub + BizUpKeep Core already active.

## Post-release verification

- [ ] Fresh activation on a test site runs `Migrator::migrate()`/`RoleGrant::install()` without error and creates both tables with the documented schema (`docs/database/Tables.md`).
- [ ] Upgrade-in-place from the previous version (deactivate → replace files → reactivate, per `docs/deployment/Upgrades.md`) leaves existing workflow data intact.
- [ ] `GET /wp-json/bizupkeep-workflow/v1/company-registrations/{a-known-uuid}` returns the expected shape against a real install.
- [ ] Admin screen (`bizhub` → `Workflows`) renders for an `administrator` user.
