<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Provides minimal WordPress function stubs needed by tests that
 * exercise code depending on WordPress core functions, without
 * requiring a full WordPress test environment. Mirrors the same
 * minimal-stub pattern used by the sibling BizHub and BizUpKeep
 * Workflow repos.
 */

require __DIR__ . '/../vendor/autoload.php';

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['__bizupkeep_core_test_options'][$name] ?? $default;
    }
}

if (! function_exists('add_option')) {
    function add_option(string $name, mixed $value, string $deprecated = '', bool|string $autoload = 'yes'): bool
    {
        $GLOBALS['__bizupkeep_core_test_options'][$name] = $value;

        return true;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $name, mixed $value, bool|string $autoload = null): bool
    {
        $GLOBALS['__bizupkeep_core_test_options'][$name] = $value;

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $name): bool
    {
        unset($GLOBALS['__bizupkeep_core_test_options'][$name]);

        return true;
    }
}

if (! function_exists('add_filter')) {
    /**
     * Minimal WordPress hook stand-in: registers a callback for a
     * given filter/action tag. Priority and argument count are
     * accepted for signature compatibility but not enforced.
     */
    function add_filter(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['__bizupkeep_core_test_hooks'][$tag][] = $callback;

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
        foreach ($GLOBALS['__bizupkeep_core_test_hooks'][$tag] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $tag, mixed ...$args): void
    {
        foreach ($GLOBALS['__bizupkeep_core_test_hooks'][$tag] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}

if (! function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array
    {
        return $GLOBALS['__bizupkeep_core_test_upload_dir'] ?? ['basedir' => sys_get_temp_dir() . '/bizupkeep-core-tests'];
    }
}

if (! function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $path): bool
    {
        return is_dir($path) || mkdir($path, 0777, true);
    }
}

if (! function_exists('trailingslashit')) {
    function trailingslashit(string $path): string
    {
        return rtrim($path, '/\\') . '/';
    }
}

if (! function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(): void
    {
        $GLOBALS['__bizupkeep_core_test_rewrite_flushed'] = true;
    }
}

if (! function_exists('bizhub')) {
    /**
     * Test stand-in for BizHub's global accessor. Declared once here
     * (not inside a test's setUp(), which would redeclare it on every
     * test run within the same namespace) so DependencyGuard tests can
     * flip $GLOBALS['__bizupkeep_core_bizhub'] between null and a
     * stand-in object to simulate BizHub being booted or not.
     */
    function bizhub(): mixed
    {
        return $GLOBALS['__bizupkeep_core_bizhub'] ?? null;
    }
}
