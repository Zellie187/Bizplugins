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
