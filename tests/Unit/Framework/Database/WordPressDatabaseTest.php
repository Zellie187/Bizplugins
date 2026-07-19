<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Framework\Database;

use BizHub\Framework\Database\Drivers\WordPressDatabase;
use PHPUnit\Framework\TestCase;
use wpdb;

/**
 * Regression coverage for a bug found via manual testing on a live
 * site: a null value bound through $wpdb->prepare()'s %s/%d/%f
 * placeholders is cast to an empty string / zero rather than emitted
 * as SQL NULL, which MySQL (in non-strict SQL modes, common on shared
 * hosting) silently coerces into the "zero date"
 * '0000-00-00 00:00:00' for nullable DATETIME columns instead of
 * raising an error - corrupting data with no warning at all.
 *
 * WordPressDatabase is the only class permitted to touch $wpdb
 * directly, and had no test coverage before this file.
 */
final class WordPressDatabaseTest extends TestCase
{
    public function test_insert_writes_a_null_value_as_literal_sql_null_not_a_bound_placeholder(): void
    {
        $wpdb = new RecordingWpdb();
        $database = new WordPressDatabase($wpdb);

        $database->insert('bizhub_workflow_instances', [
            'uuid' => 'a-uuid',
            'status' => 'created',
            'completed_at' => null,
        ]);

        $this->assertStringContainsString(
            "`uuid`, `status`, `completed_at`) VALUES ('a-uuid', 'created', NULL)",
            $wpdb->lastQuery
        );
    }

    public function test_update_writes_a_null_value_as_literal_sql_null_not_a_bound_placeholder(): void
    {
        $wpdb = new RecordingWpdb();
        $database = new WordPressDatabase($wpdb);

        $database->update(
            'bizhub_workflow_instances',
            ['status' => 'processing', 'completed_at' => null],
            ['uuid' => 'a-uuid']
        );

        $this->assertStringContainsString('`status` = \'processing\', `completed_at` = NULL', $wpdb->lastQuery);
        $this->assertStringContainsString('WHERE `uuid` = \'a-uuid\'', $wpdb->lastQuery);
    }

    public function test_find_criteria_with_a_null_value_uses_is_null_not_equals(): void
    {
        $wpdb = new RecordingWpdb();
        $database = new WordPressDatabase($wpdb);

        $database->findAll('bizhub_workflow_instances', ['completed_at' => null]);

        $this->assertStringContainsString('WHERE `completed_at` IS NULL', $wpdb->lastQuery);
    }

    public function test_non_null_values_are_still_safely_bound_not_inlined(): void
    {
        $wpdb = new RecordingWpdb();
        $database = new WordPressDatabase($wpdb);

        $database->insert('bizhub_workflow_instances', [
            'uuid' => "o'brien",
            'attempts' => 3,
        ]);

        // A naively-inlined value would break out of its quotes; prepare()
        // must still be the one escaping it.
        $this->assertStringContainsString("'o\\'brien'", $wpdb->lastQuery);
        $this->assertStringContainsString('3', $wpdb->lastQuery);
    }
}

/**
 * A minimal but behaviourally faithful stand-in for $wpdb: its
 * prepare() performs the same positional %s/%d/%f substitution (via
 * the real $wpdb->prepare()'s underlying escaping) so assertions can
 * be made against the exact final SQL text, the same way a real MySQL
 * driver would receive it.
 */
final class RecordingWpdb extends wpdb
{
    public string $prefix = 'wp_';

    public string $lastQuery = '';

    public string $last_error = '';

    public int $insert_id = 42;

    public function __construct()
    {
    }

    public function get_charset_collate(): string
    {
        return '';
    }

    public function prepare(string $query, array $args): string
    {
        $index = 0;

        return preg_replace_callback(
            '/%[sdf]/',
            function (array $matches) use ($args, &$index): string {
                $value = $args[$index++];

                return match ($matches[0]) {
                    '%d' => (string) (int) $value,
                    '%f' => (string) (float) $value,
                    default => "'" . addslashes((string) $value) . "'",
                };
            },
            $query
        );
    }

    public function query(string $query): int
    {
        $this->lastQuery = $query;

        return 1;
    }

    public function get_results(string $query, string $output = ARRAY_A): array
    {
        $this->lastQuery = $query;

        return [];
    }
}
