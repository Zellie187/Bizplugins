# Bizplugins

The BizUpKeep / BizHub ecosystem: three WordPress plugins that ship together but stay independently installable and extractable, living here as sibling subfolders under `plugins/`.

| Path | Plugin slug | Namespace | Role |
|---|---|---|---|
| [`plugins/bizhub`](plugins/bizhub) | `bizhub` | `BizHub\` | The framework: DI container, database abstraction, event dispatcher, logging, authorization, and business modules (Companies, Applications, Documents, ClientPortal, Notifications, Dashboard, Reporting, Admin, API). Owns the shared container every other plugin plugs into. |
| [`plugins/bizupkeep-core`](plugins/bizupkeep-core) | `bizupkeep-core` | `BizUpKeep\Core\` | The platform's primary/branded plugin. Bootstrap, activation/deactivation, translations, asset loading. No end-user features of its own — the orchestration layer other BizUpKeep modules build on top of. |
| [`plugins/bizupkeep-workflow`](plugins/bizupkeep-workflow) | `bizupkeep-workflow` | `BizHub\Workflow\` | Business process automation module: a generic workflow engine plus concrete workflow types (Company Registration today, more specified in its `ROADMAP.md`). |

## Why subfolders, not one merged plugin

Each subfolder is fully self-contained (its own `composer.json`, its own `bin/build-zip.sh`, its own PHPUnit/PHPStan/PHPCS config) and installs as a completely independent WordPress plugin. They live in one repo for coordinated development, but nothing about the architecture requires that: `git subtree split --prefix=plugins/<name>` can pull any one of them back out into its own standalone repo with full history, at any time.

## The shared-container contract

BizHub is the only plugin that owns a DI container. Every other plugin contributes into that single shared container rather than building a second one, via two WordPress hooks BizHub exposes (`plugins/bizhub/includes/Framework/Container/ContainerFactory.php`, `plugins/bizhub/includes/Framework/Bootstrap/Application.php`):

- **`bizhub/container_definitions`** (filter) — add PHP-DI definition file paths. Must be registered at file-inclusion time (top-level plugin code), since it fires before `plugins_loaded`.
- **`bizhub/register_providers`** (action) — receives `(ProviderRegistry $providerRegistry, DI\Container $container)`; call `$providerRegistry->add(SomeServiceProvider::class)`. Also must be registered at file-inclusion time — this fires synchronously inside BizHub's own `plugins_loaded` (priority 10), so a listener registered inside another plugin's own `plugins_loaded` callback registers too late and is silently skipped.
- **`bizhub()`** — global accessor returning the booted `Application` singleton, or `null` if BizHub hasn't booted yet (missing, inactive, or called before its `plugins_loaded` callback runs).

Both BizUpKeep Core and BizUpKeep Workflow follow this pattern (see each subfolder's `includes/Bootstrap/DependencyGuard.php` and main plugin file for the concrete wiring), and enforce their own dependencies at runtime — they fail loudly with an admin notice and self-deactivate rather than running half-integrated if BizHub (or, for Workflow, BizUpKeep Core) is missing.

## Activation order

1. **BizHub** — must be active before either of the others; nothing else works without the shared container.
2. **BizUpKeep Core** — depends only on BizHub.
3. **BizUpKeep Workflow** — depends on both BizHub and BizUpKeep Core.

WordPress 6.5+'s `Requires Plugins` header enforces "is it active" for all three at the UI level; each plugin's `DependencyGuard` additionally enforces "is it a compatible version."

## Building for release

Each subfolder has its own `bin/build-zip.sh` producing a single-plugin, WordPress-installable zip from that subfolder alone — install/activate any one of them independently on a WordPress site.

## Local development note

The canonical local development copies of these three plugins live as separate git repos (`Bizhub_plugin`, `Bizupkeep_core`, `Bizworkflow`), each with its own working `composer.json` path-repository pointing at a sibling directory. This repo's `plugins/` subfolders are kept in sync from those via `git subtree`, and each subfolder's own `composer.json` here points at `../bizhub` (its neighbour in this tree) rather than the sibling repo path used locally.
