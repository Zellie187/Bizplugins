<?php

declare(strict_types=1);

namespace BizHub\Workflow\Install;

/**
 * Defines the database schema owned by BizUpKeep Workflow.
 *
 * Table and column names here must exactly match the table/column
 * names used by BizHub\Workflow\Repositories\WorkflowRepository.
 * This class only describes the schema; Migrator applies it via
 * dbDelta(). These tables are owned by this plugin (not BizHub
 * Framework) because the workflow engine is this plugin's own
 * bounded context, following the same convention BizHub itself uses
 * for its own modules.
 *
 * @package BizHub\Workflow\Install
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
            'bizhub_workflow_instances' => "CREATE TABLE {$p}bizhub_workflow_instances (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                workflow_type VARCHAR(100) NOT NULL,
                subject_type VARCHAR(100) NOT NULL,
                subject_uuid CHAR(36) NOT NULL,
                status VARCHAR(32) NOT NULL,
                metadata LONGTEXT NULL,
                created_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                completed_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY workflow_type (workflow_type),
                KEY subject (subject_type, subject_uuid),
                KEY status (status)
            ) {$charsetCollate};",

            'bizhub_workflow_transitions' => "CREATE TABLE {$p}bizhub_workflow_transitions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                workflow_uuid CHAR(36) NOT NULL,
                from_status VARCHAR(32) NULL,
                to_status VARCHAR(32) NOT NULL,
                action VARCHAR(100) NOT NULL,
                actor_id BIGINT UNSIGNED NOT NULL,
                reason VARCHAR(500) NOT NULL DEFAULT '',
                context LONGTEXT NULL,
                occurred_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY workflow_uuid (workflow_uuid)
            ) {$charsetCollate};",
        ];
    }
}
