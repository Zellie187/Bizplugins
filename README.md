# Bizplugins

The BizUpKeep / BizHub ecosystem: six WordPress plugins that ship together but stay independently installable and extractable, living here as sibling subfolders under `plugins/`.

| Path | Plugin slug | Namespace | Role |
|---|---|---|---|
| [`plugins/bizhub`](plugins/bizhub) | `bizhub` | `BizHub\` | The framework: DI container, database abstraction, event dispatcher, logging, authorization, and business modules (Companies, Applications, Documents, ClientPortal, Notifications, Dashboard, Reporting, Admin, API). Owns the shared container every other plugin plugs into. |
| [`plugins/bizupkeep-core`](plugins/bizupkeep-core) | `bizupkeep-core` | `BizUpKeep\Core\` | The platform's primary/branded plugin. Bootstrap, activation/deactivation, translations, asset loading. No end-user features of its own — the orchestration layer other BizUpKeep modules build on top of. |
| [`plugins/bizupkeep-workflow`](plugins/bizupkeep-workflow) | `bizupkeep-workflow` | `BizHub\Workflow\` | Business process automation module: a generic workflow engine plus concrete workflow types (Company Registration, Company Amendment, Annual Return today, more specified in its `ROADMAP.md`). |
| [`plugins/bizupkeep-bookkeeping`](plugins/bizupkeep-bookkeeping) | `bizupkeep-bookkeeping` | `BizHub\Bookkeeping\` | Double-entry bookkeeping. Since `bizupkeep-stub` landed, its client-facing ledger/capture/reporting role is superseded by Stub — this plugin now mainly runs Invoicing, Customers, and Recurring Templates (no Stub equivalent exists for those), plus A2Z's own "Internal Books" and each client's Bookkeeping Monthly subscription state. Sage/Xero/QuickBooks export. |
| [`plugins/bizupkeep-payments`](plugins/bizupkeep-payments) | `bizupkeep-payments` | `BizHub\Payments\` | Payment gateway integration (Yoco, SnapScan). Lets a client pay for a workflow's service or renew their Bookkeeping Monthly subscription, generating a real invoice in bizupkeep-bookkeeping's own books — replacing WooCommerce checkout for these flows. |
| [`plugins/bizupkeep-stub`](plugins/bizupkeep-stub) | `bizupkeep-stub` | `BizHub\Stub\` | Stub Connect API integration (https://developers.stub.africa). Provisions a Stub "business" per Company, embeds Stub's accounting widgets in the Client Portal (no separate Stub login — a server-issued token authenticates off the client's existing WordPress session), and gives staff a synchronous read-only dashboard over any client's books plus a one-time historical-data migration action. |

## Why subfolders, not one merged plugin

Each subfolder is fully self-contained (its own `composer.json`, its own `bin/build-zip.sh`, its own PHPUnit/PHPStan/PHPCS config) and installs as a completely independent WordPress plugin. They live in one repo for coordinated development, but nothing about the architecture requires that: `git subtree split --prefix=plugins/<name>` can pull any one of them back out into its own standalone repo with full history, at any time.

## The shared-container contract

BizHub is the only plugin that owns a DI container. Every other plugin contributes into that single shared container rather than building a second one, via two WordPress hooks BizHub exposes (`plugins/bizhub/includes/Framework/Container/ContainerFactory.php`, `plugins/bizhub/includes/Framework/Bootstrap/Application.php`):

- **`bizhub/container_definitions`** (filter) — add PHP-DI definition file paths. Must be registered at file-inclusion time (top-level plugin code), since it fires before `plugins_loaded`.
- **`bizhub/register_providers`** (action) — receives `(ProviderRegistry $providerRegistry, DI\Container $container)`; call `$providerRegistry->add(SomeServiceProvider::class)`. Also must be registered at file-inclusion time — this fires synchronously inside BizHub's own `plugins_loaded` (priority 10), so a listener registered inside another plugin's own `plugins_loaded` callback registers too late and is silently skipped.
- **`bizhub()`** — global accessor returning the booted `Application` singleton, or `null` if BizHub hasn't booted yet (missing, inactive, or called before its `plugins_loaded` callback runs).

Every other plugin follows this pattern (see each subfolder's `includes/Bootstrap/DependencyGuard.php` and main plugin file for the concrete wiring), and enforces its own dependencies at runtime — they fail loudly with an admin notice and self-deactivate rather than running half-integrated if a required plugin is missing.

## Activation order

1. **BizHub** — must be active before any of the others; nothing else works without the shared container.
2. **BizUpKeep Core** — depends only on BizHub.
3. **BizUpKeep Workflow** and **BizUpKeep Bookkeeping** — each depends only on BizHub + Core, so either can activate before the other.
4. **BizUpKeep Payments** — depends on BizHub, Core, Workflow, and Bookkeeping (it pays for a Workflow instance's service or extends a Bookkeeping subscription).
5. **BizUpKeep Stub** — depends on BizHub, Core, and Bookkeeping (not Workflow or Payments) — provisioning/migrating a company's books needs Company data and the fixed `InternalCompanyProviderInterface` binding, nothing workflow- or payment-specific.

WordPress 6.5+'s `Requires Plugins` header enforces "is it active" for all six at the UI level; each plugin's `DependencyGuard` additionally enforces "is it a compatible version."

## Building for release

Each subfolder has its own `bin/build-zip.sh` producing a single-plugin, WordPress-installable zip from that subfolder alone — install/activate any one of them independently on a WordPress site.

## Local development note

The canonical local development copies of BizHub, BizUpKeep Core, and BizUpKeep Workflow live as separate git repos (`Bizhub_plugin`, `Bizupkeep_core`, `Bizworkflow`), each with its own working `composer.json` path-repository pointing at a sibling directory. BizUpKeep Bookkeeping, BizUpKeep Payments, and BizUpKeep Stub were developed later and, as of the commits that added each of them here, had no fully separate sibling repo of their own (Payments lived in a git worktree; Stub was authored directly into this monorepo) — verify this hasn't drifted before assuming it for any of them. This repo's `plugins/` subfolders are kept in sync from those via `git subtree` where one exists, or a plain merge otherwise (see the commits that added `plugins/bizupkeep-payments` and `plugins/bizupkeep-stub`), and each subfolder's own `composer.json` here points at `../bizhub`, `../bizupkeep-core`, etc. (its neighbours in this tree) rather than whatever path repository is used locally.
