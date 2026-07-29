<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Install;

/**
 * Defines the database schema owned by BizUpKeep Core.
 *
 * Table and column names here must exactly match the table/column
 * names used by Services\ServiceRepository. This class only describes
 * the schema; Migrator applies it via dbDelta().
 *
 * @package BizUpKeep\Core\Install
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
            'bizhub_core_services' => "CREATE TABLE {$p}bizhub_core_services (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                service_key VARCHAR(50) NOT NULL,
                name VARCHAR(150) NOT NULL,
                pricing_mode VARCHAR(20) NOT NULL,
                product_sku VARCHAR(100) NULL,
                product_slug VARCHAR(200) NULL,
                vat_treatment VARCHAR(20) NOT NULL,
                is_recurring TINYINT(1) NOT NULL DEFAULT 0,
                notes VARCHAR(500) NOT NULL DEFAULT '',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY service_key (service_key)
            ) {$charsetCollate};",
        ];
    }
}
