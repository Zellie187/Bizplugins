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

foreach ([
    'MINUTE_IN_SECONDS' => 60,
    'HOUR_IN_SECONDS' => 3600,
    'DAY_IN_SECONDS' => 86400,
    'WEEK_IN_SECONDS' => 604800,
    'MONTH_IN_SECONDS' => 2592000,
    'YEAR_IN_SECONDS' => 31536000,
] as $constant => $value) {
    if (! defined($constant)) {
        define($constant, $value);
    }
}

if (! class_exists('wpdb')) {
    /**
     * Minimal runtime stand-in for WordPress' $wpdb, sufficient for the
     * `instanceof wpdb` construction check in WordPressDatabase. Tests
     * that need real query behaviour use Mocks\InMemoryDatabase instead.
     */
    class wpdb
    {
        public string $prefix = 'wp_';

        public function get_charset_collate(): string
        {
            return '';
        }
    }
}

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
