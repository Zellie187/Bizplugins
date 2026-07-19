# 16. Service Container

## Purpose

The BizHub Service Container is responsible for managing object creation,
dependency resolution, and service lifecycles across the Platform.

BizHub follows the Dependency Injection (DI) pattern to reduce coupling,
improve testability, and support modular development.

The implementation follows PSR-11 (Container Interface) standards.

---

## Goals

The Service Container exists to:

- Centralize object creation.
- Resolve constructor dependencies automatically.
- Remove manual object instantiation.
- Improve testability.
- Support modular architecture.
- Enable future package and module development.

---

## Principles

BizHub follows these principles:

- Constructor Injection
- Dependency Inversion
- Interface-first development
- PSR compliance
- No global state
- No hidden dependencies

Business modules should never instantiate their own dependencies.

Incorrect:

```php
$service = new CompanyService();
```

Correct:

```php
$service = $container->get(CompanyService::class);
```

---

## Application Lifecycle

The Framework starts in the following order:

1. WordPress loads the plugin.
2. Composer Autoloader initializes.
3. Application is created.
4. Service Providers are registered.
5. Services are registered with the container.
6. Providers are booted.
7. Platform becomes available.
8. Business Modules become available.

---

## Provider Lifecycle

Each Service Provider follows two phases.

### Register

The register() method is responsible for:

- Registering services.
- Binding interfaces.
- Defining dependencies.
- Registering configuration.

No application logic should execute during registration.

### Boot

The boot() method is responsible for:

- Initializing services.
- Registering hooks.
- Registering events.
- Registering capabilities.
- Loading module functionality.

---

## Dependency Injection

BizHub uses constructor injection.

Example:

```php
final class CompanyService
{
    public function __construct(
        AuthorizationService $authorization,
        AuditService $audit,
        StorageService $storage
    ) {
    }
}
```

Dependencies should never be created inside constructors.

---

## Benefits

Using a Service Container provides:

- Loose coupling
- Improved testing
- Better maintainability
- Easier module development
- Standardized architecture
- Future SaaS compatibility

---

## Future Enhancements

Future versions may include:

- Automatic service discovery
- Module auto-registration
- Tagged services
- Event subscribers
- Middleware pipelines
- Cached container compilation