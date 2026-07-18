<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Provides minimal WordPress function stubs needed by tests that
 * exercise code depending on WordPress core functions, without
 * requiring a full WordPress test environment.
 */

require __DIR__ . '/../vendor/autoload.php';

if (! function_exists('wp_mail')) {
    function wp_mail(string $to, string $subject, string $body): bool
    {
        $GLOBALS['__bizhub_test_mail_sent'][] = compact('to', 'subject', 'body');

        return true;
    }
}

if (! function_exists('get_userdata')) {
    function get_userdata(int $userId)
    {
        return $GLOBALS['__bizhub_test_users'][$userId] ?? false;
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['__bizhub_test_options'][$name] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $name, mixed $value): bool
    {
        $GLOBALS['__bizhub_test_options'][$name] = $value;

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $name): bool
    {
        unset($GLOBALS['__bizhub_test_options'][$name]);

        return true;
    }
}
