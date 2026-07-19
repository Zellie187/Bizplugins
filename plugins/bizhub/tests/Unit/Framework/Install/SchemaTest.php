<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Framework\Install;

use BizHub\Framework\Install\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    private const EXPECTED_TABLES = [
        'bizhub_companies',
        'bizhub_directors',
        'bizhub_clients',
        'bizhub_client_notifications',
        'bizhub_applications',
        'bizhub_application_steps',
        'bizhub_application_comments',
        'bizhub_documents',
        'bizhub_document_versions',
        'bizhub_audit_log',
        'bizhub_queue_jobs',
        'bizhub_notification_queue',
        'bizhub_logs',
    ];

    public function test_defines_every_expected_table(): void
    {
        $statements = (new Schema())->statements('wp_', 'DEFAULT CHARSET=utf8mb4');

        $this->assertSame(self::EXPECTED_TABLES, array_keys($statements));
    }

    public function test_applies_the_given_prefix_and_charset(): void
    {
        $statements = (new Schema())->statements('custom_prefix_', 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        foreach ($statements as $table => $sql) {
            $this->assertStringContainsString("CREATE TABLE custom_prefix_{$table} (", $sql);
            $this->assertStringContainsString('DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $sql);
        }
    }

    public function test_every_table_has_a_primary_key(): void
    {
        foreach ((new Schema())->statements('wp_', '') as $table => $sql) {
            $this->assertStringContainsString('PRIMARY KEY  (id)', $sql, "Table {$table} is missing a primary key.");
        }
    }

    /**
     * Application steps and client notifications originally used the
     * MySQL reserved words "order" and "read" as bare column names,
     * which would fail on a real database. Guard against regressing
     * back to those names.
     */
    public function test_avoids_mysql_reserved_words_as_column_names(): void
    {
        $statements = (new Schema())->statements('wp_', '');

        $this->assertStringContainsString('step_order', $statements['bizhub_application_steps']);
        $this->assertDoesNotMatchRegularExpression('/\border\b/i', $statements['bizhub_application_steps']);

        $this->assertStringContainsString('is_read', $statements['bizhub_client_notifications']);
        $this->assertDoesNotMatchRegularExpression('/\bread\b/i', $statements['bizhub_client_notifications']);
    }

    #[DataProvider('requiredColumnsProvider')]
    public function test_table_contains_every_column_its_repository_expects(string $table, array $columns): void
    {
        $sql = (new Schema())->statements('wp_', '')[$table];

        foreach ($columns as $column) {
            $this->assertStringContainsString($column, $sql, "Table {$table} is missing column \"{$column}\".");
        }
    }

    /**
     * @return array<string,array{0:string,1:array<int,string>}>
     */
    public static function requiredColumnsProvider(): array
    {
        return [
            'companies' => ['bizhub_companies', ['uuid', 'client_id', 'registration_number', 'company_name', 'status', 'address_line_1', 'city', 'postal_code', 'created_at']],
            'directors' => ['bizhub_directors', ['uuid', 'company_uuid', 'first_name', 'last_name', 'appointment_date', 'active']],
            'clients' => ['bizhub_clients', ['uuid', 'wp_user_id', 'first_name', 'last_name', 'status']],
            'client_notifications' => ['bizhub_client_notifications', ['uuid', 'client_uuid', 'title', 'message', 'is_read']],
            'applications' => ['bizhub_applications', ['uuid', 'client_id', 'type', 'company_uuid', 'status', 'submitted_at']],
            'application_steps' => ['bizhub_application_steps', ['uuid', 'application_uuid', 'name', 'step_order', 'completed']],
            'application_comments' => ['bizhub_application_comments', ['uuid', 'application_uuid', 'author_id', 'message']],
            'documents' => ['bizhub_documents', ['uuid', 'owner_type', 'owner_uuid', 'name', 'category']],
            'document_versions' => ['bizhub_document_versions', ['uuid', 'document_uuid', 'version_number', 'file_path', 'mime_type', 'uploaded_by']],
            'audit_log' => ['bizhub_audit_log', ['uuid', 'action', 'subject_type', 'subject_id', 'user_id', 'context', 'occurred_at']],
            'queue_jobs' => ['bizhub_queue_jobs', ['uuid', 'job_class', 'payload', 'status', 'attempts', 'last_error']],
            'notification_queue' => ['bizhub_notification_queue', ['uuid', 'payload', 'status']],
            'logs' => ['bizhub_logs', ['level', 'message', 'context', 'created_at']],
        ];
    }
}
