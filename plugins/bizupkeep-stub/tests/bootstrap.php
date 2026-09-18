<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Provides minimal WordPress function stubs needed by tests that
 * exercise code depending on WordPress core functions, without
 * requiring a full WordPress test environment. Mirrors this
 * ecosystem's other plugins' own tests/bootstrap.php so all of them
 * behave consistently. Deliberately narrow: unit tests here target
 * pure entity logic, never admin-page rendering or an actual outbound
 * Stub API call - those are verified live only, matching this
 * codebase's established convention for anything that touches a real
 * external system.
 */

require __DIR__ . '/../vendor/autoload.php';

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (! defined('DATE_ATOM')) {
    define('DATE_ATOM', 'Y-m-d\TH:i:sP');
}

if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (! defined('BIZUPKEEP_STUB_PATH')) {
    define('BIZUPKEEP_STUB_PATH', __DIR__ . '/../');
}

if (! defined('BIZUPKEEP_STUB_CONFIG_PATH')) {
    define('BIZUPKEEP_STUB_CONFIG_PATH', BIZUPKEEP_STUB_PATH . 'config/');
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['__bizupkeep_stub_test_hooks'][$tag][] = $callback;

        return true;
    }
}

if (! function_exists('add_action')) {
    function add_action(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return add_filter($tag, $callback, $priority, $acceptedArgs);
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        foreach ($GLOBALS['__bizupkeep_stub_test_hooks'][$tag] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $tag, mixed ...$args): void
    {
        foreach ($GLOBALS['__bizupkeep_stub_test_hooks'][$tag] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['__bizupkeep_stub_test_options'][$name] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $name, mixed $value): bool
    {
        $GLOBALS['__bizupkeep_stub_test_options'][$name] = $value;

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $name): bool
    {
        unset($GLOBALS['__bizupkeep_stub_test_options'][$name]);

        return true;
    }
}

if (! function_exists('get_role')) {
    function get_role(string $name)
    {
        return $GLOBALS['__bizupkeep_stub_test_roles'][$name] ?? null;
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        return $GLOBALS['__bizupkeep_stub_test_transients'][$key] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['__bizupkeep_stub_test_transients'][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        unset($GLOBALS['__bizupkeep_stub_test_transients'][$key]);

        return true;
    }
}
