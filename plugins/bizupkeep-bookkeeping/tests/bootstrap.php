<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Provides minimal WordPress function stubs needed by tests that
 * exercise code depending on WordPress core functions, without
 * requiring a full WordPress test environment. Mirrors
 * bizupkeep-workflow's own tests/bootstrap.php so both projects' tests
 * behave consistently.
 */

require __DIR__ . '/../vendor/autoload.php';

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (! defined('DATE_ATOM')) {
    define('DATE_ATOM', 'Y-m-d\TH:i:sP');
}

if (! defined('BIZUPKEEP_BOOKKEEPING_PATH')) {
    define('BIZUPKEEP_BOOKKEEPING_PATH', __DIR__ . '/../');
}

if (! defined('BIZUPKEEP_BOOKKEEPING_CONFIG_PATH')) {
    define('BIZUPKEEP_BOOKKEEPING_CONFIG_PATH', BIZUPKEEP_BOOKKEEPING_PATH . 'config/');
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES);
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['__bizupkeep_bookkeeping_test_hooks'][$tag][] = $callback;

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
        foreach ($GLOBALS['__bizupkeep_bookkeeping_test_hooks'][$tag] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $tag, mixed ...$args): void
    {
        foreach ($GLOBALS['__bizupkeep_bookkeeping_test_hooks'][$tag] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['__bizupkeep_bookkeeping_test_options'][$name] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $name, mixed $value): bool
    {
        $GLOBALS['__bizupkeep_bookkeeping_test_options'][$name] = $value;

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $name): bool
    {
        unset($GLOBALS['__bizupkeep_bookkeeping_test_options'][$name]);

        return true;
    }
}

if (! function_exists('get_role')) {
    function get_role(string $name)
    {
        return $GLOBALS['__bizupkeep_bookkeeping_test_roles'][$name] ?? null;
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.test' . $path;
    }
}

if (! function_exists('user_can')) {
    function user_can(int $userId, string $capability): bool
    {
        return in_array($capability, $GLOBALS['__bizupkeep_bookkeeping_test_user_caps'][$userId] ?? [], true);
    }
}

if (! function_exists('wp_mail')) {
    /**
     * @param array<int,string> $headers
     * @param array<int,string> $attachments
     */
    function wp_mail(
        string $to,
        string $subject,
        string $message,
        array $headers = [],
        array $attachments = []
    ): bool {
        // Snapshotted here (not read later by the test) since the caller
        // typically deletes its temp attachment file immediately after
        // this returns.
        $attachmentSizes = array_map(
            static fn (string $path): int => is_file($path) ? filesize($path) : 0,
            $attachments
        );

        $GLOBALS['__bizupkeep_bookkeeping_test_mails'][] = compact(
            'to',
            'subject',
            'message',
            'headers',
            'attachments',
            'attachmentSizes'
        );

        return true;
    }
}

if (! function_exists('wp_tempnam')) {
    function wp_tempnam(string $filename = ''): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'bizupkeep-bookkeeping-test-');

        return $tempFile === false ? '' : $tempFile;
    }
}

if (! function_exists('wp_delete_file')) {
    function wp_delete_file(string $file): void
    {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
