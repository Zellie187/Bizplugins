<?php

declare(strict_types=1);

namespace BizHub\Stub\Install;

/**
 * Defines the database schema owned by BizUpKeep Stub.
 *
 * One table: which Stub "business" a BizHub Company has been
 * provisioned as, and whether/when its historical ledger data has been
 * migrated. Kept in this plugin's own schema rather than added to
 * bizupkeep-bookkeeping's, since bookkeeping is being partially
 * deprecated by this integration, not extended by it.
 *
 * @package BizHub\Stub\Install
 */
final class Schema
{
    /**
     * @return array<string,string>
     */
    public function statements(string $tablePrefix, string $charsetCollate): array
    {
        $p = $tablePrefix;

        return [
            'bizhub_stub_businesses' => "CREATE TABLE {$p}bizhub_stub_businesses (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                company_uuid CHAR(36) NOT NULL,
                stub_business_uid VARCHAR(191) NOT NULL,
                created_at DATETIME NOT NULL,
                migrated_at DATETIME NULL,
                migration_failed_reason TEXT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY company_uuid (company_uuid),
                KEY stub_business_uid (stub_business_uid)
            ) {$charsetCollate};",
        ];
    }
}
