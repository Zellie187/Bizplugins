# BizUpKeep Core

The primary application framework for the BizUpKeep platform, built on top of [BizHub](https://bizupkeep.co.za) — the shared DI container, database abstraction, event dispatcher, logging, and authorization service that every BizUpKeep module (including this one) integrates through.

BizUpKeep Core itself ships no end-user features. It is the bootstrap/orchestration layer other BizUpKeep modules (e.g. [BizUpKeep Workflow](../Bizworkflow)) are built on top of.

## Dependencies

BizUpKeep Core is not a standalone plugin — it requires:

- **BizHub** (`bizhub`) — the shared framework plugin. Declared via `Requires Plugins: bizhub` in this plugin's header and enforced at runtime by `BizUpKeep\Core\Bootstrap\DependencyGuard`, which deactivates this plugin with an admin notice if BizHub is missing or too old to expose the integration surface Core needs.

## Integration with BizHub

Like BizUpKeep Workflow, BizUpKeep Core never builds its own DI container, database connection, or event dispatcher. It contributes into BizHub's shared container via the same two extension points:

- `bizhub/container_definitions` — Core's service bindings, declared in `includes/Container/definitions.php` (currently empty; a contribution point ready for future Core-owned services).
- `bizhub/register_providers` — registers `BizUpKeep\Core\Providers\CoreServiceProvider` into BizHub's shared provider lifecycle.

## What's implemented today

- Plugin bootstrap, activation/deactivation handlers, translation loading, and frontend/admin asset loading (`BizUpKeep\Core\Bootstrap\Plugin`).
- `wp-content/uploads/bizupkeep/{documents,logs,temp,exports}` directory provisioning on activation.
- A dependency guard mirroring BizUpKeep Workflow's, so Core fails loudly and safely if BizHub is missing rather than silently degrading.
- Its own extension hooks for future BizUpKeep modules: `bizupkeep_core_init`, `bizupkeep_core_before_deactivate`, `bizupkeep_core_after_deactivate`, `bizupkeep_core_uninstall`.

## Installing

Ensure BizHub is active first, then install and activate this plugin. Activation provisions the upload directories and flushes rewrite rules automatically.

## Contributing

Follow the same standards as the sibling BizHub and BizUpKeep Workflow repos: PSR-4 autoloading, PSR-12 formatting, PHPStan level 6, and the `phpcs`/`phpstan`/`phpunit` checks run in CI (`.github/workflows/php.yml`).
