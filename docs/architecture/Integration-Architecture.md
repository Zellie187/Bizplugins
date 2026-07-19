# Integration Architecture: How BizUpKeep Workflow Plugs Into BizHub

BizUpKeep Workflow never builds a second DI container, database connection, or event dispatcher. It contributes into BizHub's single shared instances of each via two extension points BizHub's `Application` and `ContainerFactory` expose specifically for this purpose, plus a small global accessor function.

## `bizhub()`: the global accessor

BizHub's main plugin file (`bizhub.php`) defines a global function:

```php
function bizhub(): ?Application
```

returning the booted `Application` singleton, or `null` if BizHub has not finished booting yet. `routes/api.php` and `WorkflowAdminMenu::render()` both use `bizhub()?->container()` to reach the shared container without holding a reference to it themselves.

## Extension point 1: `bizhub/container_definitions` (filter)

Applied inside `BizHub\Framework\Container\ContainerFactory::create()`, *before* the container is built:

```php
foreach (self::externalDefinitions() as $definitions) {
    $builder->addDefinitions($definitions);
}
// ...
$definitions = apply_filters('bizhub/container_definitions', []);
```

Each entry may be a path to a PHP-DI definitions file or a raw definitions array. BizUpKeep Workflow hooks this filter at file-inclusion time in `bizupkeep-workflow.php` (i.e. before `plugins_loaded` fires for anyone, so registration order between plugins never matters) and appends the path to its own `includes/Container/definitions.php`:

```php
add_filter('bizhub/container_definitions', static function (array $definitions): array {
    $definitions[] = BIZUPKEEP_WORKFLOW_PATH . 'includes/Container/definitions.php';
    return $definitions;
});
```

## Extension point 2: `bizhub/register_providers` (action)

Fired from `BizHub\Framework\Bootstrap\Application::boot()`, after BizHub's own core providers have been *added* to the `ProviderRegistry` but before the registry's two-pass `register()`/`boot()` lifecycle runs — so externally-registered providers participate in the exact same lifecycle as BizHub's first-party ones:

```php
do_action('bizhub/register_providers', $this->providerRegistry, $this->container);
```

BizUpKeep Workflow hooks this action to add its own Service Providers, gated on `DependencyGuard::coreActive()` (BizHub itself is guaranteed active by the time this callback runs, since the action only fires from within BizHub's own boot):

```php
add_action('bizhub/register_providers', static function (ProviderRegistry $providerRegistry, Container $container): void {
    if (! DependencyGuard::coreActive()) {
        return;
    }
    $providerRegistry->add(WorkflowServiceProvider::class);
    $providerRegistry->add(CompanyRegistrationServiceProvider::class);
}, 10, 2);
```

## Boot ordering inside `bizupkeep-workflow.php`

Both hooks above are registered unconditionally at file-inclusion time. The plugin's own WordPress-facing boot (`Plugin::instance()->boot()`, which registers REST routes and the admin menu) is deferred to `plugins_loaded` at priority 20 — after BizHub's own `plugins_loaded` callback (which builds the container and fires `bizhub/booted`) has had a chance to run at its default priority. `DependencyGuard::checkAndNotify()` is invoked at that same point, so the plugin only proceeds to boot its WordPress-facing surface once both BizHub and BizUpKeep Core are confirmed active.

See `Module-Architecture.md` for what gets contributed (the DI bindings and the two Service Providers) and `docs/deployment/Installation.md` for the end-to-end activation sequence.
