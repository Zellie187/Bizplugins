<?php

declare(strict_types=1);

namespace BizHub\Framework\Install;

/**
 * Defines the database schema required by every module's repository.
 *
 * Table and column names here must exactly match the table/column
 * names each repository already uses (see e.g.
 * BizHub\Companies\Repositories\CompanyRepository::TABLE). This class
 * only describes the schema; BizHub\Framework\Install\Migrator is
 * responsible for applying it via dbDelta().
 *
 * @package BizHub\Framework\Install
 */
final class Schema
{
    /**
     * Return one dbDelta-compatible CREATE TABLE statement per table,
     * keyed by unprefixed table name.
     *
     * @return array<string,string>
     */
    public function statements(string $tablePrefix, string $charsetCollate): array
    {
        $p = $tablePrefix;

        return [
            'bizhub_companies' => "CREATE TABLE {$p}bizhub_companies (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                registration_number VARCHAR(64) NOT NULL,
                company_name VARCHAR(255) NOT NULL,
                company_type VARCHAR(100) NOT NULL,
                status VARCHAR(32) NOT NULL,
                address_line_1 VARCHAR(255) NOT NULL,
                address_line_2 VARCHAR(255) NOT NULL DEFAULT '',
                suburb VARCHAR(100) NOT NULL DEFAULT '',
                city VARCHAR(100) NOT NULL,
                province VARCHAR(100) NOT NULL DEFAULT '',
                postal_code VARCHAR(20) NOT NULL,
                country VARCHAR(100) NOT NULL DEFAULT 'South Africa',
                incorporation_date DATE NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY registration_number (registration_number),
                KEY client_id (client_id)
            ) {$charsetCollate};",

            'bizhub_directors' => "CREATE TABLE {$p}bizhub_directors (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                id_number VARCHAR(20) NULL,
                passport_number VARCHAR(20) NULL,
                appointment_date DATE NOT NULL,
                resignation_date DATE NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                phone VARCHAR(30) NULL,
                email VARCHAR(255) NULL,
                address_line_1 VARCHAR(255) NULL,
                address_line_2 VARCHAR(255) NULL,
                suburb VARCHAR(100) NULL,
                city VARCHAR(100) NULL,
                province VARCHAR(100) NULL,
                postal_code VARCHAR(20) NULL,
                country VARCHAR(100) NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY company_uuid (company_uuid)
            ) {$charsetCollate};",

            'bizhub_clients' => "CREATE TABLE {$p}bizhub_clients (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                wp_user_id BIGINT UNSIGNED NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                phone VARCHAR(30) NOT NULL DEFAULT '',
                avatar_url VARCHAR(500) NULL,
                status VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY wp_user_id (wp_user_id)
            ) {$charsetCollate};",

            'bizhub_client_notifications' => "CREATE TABLE {$p}bizhub_client_notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                client_uuid CHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(32) NOT NULL DEFAULT 'info',
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY client_uuid (client_uuid)
            ) {$charsetCollate};",

            'bizhub_applications' => "CREATE TABLE {$p}bizhub_applications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(64) NOT NULL,
                company_uuid CHAR(36) NULL,
                status VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                submitted_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY client_id (client_id)
            ) {$charsetCollate};",

            'bizhub_application_steps' => "CREATE TABLE {$p}bizhub_application_steps (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                application_uuid CHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                step_order INT NOT NULL DEFAULT 0,
                completed TINYINT(1) NOT NULL DEFAULT 0,
                completed_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY application_uuid (application_uuid)
            ) {$charsetCollate};",

            'bizhub_application_comments' => "CREATE TABLE {$p}bizhub_application_comments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                application_uuid CHAR(36) NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                message TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY application_uuid (application_uuid)
            ) {$charsetCollate};",

            'bizhub_documents' => "CREATE TABLE {$p}bizhub_documents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                owner_type VARCHAR(32) NOT NULL,
                owner_uuid CHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                category VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY owner (owner_type, owner_uuid)
            ) {$charsetCollate};",

            'bizhub_document_versions' => "CREATE TABLE {$p}bizhub_document_versions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                document_uuid CHAR(36) NOT NULL,
                version_number INT NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                mime_type VARCHAR(100) NOT NULL,
                uploaded_by BIGINT UNSIGNED NOT NULL,
                uploaded_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY document_uuid (document_uuid)
            ) {$charsetCollate};",

            'bizhub_audit_log' => "CREATE TABLE {$p}bizhub_audit_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                action VARCHAR(191) NOT NULL,
                subject_type VARCHAR(191) NOT NULL,
                subject_id VARCHAR(191) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                context LONGTEXT NULL,
                occurred_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY subject (subject_type, subject_id),
                KEY user_id (user_id)
            ) {$charsetCollate};",

            'bizhub_queue_jobs' => "CREATE TABLE {$p}bizhub_queue_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                job_class VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY status (status)
            ) {$charsetCollate};",

            'bizhub_notification_queue' => "CREATE TABLE {$p}bizhub_notification_queue (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                payload LONGTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY status (status)
            ) {$charsetCollate};",

            'bizhub_logs' => "CREATE TABLE {$p}bizhub_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                level VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                context LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY level (level),
                KEY created_at (created_at)
            ) {$charsetCollate};",
        ];
    }
}
